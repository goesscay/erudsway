<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Services\Settings;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    /** Every editable setting with its current value and the config/.env default. */
    public function index(): JsonResponse
    {
        return ApiResponse::data($this->present());
    }

    /** Change several settings at once; a null value resets one to its default. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['values' => ['required', 'array', 'min:1']]);
        /** @var User $user */
        $user = $request->user();
        $this->settings->update($user, $data['values']);

        return ApiResponse::data($this->present());
    }

    /** @return array{items: list<array{key: string, group: string, label: string, help: string, type: 'text'|'integer'|'bps'|'paise'|'boolean', value: mixed, default: mixed, overridden: bool, updated_at: string|null}>} */
    private function present(): array
    {
        return [
            /** @var list<array{key: string, group: string, label: string, help: string, type: 'text'|'integer'|'bps'|'paise'|'boolean', value: string|int|bool|null, default: string|int|bool|null, overridden: bool, updated_at: string|null}> */
            'items' => $this->settings->all(),
        ];
    }
}
