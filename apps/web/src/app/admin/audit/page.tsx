"use client";

import { useState } from "react";
import { ChevronDown, Download } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert, TextInput } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { auditCsvUrl, useAuditActions, useAuditLogs, type AuditFilters } from "@/lib/admin/api";
import { cn } from "@/lib/utils";

export default function AuditLogPage() {
  return <RequireAuth permission="view_audit_logs">{() => <AuditLog />}</RequireAuth>;
}

function AuditLog() {
  const [draft, setDraft] = useState<AuditFilters>({});
  const [filters, setFilters] = useState<AuditFilters>({});
  const [page, setPage] = useState(1);
  const logs = useAuditLogs(filters, page);
  const actions = useAuditActions();

  const apply = (next: AuditFilters) => {
    setFilters(next);
    setPage(1);
  };

  return (
    <>
      <AdminPageHeader
        title="Audit log"
        description="Every administrative, financial and course-management action, newest first. Entries can’t be edited or deleted."
      >
        <a href={auditCsvUrl(filters)} className={buttonVariants({ variant: "outline" })}>
          <Download /> Download CSV
        </a>
      </AdminPageHeader>

      <div className="space-y-5 px-4 py-6 sm:px-8">
        <form
          className="flex flex-wrap items-end gap-3 rounded-md border border-line bg-white p-4 text-sm"
          onSubmit={(e) => {
            e.preventDefault();
            apply(draft);
          }}
        >
          <label className="space-y-1">
            <span className="block text-xs font-semibold text-stone-500">Action</span>
            <select
              value={draft.action ?? ""}
              onChange={(e) => {
                const next = { ...draft, action: e.target.value || undefined };
                setDraft(next);
                apply(next);
              }}
              className="h-10 rounded-md border border-input bg-white px-2.5"
            >
              <option value="">All actions</option>
              {actions.data?.map((a) => (
                <option key={a} value={a}>
                  {a}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1">
            <span className="block text-xs font-semibold text-stone-500">Who</span>
            <TextInput
              value={draft.actor ?? ""}
              onChange={(e) => setDraft({ ...draft, actor: e.target.value })}
              placeholder="Name or email"
              className="h-10 w-52 text-sm"
            />
          </label>
          <label className="space-y-1">
            <span className="block text-xs font-semibold text-stone-500">Record</span>
            <TextInput
              value={draft.subject_type ?? ""}
              onChange={(e) => setDraft({ ...draft, subject_type: e.target.value })}
              placeholder="e.g. Refund, User"
              className="h-10 w-40 text-sm"
            />
          </label>
          <label className="space-y-1">
            <span className="block text-xs font-semibold text-stone-500">From</span>
            <input
              type="date"
              value={draft.from ?? ""}
              onChange={(e) => setDraft({ ...draft, from: e.target.value })}
              className="h-10 rounded-md border border-input bg-white px-2"
            />
          </label>
          <label className="space-y-1">
            <span className="block text-xs font-semibold text-stone-500">To</span>
            <input
              type="date"
              value={draft.to ?? ""}
              onChange={(e) => setDraft({ ...draft, to: e.target.value })}
              className="h-10 rounded-md border border-input bg-white px-2"
            />
          </label>
          <Button type="submit">Filter</Button>
          {Object.values(filters).some(Boolean) && (
            <Button
              type="button"
              variant="ghost"
              onClick={() => {
                setDraft({});
                apply({});
              }}
            >
              Clear
            </Button>
          )}
        </form>

        {logs.isPending ? (
          <Skeleton className="h-96 w-full" />
        ) : logs.isError ? (
          <FormAlert>Couldn&rsquo;t load the audit log.</FormAlert>
        ) : logs.data.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">
            No entries match.
          </div>
        ) : (
          <>
            <ul
              className={cn(
                "divide-y divide-line rounded-md border border-line bg-white",
                logs.isPlaceholderData && "opacity-60",
              )}
            >
              {logs.data.data.map((log) => (
                <Entry key={log.id} log={log} />
              ))}
            </ul>
            {logs.data.meta.last_page > 1 && (
              <div className="flex items-center justify-between text-sm">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                  Newer
                </Button>
                <span className="tabular text-stone-500">
                  Page {logs.data.meta.current_page} of {logs.data.meta.last_page}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= logs.data.meta.last_page}
                  onClick={() => setPage(page + 1)}
                >
                  Older
                </Button>
              </div>
            )}
          </>
        )}
      </div>
    </>
  );
}

type Log = NonNullable<ReturnType<typeof useAuditLogs>["data"]>["data"][number];

function Entry({ log }: { log: Log }) {
  const [open, setOpen] = useState(false);
  const hasDetail = !!(log.before || log.after);
  const keys = [...new Set([...Object.keys(log.before ?? {}), ...Object.keys(log.after ?? {})])];
  const when = new Date(log.created_at).toLocaleString("en-IN", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    timeZone: "Asia/Kolkata",
  });

  return (
    <li>
      <button
        type="button"
        onClick={() => hasDetail && setOpen(!open)}
        aria-expanded={hasDetail ? open : undefined}
        className={cn(
          "flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-left text-sm",
          hasDetail && "hover:bg-paper/60",
        )}
      >
        <span className="tabular w-40 shrink-0 text-xs text-stone-500">{when}</span>
        <span className="font-mono text-xs font-semibold text-navy-900">{log.action}</span>
        <span className="text-stone-700">
          {log.actor ? log.actor.name : <em className="text-stone-500">System</em>}
        </span>
        {log.subject_type && (
          <span className="text-xs text-stone-500">
            {log.subject_type} #{log.subject_id}
          </span>
        )}
        {hasDetail && (
          <ChevronDown className={cn("ml-auto size-4 text-stone-500 transition-transform", open && "rotate-180")} />
        )}
      </button>
      {open && (
        <div className="overflow-x-auto px-5 pb-4">
          <table className="w-full max-w-3xl text-left text-xs">
            <thead className="text-stone-500">
              <tr>
                <th className="py-1 pr-4 font-semibold">Field</th>
                <th className="py-1 pr-4 font-semibold">Before</th>
                <th className="py-1 font-semibold">After</th>
              </tr>
            </thead>
            <tbody className="font-mono">
              {keys.map((k) => (
                <tr key={k} className="border-t border-line align-top">
                  <td className="py-1.5 pr-4 text-stone-700">{k}</td>
                  <td className="py-1.5 pr-4 break-all text-destructive/80">{show(log.before?.[k])}</td>
                  <td className="py-1.5 break-all text-success">{show(log.after?.[k])}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="mt-2 text-xs text-stone-500">IP {log.ip ?? "unknown"}</p>
        </div>
      )}
    </li>
  );
}

function show(v: unknown): string {
  if (v === undefined) return "—";
  return typeof v === "string" ? v : JSON.stringify(v);
}
