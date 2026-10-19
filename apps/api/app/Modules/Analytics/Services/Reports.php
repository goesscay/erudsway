<?php

namespace App\Modules\Analytics\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Support\Collection;

/**
 * Tabular reports for staff. Each report is a list of typed columns and plain rows, so
 * the admin UI and the CSV export share one shape. Money is in paise.
 *
 * @phpstan-type Column array{key: string, label: string, type: 'text'|'number'|'money'|'percent'|'date'}
 * @phpstan-type Table array{columns: list<Column>, rows: list<array<string, string|int|float|null>>}
 */
class Reports
{
    public const NAMES = ['sales', 'courses', 'instructors', 'refunds'];

    /** @return Table */
    public function build(string $name, Period $period, string $group = 'day'): array
    {
        return match ($name) {
            'sales' => $this->sales($period, $group),
            'courses' => $this->courses($period),
            'instructors' => $this->instructors($period),
            'refunds' => $this->refunds($period),
            default => throw new \InvalidArgumentException("Unknown report {$name}"),
        };
    }

    /**
     * Sales by day or month: what was collected and how it splits.
     *
     * @return Table
     */
    private function sales(Period $p, string $group): array
    {
        $items = $this->paidItems($p);
        $refunds = Refund::query()->where('status', RefundStatus::Processed->value)->whereBetween('processed_at', [$p->from, $p->to])->get(['amount', 'processed_at']);
        $bucket = fn (string $date) => $group === 'month' ? substr($date, 0, 7) : $date;
        $keys = collect($p->dates())->map($bucket)->unique()->values();

        $byBucket = $items->groupBy(fn ($i) => $bucket(Period::istDate((string) $i->getAttribute('paid_at'))));
        $refundsBy = $refunds->groupBy(fn (Refund $r) => $bucket(Period::istDate($r->processed_at ?? now())));

        $rows = $keys->map(function (string $k) use ($byBucket, $refundsBy) {
            /** @var Collection<int, OrderItem> $g */
            $g = $byBucket->get($k, collect());

            return [
                'period' => $k,
                'orders' => $g->pluck('order_id')->unique()->count(),
                'items' => $g->count(),
                'list_value' => (int) $g->sum('list_price'),
                'discounts' => (int) $g->sum('discount'),
                'collected' => (int) $g->sum('amount'),
                'gst' => (int) $g->sum('tax'),
                'gateway_fees' => (int) $g->sum('gateway_fee'),
                'instructor_share' => (int) $g->sum('instructor_amount'),
                'platform_share' => (int) $g->sum('platform_amount'),
                'refunded' => (int) $refundsBy->get($k, collect())->sum('amount'),
            ];
        })->values()->all();

        return [
            'columns' => [
                ['key' => 'period', 'label' => $group === 'month' ? 'Month' : 'Date', 'type' => 'date'],
                ['key' => 'orders', 'label' => 'Orders', 'type' => 'number'],
                ['key' => 'items', 'label' => 'Courses sold', 'type' => 'number'],
                ['key' => 'list_value', 'label' => 'List value', 'type' => 'money'],
                ['key' => 'discounts', 'label' => 'Discounts', 'type' => 'money'],
                ['key' => 'collected', 'label' => 'Collected', 'type' => 'money'],
                ['key' => 'gst', 'label' => 'GST', 'type' => 'money'],
                ['key' => 'gateway_fees', 'label' => 'Gateway fees', 'type' => 'money'],
                ['key' => 'instructor_share', 'label' => 'Instructor share', 'type' => 'money'],
                ['key' => 'platform_share', 'label' => 'Platform share', 'type' => 'money'],
                ['key' => 'refunded', 'label' => 'Refunded', 'type' => 'money'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Every course that is or was live: learners, completion, ratings, sales and refunds.
     * Learner and completion counts are all-time; sales and refunds are for the period.
     *
     * @return Table
     */
    private function courses(Period $p): array
    {
        $courses = Course::query()
            ->whereIn('status', [CourseStatus::Published->value, CourseStatus::Unpublished->value, CourseStatus::Archived->value])
            ->with(['instructor:id,name', 'instructorProfile'])->orderBy('title')->get();
        $ids = $courses->pluck('id');

        $learners = Enrollment::query()->whereIn('course_id', $ids)->where('status', EnrollmentStatus::Active->value)
            ->selectRaw('course_id, COUNT(*) as n, SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as done')
            ->groupBy('course_id')->get()->keyBy('course_id');
        $newLearners = Enrollment::query()->whereIn('course_id', $ids)->whereBetween('granted_at', [$p->from, $p->to])
            ->selectRaw('course_id, COUNT(*) as n')->groupBy('course_id')->pluck('n', 'course_id');
        $sales = $this->paidItems($p)->groupBy('course_id');
        $refunds = $this->processedRefunds($p)->groupBy(fn (Refund $r) => $r->orderItem->course_id);

        $rows = $courses->map(function (Course $c) use ($learners, $newLearners, $sales, $refunds) {
            $l = $learners->get($c->id);
            $total = (int) ($l?->getAttribute('n') ?? 0);
            $done = (int) ($l?->getAttribute('done') ?? 0);
            $sold = $sales->get($c->id, collect());
            $refunded = $refunds->get($c->id, collect());

            return [
                'course' => $c->title,
                'instructor' => $c->instructorProfile->display_name ?? $c->instructor->name,
                'status' => $c->status->value,
                'learners' => $total,
                'new_learners' => (int) ($newLearners[$c->id] ?? 0),
                'completion_rate' => $total > 0 ? round($done * 100 / $total, 1) : null,
                'rating' => $c->rating_count > 0 ? round((float) $c->rating_avg, 2) : null,
                'ratings' => $c->rating_count,
                'sales' => $sold->count(),
                'collected' => (int) $sold->sum('amount'),
                'refunds' => $refunded->count(),
                'refund_rate' => $sold->count() > 0 ? round($refunded->count() * 100 / $sold->count(), 1) : null,
            ];
        })->values()->all();

        return [
            'columns' => [
                ['key' => 'course', 'label' => 'Course', 'type' => 'text'],
                ['key' => 'instructor', 'label' => 'Instructor', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
                ['key' => 'learners', 'label' => 'Learners (all time)', 'type' => 'number'],
                ['key' => 'new_learners', 'label' => 'New learners', 'type' => 'number'],
                ['key' => 'completion_rate', 'label' => 'Completed', 'type' => 'percent'],
                ['key' => 'rating', 'label' => 'Rating', 'type' => 'number'],
                ['key' => 'ratings', 'label' => 'Ratings', 'type' => 'number'],
                ['key' => 'sales', 'label' => 'Sales', 'type' => 'number'],
                ['key' => 'collected', 'label' => 'Collected', 'type' => 'money'],
                ['key' => 'refunds', 'label' => 'Refunds', 'type' => 'number'],
                ['key' => 'refund_rate', 'label' => 'Refund rate', 'type' => 'percent'],
            ],
            'rows' => $rows,
        ];
    }

    /** @return Table */
    private function instructors(Period $p): array
    {
        $instructors = User::query()->whereHas('instructorProfile')->with('instructorProfile')->orderBy('name')->get(['id', 'name', 'email']);
        $courseCounts = Course::query()->where('status', CourseStatus::Published->value)
            ->selectRaw('instructor_id, COUNT(*) as n, SUM(rating_count) as ratings')->groupBy('instructor_id')->get()->keyBy('instructor_id');
        $learners = Enrollment::query()->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->where('enrollments.status', EnrollmentStatus::Active->value)
            ->selectRaw('courses.instructor_id, COUNT(DISTINCT enrollments.user_id) as n')->groupBy('courses.instructor_id')->pluck('n', 'instructor_id');
        $sales = $this->paidItems($p)->groupBy('instructor_id');
        $refunds = $this->processedRefunds($p)->groupBy(fn (Refund $r) => $r->orderItem->instructor_id);

        $rows = $instructors->map(function (User $u) use ($courseCounts, $learners, $sales, $refunds) {
            $sold = $sales->get($u->id, collect());

            return [
                'instructor' => $u->instructorProfile->display_name ?? $u->name,
                'email' => $u->email,
                'live_courses' => (int) ($courseCounts->get($u->id)?->getAttribute('n') ?? 0),
                'learners' => (int) ($learners[$u->id] ?? 0),
                'ratings' => (int) ($courseCounts->get($u->id)?->getAttribute('ratings') ?? 0),
                'sales' => $sold->count(),
                'collected' => (int) $sold->sum('amount'),
                'instructor_share' => (int) $sold->sum('instructor_amount'),
                'refunded' => (int) $refunds->get($u->id, collect())->sum('amount'),
            ];
        })->values()->all();

        return [
            'columns' => [
                ['key' => 'instructor', 'label' => 'Instructor', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'live_courses', 'label' => 'Live courses', 'type' => 'number'],
                ['key' => 'learners', 'label' => 'Learners (all time)', 'type' => 'number'],
                ['key' => 'ratings', 'label' => 'Ratings', 'type' => 'number'],
                ['key' => 'sales', 'label' => 'Sales', 'type' => 'number'],
                ['key' => 'collected', 'label' => 'Collected', 'type' => 'money'],
                ['key' => 'instructor_share', 'label' => 'Instructor share', 'type' => 'money'],
                ['key' => 'refunded', 'label' => 'Refunded', 'type' => 'money'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Every refund requested in the period, whatever happened to it.
     *
     * @return Table
     */
    private function refunds(Period $p): array
    {
        $refunds = Refund::query()->whereBetween('created_at', [$p->from, $p->to])
            ->with(['orderItem.order.user:id,name,email'])->latest()->get();
        $progress = Enrollment::query()
            ->whereIn('order_item_id', $refunds->pluck('order_item_id'))->pluck('progress_percent', 'order_item_id');

        $rows = $refunds->map(fn (Refund $r) => [
            'requested' => Period::istDate($r->created_at),
            'learner' => $r->orderItem->order->user->name,
            'email' => $r->orderItem->order->user->email,
            'course' => $r->orderItem->course_title,
            'order' => $r->orderItem->order->number,
            'amount' => $r->amount,
            'progress' => isset($progress[$r->order_item_id]) ? (int) $progress[$r->order_item_id] : null,
            'status' => $r->status->value,
            'decided' => $r->auto_approved ? 'automatic' : ($r->approved_by ? 'staff' : null),
            'reason' => $r->reason,
        ])->values()->all();

        return [
            'columns' => [
                ['key' => 'requested', 'label' => 'Requested', 'type' => 'date'],
                ['key' => 'learner', 'label' => 'Learner', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'course', 'label' => 'Course', 'type' => 'text'],
                ['key' => 'order', 'label' => 'Order', 'type' => 'text'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'progress', 'label' => 'Progress', 'type' => 'percent'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
                ['key' => 'decided', 'label' => 'Approved', 'type' => 'text'],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'text'],
            ],
            'rows' => $rows,
        ];
    }

    /** @return Collection<int, OrderItem> */
    private function paidItems(Period $p): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at')->whereBetween('orders.paid_at', [$p->from, $p->to])
            ->get(['order_items.*', 'orders.paid_at']);
    }

    /** @return Collection<int, Refund> */
    private function processedRefunds(Period $p): Collection
    {
        return Refund::query()->where('status', RefundStatus::Processed->value)->whereBetween('processed_at', [$p->from, $p->to])
            ->with('orderItem:id,course_id,instructor_id')->get();
    }
}
