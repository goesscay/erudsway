<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Settings staff can change without a deploy. Each module declares its own (key = config
 * path, with label, help and validation); stored overrides are laid over config() at boot,
 * so the code reading config() needs no changes. Every change is audited.
 *
 * @phpstan-type Definition array{key: string, group: string, label: string, help: string, type: 'text'|'integer'|'bps'|'paise'|'boolean', rules: list<mixed>}
 */
class Settings
{
    private const CACHE_KEY = 'platform.settings';

    /** @var array<string, Definition> */
    private array $definitions = [];

    /** @var array<string, mixed> config value before any override, per key */
    private array $defaults = [];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  'text'|'integer'|'bps'|'paise'|'boolean'  $type
     * @param  list<mixed>  $rules  Laravel validation rules for the value
     */
    public function define(string $key, string $group, string $label, string $help, string $type, array $rules): void
    {
        $this->definitions[$key] = ['key' => $key, 'group' => $group, 'label' => $label, 'help' => $help, 'type' => $type, 'rules' => $rules];
    }

    /** Lay stored overrides over config(). Safe before migrations have run. */
    public function apply(): void
    {
        foreach ($this->stored() as $key => $value) {
            $this->defaults[$key] ??= config($key);
            config()->set($key, $value);
        }
    }

    /**
     * @return list<array{key: string, group: string, label: string, help: string, type: 'text'|'integer'|'bps'|'paise'|'boolean', value: mixed, default: mixed, overridden: bool, updated_at: string|null}>
     */
    public function all(): array
    {
        $meta = DB::table('settings')->get(['key', 'updated_at'])->keyBy('key');

        return array_values(array_map(fn (array $d) => [
            'key' => $d['key'],
            'group' => $d['group'],
            'label' => $d['label'],
            'help' => $d['help'],
            'type' => $d['type'],
            'value' => config($d['key']),
            'default' => array_key_exists($d['key'], $this->defaults) ? $this->defaults[$d['key']] : config($d['key']),
            'overridden' => $meta->has($d['key']),
            'updated_at' => $meta->has($d['key']) ? (string) $meta[$d['key']]->updated_at : null,
        ], $this->definitions));
    }

    /**
     * Save several values at once (all valid, or none saved). A null value resets the
     * setting to its default from config/.env.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(User $actor, array $values): void
    {
        $unknown = array_diff(array_keys($values), array_keys($this->definitions));
        if ($unknown !== []) {
            throw new DomainException('UNKNOWN_SETTING', 'Unknown setting: '.implode(', ', $unknown).'.', 422);
        }

        $rules = [];
        $data = [];
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }
            // Dots are config path separators, not nested input.
            $field = str_replace('.', '__', $key);
            $data[$field] = $value;
            $rules[$field] = ['required', ...$this->definitions[$key]['rules']];
        }
        $validator = Validator::make($data, $rules, [], collect($values)->keys()->mapWithKeys(fn ($k) => [str_replace('.', '__', $k) => $this->definitions[$k]['label']])->all());
        if ($validator->fails()) {
            throw ValidationException::withMessages(
                collect($validator->errors()->messages())->mapWithKeys(fn ($m, $f) => ['values.'.str_replace('__', '.', $f) => $m])->all()
            );
        }

        $before = [];
        $after = [];
        DB::transaction(function () use ($values, $actor, &$before, &$after) {
            foreach ($values as $key => $value) {
                $old = config($key);
                $this->defaults[$key] ??= $old;
                $new = $value === null ? $this->defaults[$key] : $this->cast($key, $value);
                if ($value === null) {
                    DB::table('settings')->where('key', $key)->delete();
                } else {
                    DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($new), 'updated_by' => $actor->id, 'updated_at' => now()]);
                }
                if ($old !== $new) {
                    $before[$key] = $old;
                    $after[$key] = $new;
                }
                config()->set($key, $new);
            }
        });
        Cache::forget(self::CACHE_KEY);

        if ($after !== []) {
            $this->audit->record('settings.updated', null, $before, $after, $actor);
        }
    }

    private function cast(string $key, mixed $value): mixed
    {
        return match ($this->definitions[$key]['type']) {
            'integer', 'bps', 'paise' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'text' => trim((string) $value),
        };
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')
                ->map(fn ($v) => json_decode((string) $v, true))->all());
        } catch (Throwable) {
            return []; // table not migrated yet
        }
    }
}
