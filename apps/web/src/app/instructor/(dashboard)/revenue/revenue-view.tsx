"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { CircleAlert } from "lucide-react";
import { formatDate, StatusPill } from "@/components/admin/page-header";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, unwrap } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";

const monthLabel = (ym: string) => new Date(`${ym}-01T00:00:00`).toLocaleDateString("en-IN", { month: "short" });

export function RevenueView() {
  const { data: me } = useMe();
  const enabled = !!me?.instructor;
  const revenue = useQuery({ queryKey: ["instructor", "revenue"], enabled, queryFn: async () => (await unwrap(api.GET("/instructor/revenue"))).data });
  const account = useQuery({ queryKey: ["instructor", "payout-account"], enabled, queryFn: async () => (await unwrap(api.GET("/instructor/payout-account"))).data });

  return (
    <>
      <header className="border-b border-line bg-white px-4 py-6 sm:px-8">
        <h1 className="text-h2">Revenue</h1>
        <p className="mt-1 text-sm text-stone-500">Your share of every sale, after GST and payment fees. Paid monthly to your bank account.</p>
      </header>

      <div className="space-y-8 px-4 py-8 sm:px-8">
        {account.data === null && (
          <FormAlert tone="info">
            Add your PAN and bank details so we can pay you.{" "}
            <Link href="/instructor/payout-details" className="font-semibold underline">
              Add payout details
            </Link>
          </FormAlert>
        )}
        {account.data?.status === "rejected" && (
          <FormAlert>
            We couldn&rsquo;t verify your payout details: {account.data.review_note}{" "}
            <Link href="/instructor/payout-details" className="font-semibold underline">
              Update them
            </Link>
          </FormAlert>
        )}

        {revenue.isPending ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {[0, 1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-28 w-full" />
            ))}
          </div>
        ) : revenue.isError ? (
          <FormAlert>Couldn&rsquo;t load your revenue.</FormAlert>
        ) : (
          <>
            <section aria-label="Balances" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <Stat label="Available for payout" value={revenue.data.balances.available} hint={`Paid monthly once it reaches ${formatPaise(revenue.data.payout_minimum, true)}`} strong />
              <Stat
                label="On hold"
                value={revenue.data.balances.pending}
                hint={revenue.data.next_release_at ? `Held ${revenue.data.hold_days} days for refunds · next release ${formatDate(revenue.data.next_release_at)}` : `Held ${revenue.data.hold_days} days for refunds`}
              />
              <Stat label="In a payout" value={revenue.data.balances.in_payout} hint="Being processed by our finance team" />
              <Stat label="Paid out" value={revenue.data.balances.paid} hint={`Lifetime earnings ${formatPaise(revenue.data.balances.lifetime, true)}`} />
            </section>

            <section className="rounded-md border border-line bg-white p-6">
              <h2 className="font-sans text-base font-bold text-ink">Earnings by month</h2>
              <MonthlyChart data={revenue.data.monthly} />
            </section>

            <section className="rounded-md border border-line bg-white">
              <h2 className="border-b border-line px-6 py-4 font-sans text-base font-bold text-ink">By course</h2>
              {revenue.data.courses.length === 0 ? (
                <p className="px-6 py-10 text-center text-sm text-stone-500">No sales yet. Earnings appear here as learners buy your courses.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="text-xs font-semibold tracking-wide text-stone-500 uppercase">
                      <tr>
                        <th className="px-6 py-3">Course</th>
                        <th className="px-6 py-3 text-right">Sales</th>
                        <th className="px-6 py-3 text-right">Refunds</th>
                        <th className="px-6 py-3 text-right">Your earnings</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                      {revenue.data.courses.map((c) => (
                        <tr key={c.slug}>
                          <td className="px-6 py-3 font-semibold text-ink">{c.title}</td>
                          <td className="tabular px-6 py-3 text-right text-stone-700">{c.sales}</td>
                          <td className="tabular px-6 py-3 text-right text-stone-700">{c.refunds}</td>
                          <td className="tabular px-6 py-3 text-right font-semibold text-ink">{formatPaise(c.earnings, true)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            <History enabled={enabled} tds={revenue.data.tds} />
          </>
        )}
      </div>
    </>
  );
}

function Stat({ label, value, hint, strong }: { label: string; value: number; hint: string; strong?: boolean }) {
  return (
    <div className={cn("rounded-md border p-5", strong ? "border-navy-900 bg-navy-950 text-white" : "border-line bg-white")}>
      <p className={cn("text-xs font-semibold tracking-wide uppercase", strong ? "text-white/60" : "text-stone-500")}>{label}</p>
      <p className={cn("tabular mt-2 font-serif text-3xl font-semibold", strong ? "text-white" : "text-navy-900")}>{formatPaise(value, true)}</p>
      <p className={cn("mt-1 text-xs", strong ? "text-white/60" : "text-stone-500")}>{hint}</p>
    </div>
  );
}

/** Twelve bars, scaled to the best month. Negative months (refunds > sales) show as empty. */
function MonthlyChart({ data }: { data: { month: string; earnings: number }[] }) {
  const max = Math.max(1, ...data.map((d) => d.earnings));
  return (
    <div className="mt-6">
      <ol className="flex h-44 items-end gap-2" aria-label="Monthly earnings, last 12 months">
        {data.map((d) => (
          <li key={d.month} className="group flex h-full flex-1 flex-col justify-end">
            <span className="sr-only">
              {monthLabel(d.month)}: {formatPaise(d.earnings, true)}
            </span>
            <div
              aria-hidden
              title={`${monthLabel(d.month)} · ${formatPaise(d.earnings, true)}`}
              className="w-full rounded-t-sm bg-navy-900 transition-colors group-hover:bg-gold-500"
              style={{ height: `${Math.max(0, (d.earnings / max) * 100)}%`, minHeight: d.earnings > 0 ? 3 : 0 }}
            />
          </li>
        ))}
      </ol>
      <ol className="mt-2 flex gap-2 border-t border-line pt-2" aria-hidden>
        {data.map((d) => (
          <li key={d.month} className="flex-1 text-center text-[0.65rem] text-stone-500">
            {monthLabel(d.month)}
          </li>
        ))}
      </ol>
    </div>
  );
}

function History({ enabled, tds }: { enabled: boolean; tds: { section: string; rate_bps: number } }) {
  const [tab, setTab] = useState<"statement" | "payouts">("statement");
  const [page, setPage] = useState(1);
  const statement = useQuery({
    queryKey: ["instructor", "statement", page],
    enabled: enabled && tab === "statement",
    queryFn: () => unwrap(api.GET("/instructor/statement", { params: { query: { page } } })),
  });
  const payouts = useQuery({ queryKey: ["instructor", "payouts"], enabled: enabled && tab === "payouts", queryFn: async () => (await unwrap(api.GET("/instructor/payouts"))).data });
  const meta = statement.data?.meta as { current_page: number; last_page: number } | undefined;

  return (
    <section className="rounded-md border border-line bg-white">
      <div role="tablist" className="flex gap-6 border-b border-line px-6">
        {(["statement", "payouts"] as const).map((t) => (
          <button
            key={t}
            role="tab"
            aria-selected={tab === t}
            onClick={() => setTab(t)}
            className={cn("-mb-px border-b-2 py-4 text-sm font-semibold capitalize", tab === t ? "border-gold-500 text-navy-900" : "border-transparent text-stone-500 hover:text-navy-900")}
          >
            {t}
          </button>
        ))}
      </div>

      {tab === "statement" ? (
        statement.isPending ? (
          <Skeleton className="m-6 h-32" />
        ) : (statement.data?.data ?? []).length === 0 ? (
          <p className="px-6 py-10 text-center text-sm text-stone-500">Nothing here yet.</p>
        ) : (
          <>
            <ul className="divide-y divide-line">
              {statement.data!.data.map((l) => (
                <li key={l.id} className="flex items-center gap-4 px-6 py-3 text-sm">
                  <span className="tabular w-24 shrink-0 text-xs text-stone-500">{formatDate(l.created_at)}</span>
                  <span className="min-w-0 flex-1 truncate text-stone-700">{l.description}</span>
                  <StatusPill status={l.status === "pending" ? "on hold" : l.status} />
                  <span className={cn("tabular w-24 text-right font-semibold", l.amount < 0 ? "text-destructive" : "text-ink")}>{formatPaise(l.amount, true)}</span>
                </li>
              ))}
            </ul>
            {meta && meta.last_page > 1 && (
              <div className="flex items-center justify-end gap-3 border-t border-line px-6 py-3 text-sm">
                <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  Newer
                </Button>
                <Button size="sm" variant="outline" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
                  Older
                </Button>
              </div>
            )}
          </>
        )
      ) : payouts.isPending ? (
        <Skeleton className="m-6 h-32" />
      ) : (payouts.data ?? []).length === 0 ? (
        <p className="px-6 py-10 text-center text-sm text-stone-500">No payouts yet. We pay available earnings at the start of each month.</p>
      ) : (
        <>
          <ul className="divide-y divide-line">
            {payouts.data!.map((p) => (
              <li key={p.id} className="flex flex-wrap items-center gap-4 px-6 py-4 text-sm">
                <div className="min-w-0 flex-1">
                  <p className="font-semibold text-ink">{p.paid_at ? `Paid ${formatDate(p.paid_at)}` : "Approved — transfer in progress"}</p>
                  <p className="text-xs text-stone-500">
                    To {p.bank.account_holder} · account ending {p.bank.account_last4}
                    {p.reference && <> · UTR {p.reference}</>}
                  </p>
                </div>
                <div className="text-right text-xs text-stone-500">
                  <p className="tabular font-serif text-lg font-semibold text-navy-900">{formatPaise(p.net, true)}</p>
                  {formatPaise(p.gross, true)} − TDS {formatPaise(p.tds, true)}
                </div>
              </li>
            ))}
          </ul>
          <p className="flex items-start gap-2 border-t border-line px-6 py-3 text-xs text-stone-500">
            <CircleAlert className="mt-0.5 size-3.5 shrink-0" /> TDS is withheld under section {tds.section} ({tds.rate_bps / 100}%) and reported against your PAN; it appears in your Form 26AS.
          </p>
        </>
      )}
    </section>
  );
}
