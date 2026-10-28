"use client";

import Link from "next/link";
import { useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import type { paths } from "@erudsway/api-client";
import { ChevronRight, Search } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { formatDateTime, TicketStatus } from "@/components/help/ticket-parts";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, unwrap } from "@/lib/api/client";

type Query = NonNullable<paths["/admin/support/tickets"]["get"]["parameters"]["query"]>;

const statusFilters: { value: string; label: string }[] = [
  { value: "active", label: "Needs attention" },
  { value: "waiting_learner", label: "Waiting for learner" },
  { value: "resolved", label: "Resolved" },
  { value: "closed", label: "Closed" },
  { value: "", label: "All" },
];

const selectClass = "h-9 rounded-md border border-input bg-white px-2 text-sm";

export default function SupportQueuePage() {
  return <RequireAuth permission="handle_support">{() => <Queue />}</RequireAuth>;
}

function Queue() {
  const [status, setStatus] = useState("active");
  const [category, setCategory] = useState("");
  const [assigned, setAssigned] = useState("");
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);

  const options = useQuery({
    queryKey: ["admin", "support", "options"],
    queryFn: async () => (await unwrap(api.GET("/admin/support/options"))).data,
    staleTime: 5 * 60_000,
  });
  const list = useQuery({
    queryKey: ["admin", "support", "tickets", status, category, assigned, q, page],
    queryFn: async () => {
      const query = {
        ...(status ? { status } : {}),
        ...(category ? { category } : {}),
        ...(assigned ? { assigned } : {}),
        ...(q ? { q } : {}),
        page,
      } as Query;
      return (await unwrap(api.GET("/admin/support/tickets", { params: { query } }))).data;
    },
    placeholderData: keepPreviousData,
  });
  const data = list.data;
  const active = (data?.counts.open ?? 0) + (data?.counts.in_progress ?? 0);

  return (
    <>
      <AdminPageHeader
        title="Support tickets"
        description={data ? `${active} need attention · from the Help Centre` : "Tickets raised from the Help Centre."}
      />
      <div className="px-4 py-6 sm:px-8">
        <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Show tickets">
          {statusFilters.map((f) => (
            <Button
              key={f.label}
              size="sm"
              variant={status === f.value ? "default" : "outline"}
              aria-pressed={status === f.value}
              onClick={() => {
                setStatus(f.value);
                setPage(1);
              }}
            >
              {f.label}
            </Button>
          ))}
        </div>
        <div className="mb-5 flex flex-wrap items-center gap-2">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              setQ(search.trim());
              setPage(1);
            }}
            className="relative"
          >
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-stone-400" />
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Subject, number or learner"
              aria-label="Search tickets"
              className="h-9 w-64 rounded-md border border-input bg-white pr-2 pl-8 text-sm"
            />
          </form>
          <select
            aria-label="Topic"
            value={category}
            onChange={(e) => {
              setCategory(e.target.value);
              setPage(1);
            }}
            className={selectClass}
          >
            <option value="">All topics</option>
            {(options.data?.categories ?? []).map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>
          <select
            aria-label="Assignee"
            value={assigned}
            onChange={(e) => {
              setAssigned(e.target.value);
              setPage(1);
            }}
            className={selectClass}
          >
            <option value="">Anyone</option>
            <option value="me">Assigned to me</option>
            <option value="unassigned">Unassigned</option>
          </select>
        </div>

        {list.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : list.isError || !data ? (
          <FormAlert>Couldn&rsquo;t load tickets.</FormAlert>
        ) : data.items.length === 0 ? (
          <p className="rounded-md border border-dashed border-line bg-white px-4 py-12 text-center text-sm text-stone-500">
            {status === "active" ? "Nothing needs attention. You’re all caught up." : "No tickets here."}
          </p>
        ) : (
          <ul className="divide-y divide-line overflow-hidden rounded-md border border-line bg-white">
            {data.items.map((t) => (
              <li key={t.id}>
                <Link href={`/admin/support/${t.id}`} className="flex items-center gap-4 px-4 py-3.5 hover:bg-paper-2">
                  <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2">
                      <span className="truncate font-semibold text-ink">{t.subject}</span>
                      <TicketStatus status={t.status} label={t.status_label} />
                    </p>
                    <p className="mt-1 text-sm text-stone-500">
                      <span className="tabular">{t.number}</span> · {t.learner.name} · {t.category_label}
                      {t.course && <> · {t.course.title}</>} · {t.assignee ? t.assignee.name : "Unassigned"} ·{" "}
                      {formatDateTime(t.last_activity_at)}
                    </p>
                  </div>
                  <ChevronRight className="size-4 shrink-0 text-stone-400" aria-hidden />
                </Link>
              </li>
            ))}
          </ul>
        )}
        {data && data.last_page > 1 && (
          <div className="mt-4 flex items-center justify-end gap-3 text-sm">
            <span className="tabular text-stone-500">
              Page {data.current_page} of {data.last_page}
            </span>
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button size="sm" variant="outline" disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        )}
      </div>
    </>
  );
}
