<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Analytics\Services\Overview;
use App\Modules\Analytics\Services\Period;
use App\Modules\Analytics\Services\Reports;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(private readonly Overview $overview, private readonly Reports $reports) {}

    /** Headline numbers, daily trend and top courses for a period (default: last 30 days). */
    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $o = $this->overview->build(Period::fromDates($data['from'] ?? null, $data['to'] ?? null));

        return ApiResponse::data([
            'period' => $o['period'],
            /** @var list<array{key: string, label: string, value: int, previous: int, money: bool}> */
            'kpis' => $o['kpis'],
            /** @var list<array{date: string, revenue: int, orders: int, enrollments: int, signups: int}> */
            'daily' => $o['daily'],
            /** @var list<array{slug: string, title: string, sales: int, revenue: int}> */
            'top_courses' => $o['top_courses'],
        ]);
    }

    /** Work waiting for staff, limited to what this person can act on. */
    public function queues(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($user->getAllPermissions()->isEmpty(), 403);

        return ApiResponse::data($this->overview->queues($user));
    }

    /** A report as typed columns and rows (money in paise). */
    public function report(Request $request, string $name): JsonResponse
    {
        $data = $request->validate($this->rules());
        abort_unless(in_array($name, Reports::NAMES, true), 404);
        $period = Period::fromDates($data['from'] ?? null, $data['to'] ?? null);

        $table = $this->reports->build($name, $period, $data['group'] ?? 'day');

        return ApiResponse::data([
            'name' => $name,
            'period' => $period->toArray(),
            /** @var list<array{key: string, label: string, type: 'text'|'number'|'money'|'percent'|'date'}> */
            'columns' => $table['columns'],
            // One object per row, keyed by column key. Money in paise.
            /** @var list<array<string, string|int|float|null>> */
            'rows' => $table['rows'],
        ]);
    }

    /** The same report as CSV, with money in rupees. */
    public function csv(Request $request, string $name): StreamedResponse
    {
        $data = $request->validate($this->rules());
        abort_unless(in_array($name, Reports::NAMES, true), 404);
        $period = Period::fromDates($data['from'] ?? null, $data['to'] ?? null);
        $table = $this->reports->build($name, $period, $data['group'] ?? 'day');
        $range = $period->toArray();

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 (₹, names)
            fputcsv($out, array_map(fn ($c) => $c['type'] === 'money' ? $c['label'].' (INR)' : ($c['type'] === 'percent' ? $c['label'].' (%)' : $c['label']), $table['columns']));
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map(function ($c) use ($row) {
                    $v = $row[$c['key']] ?? null;

                    return $c['type'] === 'money' && $v !== null ? number_format(((int) $v) / 100, 2, '.', '') : $v;
                }, $table['columns']));
            }
        }, "erudsway-{$name}-{$range['from']}-to-{$range['to']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['nullable', Rule::in(['day', 'month'])],
        ];
    }
}
