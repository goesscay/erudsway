<?php

namespace App\Modules\Legacy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $system
 * @property string $kind
 * @property string $legacy_id
 * @property string $role
 * @property string $target_type
 * @property int $target_id
 */
class LegacyLink extends Model
{
    protected $fillable = ['system', 'kind', 'legacy_id', 'role', 'target_type', 'target_id'];

    protected function casts(): array
    {
        return ['target_id' => 'integer'];
    }

    /** The id of what a legacy record became, if it has been imported. */
    public static function target(string $kind, int|string $legacyId, string $role = 'main'): ?int
    {
        return self::query()->where(['system' => 'moodle', 'kind' => $kind, 'legacy_id' => (string) $legacyId, 'role' => $role])->value('target_id');
    }

    public static function remember(string $kind, int|string $legacyId, Model $target, string $role = 'main'): void
    {
        self::query()->updateOrCreate(
            ['system' => 'moodle', 'kind' => $kind, 'legacy_id' => (string) $legacyId, 'role' => $role],
            ['target_type' => class_basename($target), 'target_id' => $target->getKey()],
        );
    }

    /**
     * Every target of one legacy record (all roles).
     *
     * @return list<int>
     */
    public static function targets(string $kind, int|string $legacyId): array
    {
        return self::query()->where(['system' => 'moodle', 'kind' => $kind, 'legacy_id' => (string) $legacyId])
            ->pluck('target_id')->map(fn ($id) => (int) $id)->all();
    }
}
