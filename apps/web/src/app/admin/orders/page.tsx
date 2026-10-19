"use client";

import { useDeferredValue, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Search } from "lucide-react";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, unwrap } from "@/lib/api/client";
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

export default function OrdersPage() {
  return <RequireAuth permission="manage_finance">{() => <Orders />}</RequireAuth>;
}

function Orders() {
  const [q, setQ] = useState("");
  const [status, setStatus] = useState<StatusFilter>("");
  const [page, setPage] = useState(1);
  const deferredQ = useDeferredValue(q.trim());
  const list = useQuery({
    queryKey: ["admin", "orders", deferredQ, status, page],
    queryFn: () =>
      unwrap(api.GET("/admin/orders", { params: { query: { q: deferredQ || undefined, status: status || undefined, page } } })),
  });
  const rows = list.data?.data ?? [];
  const meta = list.data?.meta as { current_page: number; last_page: number; total: number } | undefined;

  return (
    <>
      <AdminPageHeader title="Orders" description={meta ? plural(meta.total, "order") : "Every checkout, paid or not."} />
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
                      {o.items.map((i) => (
                        <p key={i.id} className="truncate">
                          {i.course.title}
                        </p>
                      ))}
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
    </>
  );
}
