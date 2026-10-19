<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Http\Resources\AuditLogResource;
use App\Modules\Platform\Models\AuditLog;
use App\Support\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Read-only view of the append-only audit log. */
class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate($this->rules());

        return AuditLogResource::collection($this->query($filters)->with('actor:id,name,email')->paginate(50));
    }

    /** The distinct action names, for the filter. */
    public function actions(): JsonResponse
    {
        return ApiResponse::data([
            /** @var list<string> */
            'items' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')->values()->all(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $request->validate($this->rules());

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When (IST)', 'Actor', 'Actor email', 'Action', 'Subject type', 'Subject id', 'Before', 'After', 'IP']);
            $this->query($filters)->with('actor:id,name,email')->chunkById(500, function ($rows) use ($out) {
                foreach ($rows as $log) {
                    /** @var AuditLog $log */
                    fputcsv($out, [
                        $log->created_at->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
                        $log->actor->name ?? 'System',
                        $log->actor->email ?? '',
                        $log->action,
                        $log->subject_type,
                        $log->subject_id,
                        $log->before ? json_encode($log->before, JSON_UNESCAPED_UNICODE) : '',
                        $log->after ? json_encode($log->after, JSON_UNESCAPED_UNICODE) : '',
                        $log->ip,
                    ]);
                }
            }, 'id');
        }, 'erudsway-audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:120'],
            'subject_type' => ['nullable', 'string', 'max:60'],
            'subject_id' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<AuditLog>
     */
    private function query(array $f): Builder
    {
        return AuditLog::query()
            ->when($f['action'] ?? null, fn (Builder $q, string $a) => $q->where('action', 'like', $a.'%'))
            ->when($f['actor'] ?? null, fn (Builder $q, string $a) => $q->whereHas('actor', fn (Builder $u) => $u->where('email', 'like', '%'.mb_strtolower($a).'%')->orWhereRaw('lower(name) like ?', ['%'.mb_strtolower($a).'%'])))
            ->when($f['subject_type'] ?? null, fn (Builder $q, string $t) => $q->where('subject_type', 'like', '%'.$t)) // class basename, e.g. Refund
            ->when($f['subject_id'] ?? null, fn (Builder $q, string $id) => $q->where('subject_id', $id))
            ->when($f['from'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '>=', Carbon::parse($d, 'Asia/Kolkata')->startOfDay()->utc()))
            ->when($f['to'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '<=', Carbon::parse($d, 'Asia/Kolkata')->endOfDay()->utc()))
            ->orderByDesc('id');
    }
}
