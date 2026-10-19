"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useMemo, useState } from "react";
import { ArrowDown, ArrowUp, Download } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/page-header";
import { DateRangePicker, presetRange } from "@/components/admin/date-range";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { reportCsvUrl, useReport, type Range, type ReportName } from "@/lib/admin/api";
import { formatCount, formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";

const reports: { name: ReportName; label: string; about: string }[] = [
  {
    name: "sales",
    label: "Sales",
    about: "What was collected and how it splits between GST, gateway fees, instructors and Erudsway.",
  },
  {
    name: "courses",
    label: "Courses",
    about: "Learners, completion, ratings, sales and refunds for every course that has been live.",
  },
  {
    name: "instructors",
    label: "Instructors",
    about: "Each instructor’s live courses, learners, sales and share for the period.",
  },
  {
    name: "refunds",
    label: "Refunds",
    about: "Every refund requested in the period, with how far the learner had got.",
  },
];

export default function ReportsPage() {
  return (
    <RequireAuth permission="view_reports">
      {() => (
        <Suspense fallback={<Skeleton className="m-8 h-96" />}>
          <Reports />
        </Suspense>
      )}
    </RequireAuth>
  );
}

function Reports() {
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams();
  const requested = search.get("report");
  const name: ReportName = reports.some((r) => r.name === requested) ? (requested as ReportName) : "sales";
  const [range, setRange] = useState<Range>(() => presetRange("30d"));
  const [group, setGroup] = useState<"day" | "month">("day");
  const report = useReport(name, range, group);
  const about = reports.find((r) => r.name === name)!.about;

  return (
    <>
      <AdminPageHeader title="Reports" description={about}>
        <a href={reportCsvUrl(name, range, group)} className={buttonVariants({ variant: "outline" })}>
          <Download /> Download CSV
        </a>
      </AdminPageHeader>

      <div className="space-y-5 px-4 py-6 sm:px-8">
        <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
          <div role="tablist" aria-label="Report" className="flex gap-1 rounded-md bg-paper-2 p-1">
            {reports.map((r) => (
              <button
                key={r.name}
                role="tab"
                type="button"
                aria-selected={r.name === name}
                onClick={() => router.replace(`${pathname}?report=${r.name}`, { scroll: false })}
                className={cn(
                  "h-8 rounded-sm px-3 text-sm font-semibold",
                  r.name === name ? "bg-white text-ink shadow-sm" : "text-stone-500 hover:text-ink",
                )}
              >
                {r.label}
              </button>
            ))}
          </div>
          <DateRangePicker value={range} onChange={setRange} />
          {name === "sales" && (
            <select
              aria-label="Group by"
              value={group}
              onChange={(e) => setGroup(e.target.value as "day" | "month")}
              className="h-9 rounded-md border border-input bg-white px-2.5 text-sm font-semibold"
            >
              <option value="day">By day</option>
              <option value="month">By month</option>
            </select>
          )}
        </div>

        {report.isPending ? (
          <Skeleton className="h-96 w-full" />
        ) : report.isError ? (
          <FormAlert>Couldn&rsquo;t load the report.</FormAlert>
        ) : (
          <ReportTable
            key={name}
            columns={report.data.columns}
            rows={report.data.rows}
            dim={report.isPlaceholderData}
            totals={name === "sales"}
          />
        )}
      </div>
    </>
  );
}

type Column = { key: string; label: string; type: "text" | "number" | "money" | "percent" | "date" };
type Row = Record<string, string | number | null>;

function format(v: string | number | null | undefined, type: Column["type"]): string {
  if (v === null || v === undefined || v === "") return "—";
  if (type === "money") return formatPaise(Number(v));
  if (type === "percent") return `${v}%`;
  if (type === "number") return Number.isInteger(v) ? formatCount(Number(v)) : String(v);
  if (type === "date") {
    const s = String(v);
    return s.length === 7
      ? new Date(`${s}-01T00:00:00`).toLocaleDateString("en-IN", { month: "short", year: "numeric" })
      : new Date(`${s}T00:00:00`).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
  }
  return String(v);
}

function ReportTable({
  columns,
  rows,
  dim,
  totals,
}: {
  columns: Column[];
  rows: Row[];
  dim: boolean;
  totals: boolean;
}) {
  const [sort, setSort] = useState<{ key: string; desc: boolean } | null>(null);
  const sorted = useMemo(() => {
    if (!sort) return rows;
    return [...rows].sort((a, b) => {
      const x = a[sort.key] ?? "";
      const y = b[sort.key] ?? "";
      const c = typeof x === "number" && typeof y === "number" ? x - y : String(x).localeCompare(String(y));
      return sort.desc ? -c : c;
    });
  }, [rows, sort]);

  if (rows.length === 0) {
    return (
      <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">
        Nothing in this period.
      </div>
    );
  }

  const sums = Object.fromEntries(
    columns
      .filter((c) => c.type === "money" || (c.type === "number" && c.key !== "rating"))
      .map((c) => [c.key, rows.reduce((t, r) => t + (Number(r[c.key]) || 0), 0)]),
  );

  return (
    <div className={cn("overflow-x-auto rounded-md border border-line bg-white", dim && "opacity-60")}>
      <table className="w-full text-left text-sm">
        <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
          <tr>
            {columns.map((c) => {
              const active = sort?.key === c.key;
              const numeric = c.type !== "text" && c.type !== "date";
              return (
                <th
                  key={c.key}
                  className={cn("px-4 py-3 whitespace-nowrap", numeric && "text-right")}
                  aria-sort={active ? (sort!.desc ? "descending" : "ascending") : undefined}
                >
                  <button
                    type="button"
                    onClick={() => setSort({ key: c.key, desc: active ? !sort!.desc : numeric })}
                    className={cn("inline-flex items-center gap-1 uppercase hover:text-ink", active && "text-ink")}
                  >
                    {c.label}
                    {active && (sort!.desc ? <ArrowDown className="size-3" /> : <ArrowUp className="size-3" />)}
                  </button>
                </th>
              );
            })}
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {sorted.map((r, i) => (
            <tr key={i} className="hover:bg-paper/60">
              {columns.map((c) => (
                <td
                  key={c.key}
                  className={cn(
                    "px-4 py-2.5",
                    c.type === "text" ? "max-w-[22rem] truncate" : "tabular whitespace-nowrap",
                    c.type !== "text" && c.type !== "date" && "text-right",
                    c.key === columns[0]!.key && "font-semibold text-ink",
                  )}
                  title={c.type === "text" ? String(r[c.key] ?? "") : undefined}
                >
                  {format(r[c.key], c.type)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        {totals && (
          <tfoot className="border-t-2 border-line bg-paper-2/40 font-semibold text-ink">
            <tr>
              {columns.map((c, i) => (
                <td
                  key={c.key}
                  className={cn(
                    "tabular px-4 py-3 whitespace-nowrap",
                    c.type !== "text" && c.type !== "date" && "text-right",
                  )}
                >
                  {i === 0 ? "Total" : c.key in sums ? format(sums[c.key]!, c.type) : ""}
                </td>
              ))}
            </tr>
          </tfoot>
        )}
      </table>
    </div>
  );
}
