"use client";

import { useDeferredValue, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Search } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { formatPaise, plural } from "@/lib/format";

const statuses = [
  { value: "", label: "All orders" },
  { value: "paid", label: "Paid" },
  { value: "pending", label: "Pending" },
  { value: "partially_refunded", label: "Partly refunded" },
  { value: "refunded", label: "Refunded" },
  { value: "failed", label: "Failed" },
] as const;

type StatusFilter = (typeof statuses)[number]["value"];
type Order = components["schemas"]["OrderResource"];
type Item = Order["items"][number];

const refundLabel: Record<NonNullable<Item["refund"]>["status"], string> = {
  requested: "Refund requested",
  processing: "Refund processing",
  processed: "Refunded",
  rejected: "Refund declined",
  failed: "Refund failed",
};

export default function OrdersPage() {
  return <RequireAuth permission="manage_finance">{() => <Orders />}</RequireAuth>;
}

function Orders() {
  const [q, setQ] = useState("");
  const [status, setStatus] = useState<StatusFilter>("");
  const [page, setPage] = useState(1);
  const [refunding, setRefunding] = useState<{ order: Order; item: Item } | null>(null);
  const deferredQ = useDeferredValue(q.trim());
  const list = useQuery({
    queryKey: ["admin", "orders", deferredQ, status, page],
    queryFn: () =>
      unwrap(
        api.GET("/admin/orders", {
          params: { query: { q: deferredQ || undefined, status: status || undefined, page } },
        }),
      ),
  });
  const rows = list.data?.data ?? [];
  const meta = list.data?.meta as { current_page: number; last_page: number; total: number } | undefined;

  return (
    <>
      <AdminPageHeader
        title="Orders"
        description={meta ? plural(meta.total, "order") : "Every checkout, paid or not."}
      />
      <div className="px-8 py-6">
        <div className="mb-5 flex flex-wrap gap-3">
          <label className="relative max-w-sm flex-1">
            <span className="sr-only">Search orders</span>
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-500" />
            <input
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                setPage(1);
              }}
              placeholder="Order number, name or email"
              className="h-10 w-full rounded-md border border-input bg-white pr-3 pl-9 text-sm"
            />
          </label>
          <select
            value={status}
            onChange={(e) => {
              setStatus(e.target.value as StatusFilter);
              setPage(1);
            }}
            aria-label="Status"
            className="h-10 rounded-md border border-input bg-white px-3 text-sm"
          >
            {statuses.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
        </div>

        {list.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load orders.</FormAlert>
        ) : (
          <div className="overflow-x-auto rounded-md border border-line bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                <tr>
                  <th className="px-5 py-3">Order</th>
                  <th className="px-5 py-3">Learner</th>
                  <th className="px-5 py-3">Courses</th>
                  <th className="px-5 py-3 text-right">Total</th>
                  <th className="px-5 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-5 py-12 text-center text-stone-500">
                      No orders match.
                    </td>
                  </tr>
                )}
                {rows.map((o) => (
                  <tr key={o.id} className="align-top">
                    <td className="px-5 py-3">
                      <p className="font-mono text-xs font-semibold whitespace-nowrap text-ink">{o.number}</p>
                      <p className="text-xs text-stone-500">{formatDate(o.paid_at ?? o.created_at)}</p>
                    </td>
                    <td className="px-5 py-3">
                      <p className="text-ink">{o.billing.name}</p>
                      <p className="text-xs text-stone-500">
                        {o.billing.email} · {o.billing.state}
                        {o.billing.gstin && <> · GSTIN {o.billing.gstin}</>}
                      </p>
                    </td>
                    <td className="px-5 py-3 text-stone-700">
                      {o.items.map((i) => {
                        // Purchases are final for learners; staff refund genuine problems here.
                        const canRefund =
                          ["paid", "partially_refunded"].includes(o.status) &&
                          (!i.refund || ["rejected", "failed"].includes(i.refund.status));
                        return (
                          <div key={i.id} className="flex items-center justify-between gap-3 py-0.5">
                            <span className="truncate">{i.course.title}</span>
                            {i.refund && i.refund.status !== "rejected" ? (
                              <span className="shrink-0 text-xs font-semibold text-stone-500">
                                {refundLabel[i.refund.status]}
                              </span>
                            ) : (
                              canRefund && (
                                <Button
                                  size="xs"
                                  variant="ghost"
                                  className="shrink-0 text-destructive"
                                  onClick={() => setRefunding({ order: o, item: i })}
                                >
                                  Refund<span className="sr-only"> {i.course.title}</span>
                                </Button>
                              )
                            )}
                          </div>
                        );
                      })}
                      {o.coupon_code && <p className="text-xs text-stone-500">Coupon {o.coupon_code}</p>}
                    </td>
                    <td className="tabular px-5 py-3 text-right font-semibold text-ink">{formatPaise(o.total)}</td>
                    <td className="px-5 py-3">
                      <StatusPill status={o.status.replace("_", " ")} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="mt-4 flex items-center justify-end gap-3 text-sm">
            <span className="tabular text-stone-500">
              Page {page} of {meta.last_page}
            </span>
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button size="sm" variant="outline" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        )}
      </div>
      <RefundDialog target={refunding} onClose={() => setRefunding(null)} />
    </>
  );
}

function RefundDialog({ target, onClose }: { target: { order: Order; item: Item } | null; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const close = () => {
    setReason("");
    setError(null);
    onClose();
  };
  const submit = async () => {
    if (!target) return;
    setBusy(true);
    setError(null);
    try {
      await mutate(() =>
        api.POST("/admin/order-items/{item}/refund", {
          params: { path: { item: target.item.id } },
          body: { reason: reason.trim() },
        }),
      );
      await queryClient.invalidateQueries({ queryKey: ["admin", "orders"] });
      close();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={!!target} onOpenChange={(o) => !o && close()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Refund this course?</DialogTitle>
          <DialogDescription>
            {target && (
              <>
                <span className="font-semibold text-ink">{target.item.course.title}</span> · order {target.order.number}{" "}
                ·{" "}
                {formatPaise(
                  target.item.amount - (target.item.refund?.status === "processed" ? target.item.refund.amount : 0),
                )}{" "}
                back to {target.order.billing.name}. Their access to the course ends, and the money goes back through
                Razorpay.
              </>
            )}
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <div className="space-y-1.5">
          <Label htmlFor="refund-reason">Reason (kept in the audit log)</Label>
          <Textarea
            id="refund-reason"
            rows={3}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="e.g. Charged twice — duplicate payment pay_…"
          />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={close}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={submit} disabled={busy || reason.trim().length < 10}>
            {busy ? "Refunding…" : "Refund"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
