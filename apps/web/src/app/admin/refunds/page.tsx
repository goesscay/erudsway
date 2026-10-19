"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";

type RefundRow = components["schemas"]["RefundResource"];
type Status = RefundRow["status"];

const tabs: { value: Status; label: string }[] = [
  { value: "requested", label: "Awaiting decision" },
  { value: "processing", label: "Issued" },
  { value: "processed", label: "Completed" },
  { value: "rejected", label: "Declined" },
  { value: "failed", label: "Failed" },
];

export default function RefundsPage() {
  return <RequireAuth permission="manage_finance">{() => <Queue />}</RequireAuth>;
}

function Queue() {
  const [status, setStatus] = useState<Status>("requested");
  const list = useQuery({
    queryKey: ["admin", "refunds", status],
    queryFn: async () => (await unwrap(api.GET("/admin/refunds", { params: { query: { status } } }))).data,
  });

  return (
    <>
      <AdminPageHeader title="Refunds" description="Requests within 30 days are approved automatically below 30% progress; the rest wait here." />
      <div className="px-8 py-6">
        <div role="tablist" aria-label="Refund status" className="mb-6 flex gap-6 overflow-x-auto overflow-y-hidden border-b border-line">
          {tabs.map((t) => (
            <button
              key={t.value}
              role="tab"
              aria-selected={status === t.value}
              onClick={() => setStatus(t.value)}
              className={cn("-mb-px shrink-0 border-b-2 pb-3 text-sm font-semibold", status === t.value ? "border-gold-500 text-navy-900" : "border-transparent text-stone-500 hover:text-navy-900")}
            >
              {t.label}
            </button>
          ))}
        </div>
        {list.isPending ? (
          <Skeleton className="h-40 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load refunds.</FormAlert>
        ) : list.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">Nothing here.</div>
        ) : (
          <ul className="space-y-3">
            {list.data.map((r) => (
              <RefundCard key={r.id} refund={r} />
            ))}
          </ul>
        )}
      </div>
    </>
  );
}

function RefundCard({ refund: r }: { refund: RefundRow }) {
  const queryClient = useQueryClient();
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<"approve" | "reject" | null>(null);

  const decide = async (decision: "approve" | "reject") => {
    setBusy(decision);
    setError(null);
    try {
      const path = { params: { path: { refund: r.id } } };
      if (decision === "approve") await mutate(() => api.POST("/admin/refunds/{refund}/approve", { ...path, body: { note: note.trim() || null } }));
      else await mutate(() => api.POST("/admin/refunds/{refund}/reject", { ...path, body: { note: note.trim() } }));
      await queryClient.invalidateQueries({ queryKey: ["admin", "refunds"] });
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
      setBusy(null);
    }
  };

  return (
    <li className="rounded-md border border-line bg-white p-5 text-sm">
      <div className="flex flex-wrap items-start gap-4">
        <div className="min-w-0 flex-1">
          <p className="font-semibold text-ink">{r.course.title}</p>
          <p className="mt-0.5 text-xs text-stone-500">
            {r.learner.name} ({r.learner.email}) · order {r.order.number} · bought {formatDate(r.order.paid_at)} · requested {formatDate(r.created_at)}
          </p>
          <p className="mt-2 text-stone-700">&ldquo;{r.reason}&rdquo;</p>
          {r.decision_note && <p className="mt-1 text-xs text-stone-500">Note: {r.decision_note}</p>}
        </div>
        <div className="text-right">
          <p className="tabular font-semibold text-ink">{formatPaise(r.amount)}</p>
          <p className="text-xs text-stone-500">{r.progress_percent}% of course completed</p>
          <div className="mt-1">
            <StatusPill status={r.auto_approved ? "auto-approved" : r.status} />
          </div>
        </div>
      </div>
      {(r.status === "requested" || r.status === "failed") && (
        <div className="mt-4 space-y-2 border-t border-line pt-4">
          {error && <FormAlert>{error}</FormAlert>}
          <Label htmlFor={`note-${r.id}`} className="text-xs font-semibold text-stone-700">
            Note to the learner (required to decline)
          </Label>
          <Textarea id={`note-${r.id}`} rows={2} value={note} onChange={(e) => setNote(e.target.value)} className="bg-white" />
          <div className="flex gap-2">
            <Button size="sm" onClick={() => decide("approve")} disabled={!!busy}>
              {busy === "approve" ? "Refunding…" : r.status === "failed" ? "Retry refund" : "Approve and refund"}
            </Button>
            {r.status === "requested" && (
              <Button size="sm" variant="destructive" onClick={() => decide("reject")} disabled={!!busy || note.trim().length < 10}>
                Decline
              </Button>
            )}
          </div>
        </div>
      )}
    </li>
  );
}
