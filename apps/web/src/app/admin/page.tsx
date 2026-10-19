"use client";

import Link from "next/link";
import { useState } from "react";
import { ArrowDownRight, ArrowRight, ArrowUpRight, CircleCheck } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/page-header";
import { DateRangePicker, presetRange } from "@/components/admin/date-range";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Skeleton } from "@/components/ui/skeleton";
import { useOverview, useQueues, type Range } from "@/lib/admin/api";
import { hasPermission, type CurrentUser } from "@/lib/auth/session";
import { formatCount, formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";

export default function AdminDashboardPage() {
  return <RequireAuth staff>{(me) => <Dashboard me={me} />}</RequireAuth>;
}

function Dashboard({ me }: { me: CurrentUser }) {
  const reports = hasPermission(me, "view_reports");
  const [range, setRange] = useState<Range>(() => presetRange("30d"));
  const queues = useQueues(true);
  const overview = useOverview(range, reports);

  return (
    <>
      <AdminPageHeader
        title={`Good ${greeting()}, ${me.name.split(" ")[0]}`}
        description="What needs doing, and how the marketplace is going."
      >
        {reports && <DateRangePicker value={range} onChange={setRange} />}
      </AdminPageHeader>

      <div className="space-y-8 px-4 py-6 sm:px-8">
        <section aria-labelledby="queues-title">
          <h2 id="queues-title" className="mb-3 text-sm font-bold tracking-wide text-stone-500 uppercase">
            Waiting for you
          </h2>
          {queues.isPending ? (
            <Skeleton className="h-24 w-full" />
          ) : queues.isError ? (
            <FormAlert>Couldn&rsquo;t load the work queues.</FormAlert>
          ) : queues.data.every((q) => q.count === 0) ? (
            <p className="flex items-center gap-2 rounded-md border border-line bg-white px-5 py-4 text-sm text-stone-700">
              <CircleCheck className="size-5 text-success" /> All clear — nothing is waiting for you.
            </p>
          ) : (
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {queues.data.map((q) => (
                <li key={q.key}>
                  <Link
                    href={q.href}
                    className={cn(
                      "group flex items-center gap-4 rounded-md border bg-white px-5 py-4 hover:border-navy-900",
                      q.count > 0 ? "border-gold-500/60" : "border-line",
                    )}
                  >
                    <span
                      className={cn(
                        "tabular font-serif text-3xl font-semibold",
                        q.count > 0 ? "text-navy-900" : "text-stone-300",
                      )}
                    >
                      {q.count}
                    </span>
                    <span className="flex-1 text-sm text-stone-700">{q.label}</span>
                    <ArrowRight className="size-4 text-stone-300 group-hover:text-navy-900" />
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>

        {reports &&
          (overview.isPending ? (
            <Skeleton className="h-80 w-full" />
          ) : overview.isError ? (
            <FormAlert>Couldn&rsquo;t load the numbers.</FormAlert>
          ) : (
            <div className={cn("space-y-8", overview.isPlaceholderData && "opacity-60")}>
              <section aria-label="Key numbers" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {overview.data.kpis.map((k) => (
                  <Kpi
                    key={k.key}
                    label={k.label}
                    value={k.value}
                    previous={k.previous}
                    money={k.money}
                    inverse={k.key === "refunds"}
                  />
                ))}
              </section>

              <section className="rounded-md border border-line bg-white p-5 sm:p-6">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <h2 className="font-sans text-base font-bold text-ink">Revenue by day</h2>
                  <p className="text-xs text-stone-500">GST included, before refunds · IST days</p>
                </div>
                <DailyChart data={overview.data.daily} />
              </section>

              <section className="rounded-md border border-line bg-white">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                  <h2 className="font-sans text-base font-bold text-ink">Best-selling courses</h2>
                  <Link
                    href="/admin/reports?report=courses"
                    className="text-sm font-semibold text-navy-800 hover:underline"
                  >
                    All courses
                  </Link>
                </div>
                {overview.data.top_courses.length === 0 ? (
                  <p className="px-5 py-8 text-center text-sm text-stone-500">No sales in this period.</p>
                ) : (
                  <ol className="divide-y divide-line">
                    {overview.data.top_courses.map((c, i) => (
                      <li key={c.slug || i} className="flex items-center gap-4 px-5 py-3 text-sm">
                        <span className="tabular w-5 text-stone-500">{i + 1}</span>
                        <Link
                          href={`/course/${c.slug}`}
                          className="min-w-0 flex-1 truncate font-semibold text-ink hover:underline"
                        >
                          {c.title}
                        </Link>
                        <span className="tabular text-stone-500">{c.sales} sold</span>
                        <span className="tabular w-24 text-right font-semibold text-ink">{formatPaise(c.revenue)}</span>
                      </li>
                    ))}
                  </ol>
                )}
              </section>
            </div>
          ))}
      </div>
    </>
  );
}

function greeting() {
  const h = Number(
    new Intl.DateTimeFormat("en-IN", { hour: "numeric", hour12: false, timeZone: "Asia/Kolkata" }).format(new Date()),
  );
  return h < 12 ? "morning" : h < 17 ? "afternoon" : "evening";
}

function Kpi({
  label,
  value,
  previous,
  money,
  inverse,
}: {
  label: string;
  value: number;
  previous: number;
  money: boolean;
  inverse?: boolean;
}) {
  const change = previous === 0 ? (value === 0 ? 0 : null) : Math.round(((value - previous) / previous) * 100);
  const up = change !== null && change > 0;
  const good = change === null || change === 0 ? null : inverse ? !up : up;

  return (
    <div className="rounded-md border border-line bg-white px-5 py-4">
      <p className="text-xs font-semibold tracking-wide text-stone-500 uppercase">{label}</p>
      <p className="tabular mt-1 font-serif text-2xl font-semibold text-ink">
        {money ? formatPaise(value) : formatCount(value)}
      </p>
      <p
        className={cn(
          "mt-1 flex items-center gap-1 text-xs",
          good === null ? "text-stone-500" : good ? "text-success" : "text-destructive",
        )}
      >
        {change === null ? (
          "New this period"
        ) : change === 0 ? (
          "Same as the previous period"
        ) : (
          <>
            {up ? <ArrowUpRight className="size-3.5" /> : <ArrowDownRight className="size-3.5" />}
            {Math.abs(change)}% vs previous period
          </>
        )}
      </p>
    </div>
  );
}

function DailyChart({ data }: { data: { date: string; revenue: number; orders: number }[] }) {
  const max = Math.max(1, ...data.map((d) => d.revenue));
  const label = (d: string) =>
    new Date(`${d}T00:00:00`).toLocaleDateString("en-IN", { day: "numeric", month: "short" });
  const every = Math.ceil(data.length / 8);

  return (
    <div className="mt-5">
      <ol className="flex h-48 items-end gap-[2px]" aria-label="Revenue by day">
        {data.map((d) => (
          <li key={d.date} className="group relative flex h-full flex-1 flex-col justify-end">
            <span className="sr-only">
              {label(d.date)}: {formatPaise(d.revenue)}, {d.orders} orders
            </span>
            <div
              aria-hidden
              title={`${label(d.date)} · ${formatPaise(d.revenue)} · ${d.orders} orders`}
              className="w-full rounded-t-[2px] bg-navy-900 transition-colors group-hover:bg-gold-500"
              style={{ height: `${(d.revenue / max) * 100}%`, minHeight: d.revenue > 0 ? 3 : 0 }}
            />
          </li>
        ))}
      </ol>
      <ol className="mt-2 flex gap-[2px] border-t border-line pt-2" aria-hidden>
        {data.map((d, i) => (
          <li key={d.date} className="flex-1 text-center text-[0.65rem] whitespace-nowrap text-stone-500">
            {i % every === 0 ? label(d.date) : ""}
          </li>
        ))}
      </ol>
    </div>
  );
}
