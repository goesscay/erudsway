<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Enums\CouponKind;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Commerce\Http\Resources\CouponResource;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Platform coupons. Requires `manage_finance`; changes are audited. */
class CouponController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CouponResource::collection(Coupon::query()->with('course:id,slug,title')->withCount('redemptions')->latest()->get());
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{4,40}$/'],
            'kind' => ['required', Rule::enum(CouponKind::class)],
            'value' => ['required', 'integer', 'min:1'],
            'channel' => ['sometimes', Rule::enum(SaleChannel::class)],
            'course' => ['nullable', 'string'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], ['code.regex' => 'Use 4–40 letters, numbers, dashes or underscores.']);

        if ($data['kind'] === CouponKind::Percent->value && $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'A percentage can be at most 100.']);
        }
        $code = Str::upper($data['code']);
        if (Coupon::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'That code already exists.']);
        }
        $course = ! empty($data['course']) ? Course::query()->where('slug', $data['course'])->first() : null;
        if (! empty($data['course']) && $course === null) {
            throw ValidationException::withMessages(['course' => 'No course with that address.']);
        }

        $coupon = Coupon::query()->create([
            'code' => $code,
            'kind' => $data['kind'],
            'value' => $data['value'],
            'channel' => $data['channel'] ?? SaleChannel::PlatformPromoted->value,
            'course_id' => $course?->id,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'per_user_limit' => $data['per_user_limit'] ?? 1,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_by' => $this->user($request)->id,
        ]);
        $audit->record('coupon.created', $coupon, null, $coupon->only(['code', 'kind', 'value', 'channel', 'course_id']));

        return (new CouponResource($coupon->load('course')->loadCount('redemptions')))->response()->setStatusCode(201);
    }

    /** Switch a coupon on or off (codes are never edited once people may have them). */
    public function update(Request $request, Coupon $coupon, AuditLogger $audit): CouponResource
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $before = ['is_active' => $coupon->is_active];
        $coupon->update(['is_active' => $data['is_active']]);
        $audit->record('coupon.updated', $coupon, $before, ['is_active' => $coupon->is_active]);

        return new CouponResource($coupon->load('course')->loadCount('redemptions'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
