<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Commerce\Enums\OfflinePaymentMethod;
use App\Modules\Commerce\Http\Requests\RecordOfflinePaymentRequest;
use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Services\OfflineSales;
use App\Modules\Commerce\Services\Purchasability;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Payments taken outside the website (cash, bank transfer, UPI…). Requires `manage_finance`.
 * The learner gets the course, a GST invoice and a receipt, exactly as if they'd paid online.
 */
class OfflinePaymentController extends Controller
{
    public function __construct(private readonly OfflineSales $sales) {}

    /** Find the learner who paid, by name or email (finance staff may not have user management). */
    public function learners(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = '%'.mb_strtolower($data['q']).'%';
        $users = User::query()
            ->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$term])->orWhere('email', 'like', $term))
            ->orderBy('name')->limit(8)->get(['uuid', 'name', 'email', 'status']);

        return ApiResponse::data([
            /** @var list<array{id: string, name: string, email: string, suspended: bool}> */
            'items' => $users->map(fn (User $u) => [
                'id' => $u->uuid,
                'name' => $u->name,
                'email' => $u->email,
                'suspended' => $u->isSuspended(),
            ])->values()->all(),
        ]);
    }

    /** The price this learner would pay for a course or edition (less if it's an upgrade), and whether they can buy it. */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string', Rule::exists('users', 'uuid')],
            'course' => ['required', 'string', Rule::exists('courses', 'slug')],
            'edition_id' => ['nullable', 'integer'],
        ]);
        [$buyer, $course, $edition] = $this->resolve($data);
        $line = $this->sales->quote($buyer, $course, $edition);

        return ApiResponse::data([
            'title' => $line->title(),
            'list_price' => $line->listPrice,
            'upgrade_from' => $line->upgradeFrom?->name,
            // Why it can't be sold to this learner, if it can't.
            'problem' => $line->problem !== null ? Purchasability::message($line->problem) : null,
            /** @var list<array{value: 'cash'|'bank_transfer'|'upi'|'cheque'|'card'|'other', label: string}> */
            'methods' => array_map(fn (OfflinePaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], OfflinePaymentMethod::cases()),
        ]);
    }

    /** Record the payment: creates a paid order, gives access, and emails the GST invoice. */
    public function store(RecordOfflinePaymentRequest $request): OrderResource
    {
        /** @var User $staff */
        $staff = $request->user();
        $data = $request->validated();
        [$buyer, $course, $edition] = $this->resolve($data);

        $order = $this->sales->record(
            $staff,
            $buyer,
            $course,
            $edition,
            (int) $data['amount'],
            [
                'method' => OfflinePaymentMethod::from($data['method']),
                'reference' => $data['reference'] ?? null,
                'received_on' => Carbon::parse($data['received_on']),
                'note' => $data['note'] ?? null,
            ],
            [
                'name' => $data['name'],
                'state_code' => $data['state_code'],
                'gstin' => $data['gstin'] ?? null,
                'business_name' => $data['business_name'] ?? null,
                'address' => $data['address'] ?? null,
            ],
        );

        return new OrderResource($order->load(['items.course', 'items.refunds', 'invoice', 'payments']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: User, 1: Course, 2: CourseEdition|null}
     */
    private function resolve(array $data): array
    {
        $buyer = User::query()->where('uuid', $data['user'])->firstOrFail();
        $course = Course::query()->where('slug', $data['course'])->firstOrFail();
        $edition = null;
        if (! empty($data['edition_id'])) {
            $edition = CourseEdition::query()->where('course_id', $course->id)->find($data['edition_id']);
            if ($edition === null) {
                throw new DomainException('EDITION_NOT_FOUND', 'That edition isn’t part of this course.', 422);
            }
        }

        return [$buyer, $course, $edition];
    }
}
