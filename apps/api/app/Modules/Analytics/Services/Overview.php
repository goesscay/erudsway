<?php

namespace App\Modules\Analytics\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Engagement\Models\Review;
use App\Modules\Finance\Enums\PayoutAccountStatus;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Models\Payout;
use App\Modules\Finance\Models\PayoutAccount;
use App\Modules\Identity\Enums\ApplicationStatus;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\InstructorApplication;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Support\Collection;

/** The admin dashboard: headline numbers for a period, a daily trend, and work waiting. */
class Overview
{
    /**
     * @return array{period: array{from: string, to: string}, kpis: list<array{key: string, label: string, value: int, previous: int, money: bool}>, daily: list<array{date: string, revenue: int, orders: int, enrollments: int, signups: int}>, top_courses: list<array{slug: string, title: string, sales: int, revenue: int}>}
     */
    public function build(Period $period): array
    {
        $now = $this->figures($period);
        $before = $this->figures($period->previous());

        $labels = [
            'revenue' => ['Revenue collected', true],
            'platform' => ['Platform share', true],
            'refunds' => ['Refunded', true],
            'orders' => ['Paid orders', false],
            'signups' => ['New accounts', false],
            'enrollments' => ['New enrollments', false],
            'completions' => ['Course completions', false],
            'certificates' => ['Certificates issued', false],
        ];

        $kpis = [];
        foreach ($labels as $key => [$label, $money]) {
            $kpis[] = ['key' => $key, 'label' => $label, 'value' => $now['totals'][$key], 'previous' => $before['totals'][$key], 'money' => $money];
        }

        $daily = [];
        foreach ($period->dates() as $date) {
            $daily[] = [
                'date' => $date,
                'revenue' => $now['daily']['revenue'][$date] ?? 0,
                'orders' => $now['daily']['orders'][$date] ?? 0,
                'enrollments' => $now['daily']['enrollments'][$date] ?? 0,
                'signups' => $now['daily']['signups'][$date] ?? 0,
            ];
        }

        return ['period' => $period->toArray(), 'kpis' => $kpis, 'daily' => $daily, 'top_courses' => $now['top']];
    }

    /**
     * Work waiting for staff, limited to the queues this person can act on.
     *
     * @return list<array{key: string, label: string, count: int, href: string}>
     */
    public function queues(User $user): array
    {
        $queues = [
            [Permission::ModerateCourses, 'course_reviews', 'Courses waiting for review', '/admin/course-reviews', fn () => Course::query()->where('status', CourseStatus::InReview->value)->count()],
            [Permission::ReviewInstructorApplications, 'applications', 'Instructor applications', '/admin/instructor-applications', fn () => InstructorApplication::query()->where('status', ApplicationStatus::Pending->value)->count()],
            [Permission::ModerateCourses, 'reported_reviews', 'Reported learner reviews', '/admin/reviews', fn () => Review::query()->where('status', ReviewStatus::Flagged->value)->count()],
            [Permission::ManageFinance, 'refunds', 'Refund requests to decide', '/admin/refunds', fn () => Refund::query()->where('status', RefundStatus::Requested->value)->count()],
            [Permission::ManageFinance, 'payouts', 'Payouts awaiting approval', '/admin/payouts', fn () => Payout::query()->where('status', PayoutStatus::Draft->value)->count()],
            [Permission::ManageFinance, 'payout_accounts', 'Payout details to verify', '/admin/payouts', fn () => PayoutAccount::query()->where('status', PayoutAccountStatus::Submitted->value)->count()],
        ];

        $out = [];
        foreach ($queues as [$permission, $key, $label, $href, $count]) {
            if ($user->can($permission->value)) {
                $out[] = ['key' => $key, 'label' => $label, 'count' => (int) $count(), 'href' => $href];
            }
        }

        return $out;
    }

    /**
     * @return array{totals: array<string, int>, daily: array<string, array<string, int>>, top: list<array{slug: string, title: string, sales: int, revenue: int}>}
     */
    private function figures(Period $p): array
    {
        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.paid_at')->whereBetween('orders.paid_at', [$p->from, $p->to])
            ->get(['order_items.order_id', 'order_items.course_id', 'order_items.course_title', 'order_items.amount', 'order_items.platform_amount', 'orders.paid_at']);
        $refunds = Refund::query()->where('status', RefundStatus::Processed->value)->whereBetween('processed_at', [$p->from, $p->to])->sum('amount');
        $signups = User::query()->whereBetween('created_at', [$p->from, $p->to])->pluck('created_at');
        $enrollments = Enrollment::query()->whereBetween('granted_at', [$p->from, $p->to])->pluck('granted_at');

        $daily = [
            'revenue' => $this->byDay($items, 'paid_at', fn (Collection $g) => (int) $g->sum('amount')),
            'orders' => $this->byDay($items, 'paid_at', fn (Collection $g) => $g->pluck('order_id')->unique()->count()),
            'enrollments' => $enrollments->countBy(fn ($at) => Period::istDate($at))->all(),
            'signups' => $signups->countBy(fn ($at) => Period::istDate($at))->all(),
        ];

        $slugs = Course::query()->whereIn('id', $items->pluck('course_id')->unique())->pluck('slug', 'id');
        $top = $items->groupBy('course_id')
            ->map(fn (Collection $g, $courseId) => [
                'slug' => (string) ($slugs[$courseId] ?? ''),
                'title' => (string) $g->first()?->getAttribute('course_title'),
                'sales' => $g->count(),
                'revenue' => (int) $g->sum('amount'),
            ])
            ->sortByDesc('revenue')->take(5)->values()->all();

        return [
            'totals' => [
                'revenue' => (int) $items->sum('amount'),
                'platform' => (int) $items->sum('platform_amount'),
                'refunds' => (int) $refunds,
                'orders' => $items->pluck('order_id')->unique()->count(),
                'signups' => $signups->count(),
                'enrollments' => $enrollments->count(),
                'completions' => Enrollment::query()->whereBetween('completed_at', [$p->from, $p->to])->count(),
                'certificates' => Certificate::query()->whereBetween('issued_at', [$p->from, $p->to])->count(),
            ],
            'daily' => $daily,
            'top' => $top,
        ];
    }

    /**
     * @param  Collection<int, OrderItem>  $rows
     * @param  callable(Collection<int, OrderItem>): int  $reduce
     * @return array<string, int>
     */
    private function byDay(Collection $rows, string $column, callable $reduce): array
    {
        return $rows->groupBy(fn ($r) => Period::istDate($r->getAttribute($column)))->map($reduce)->all();
    }
}
