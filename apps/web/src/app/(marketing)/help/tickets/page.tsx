"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ChevronRight, LifeBuoy } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { formatDateTime, TicketStatus } from "@/components/help/ticket-parts";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, unwrap } from "@/lib/api/client";

export default function MyTicketsPage() {
  return <RequireAuth>{() => <MyTickets />}</RequireAuth>;
}

function MyTickets() {
  const list = useQuery({
    queryKey: ["support", "tickets"],
    queryFn: async () => (await unwrap(api.GET("/me/support/tickets"))).data.items,
  });

  return (
    <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
      <nav aria-label="Breadcrumb" className="text-sm text-stone-500">
        <Link href="/help" className="hover:text-navy-800">
          Help Centre
        </Link>{" "}
        / My tickets
      </nav>
      <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-h1">My support tickets</h1>
          <p className="mt-1 text-stone-600">Follow your requests and reply to our support team.</p>
        </div>
        <Link href="/help/tickets/new" className={buttonVariants()}>
          <LifeBuoy /> Raise a ticket
        </Link>
      </div>

      <div className="mt-8">
        {list.isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load your tickets. Please refresh the page.</FormAlert>
        ) : list.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-4 py-14 text-center">
            <p className="font-semibold text-ink">You haven&rsquo;t raised any tickets.</p>
            <p className="mt-1 text-sm text-stone-600">
              Try the{" "}
              <Link href="/help" className="font-semibold text-navy-800 underline underline-offset-2">
                help articles
              </Link>{" "}
              first. Many questions are answered there.
            </p>
          </div>
        ) : (
          <ul className="divide-y divide-line overflow-hidden rounded-md border border-line bg-white">
            {list.data.map((t) => (
              <li key={t.id}>
                <Link
                  href={`/help/tickets/${t.id}`}
                  className="flex items-center gap-4 px-4 py-4 hover:bg-paper-2 sm:px-5"
                >
                  <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2">
                      <span className="truncate font-semibold text-ink">{t.subject}</span>
                      <TicketStatus status={t.status} label={t.status_label} />
                    </p>
                    <p className="mt-1 text-sm text-stone-500">
                      <span className="tabular">{t.number}</span> · {t.category_label} · Updated{" "}
                      {formatDateTime(t.last_activity_at)}
                    </p>
                  </div>
                  <ChevronRight className="size-4 shrink-0 text-stone-400" aria-hidden />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
