<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Http\Resources\CartResource;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Services\CartService;
use App\Modules\Commerce\Services\Pricing;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Works signed in (session) or as a guest (X-Cart-Token header). Every response is the
 * whole cart, priced by the server.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly Pricing $pricing,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request, $this->carts->find($this->user($request), $this->token($request)));
    }

    /**
     * Add a course to the cart (creates the cart, and for guests its token). For a course with
     * editions, `edition` picks one (default: the recommended edition); adding the course again
     * with another edition switches it — that's also how an owned course is upgraded.
     */
    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course' => ['required', 'string'],
            'edition' => ['nullable', 'integer'],
        ]);
        $user = $this->user($request);
        $course = Course::query()->where('slug', $data['course'])->with(['priceTier', 'editions.priceTier'])->firstOrFail();
        $edition = isset($data['edition']) ? $course->editions->firstWhere('id', (int) $data['edition']) : null;
        if (isset($data['edition']) && $edition === null) {
            throw new DomainException('EDITION_UNKNOWN', 'That option isn’t available for this course.', 422);
        }
        $cart = $this->carts->findOrCreate($user, $this->token($request));
        $this->carts->add($cart, $course, $user, $edition);

        return $this->respond($request, $cart);
    }

    public function remove(Request $request, string $slug): JsonResponse
    {
        $cart = $this->carts->find($this->user($request), $this->token($request));
        $course = Course::query()->where('slug', $slug)->first();
        if ($cart !== null && $course !== null) {
            $this->carts->remove($cart, $course);
        }

        return $this->respond($request, $cart);
    }

    /** Apply a coupon code; an unusable code is kept with a message so the learner sees why. */
    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $cart = $this->carts->findOrCreate($this->user($request), $this->token($request));
        $cart->forceFill(['coupon_code' => Str::upper(trim($data['code']))])->save();

        return $this->respond($request, $cart);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->carts->find($this->user($request), $this->token($request));
        $cart?->forceFill(['coupon_code' => null])->save();

        return $this->respond($request, $cart);
    }

    /** After sign-in: fold the guest cart into the user's. */
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);
        /** @var User $user */
        $user = $request->user();

        return $this->respond($request, $this->carts->merge($user, $data['token']));
    }

    private function respond(Request $request, ?Cart $cart): JsonResponse
    {
        $items = $cart?->items()->with([
            'course.instructor:id,name', 'course.instructorProfile', 'course.category', 'course.priceTier', 'course.editions.priceTier', 'edition.priceTier',
        ])->get() ?? collect();

        // Always 200: the cart may have just been created, but the request didn't ask to create it.
        return (new CartResource($cart, $this->pricing->quote($items, $this->user($request), $cart?->coupon_code)))->response()->setStatusCode(200);
    }

    private function user(Request $request): ?User
    {
        // Session user first (SPA cookie auth), then a Sanctum token.
        /** @var User|null */
        return $request->user() ?? $request->user('sanctum');
    }

    private function token(Request $request): ?string
    {
        $token = $request->header('X-Cart-Token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
