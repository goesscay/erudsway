"use client";

import { useState } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Mail, Phone, RotateCcw } from "lucide-react";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";

type Filter = "new" | "handled" | "";

const filters: { value: Filter; label: string }[] = [
  { value: "new", label: "New" },
  { value: "handled", label: "Handled" },
  { value: "", label: "All" },
];

export default function MessagesPage() {
  return <RequireAuth permission="manage_users">{() => <Messages />}</RequireAuth>;
}

function Messages() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<Filter>("new");
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const list = useQuery({
    queryKey: ["admin", "contact-messages", status, page],
    queryFn: async () =>
      (await unwrap(api.GET("/admin/contact-messages", { params: { query: { ...(status ? { status } : {}), page } } })))
        .data,
    placeholderData: keepPreviousData,
  });
  const update = useMutation({
    mutationFn: ({ id, next }: { id: string; next: "new" | "handled" }) =>
      mutate(() =>
        api.PATCH("/admin/contact-messages/{message}", { params: { path: { message: id } }, body: { status: next } }),
      ),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "contact-messages"] }),
    onError: (e) => setError(e instanceof ApiRequestError ? e.message : "Couldn’t update the message."),
  });
  const data = list.data;

  return (
    <>
      <AdminPageHeader
        title="Messages"
        description={
          data ? `${data.new_count} new · from the website’s Contact page` : "Messages from the website’s Contact page."
        }
      />
      <div className="px-4 py-6 sm:px-8">
        <div className="mb-5 flex flex-wrap gap-2" role="group" aria-label="Show messages">
          {filters.map((f) => (
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
        {error && <FormAlert>{error}</FormAlert>}
        {list.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : list.isError || !data ? (
          <FormAlert>Couldn&rsquo;t load messages.</FormAlert>
        ) : data.items.length === 0 ? (
          <p className="rounded-md border border-dashed border-line bg-white px-4 py-12 text-center text-sm text-stone-500">
            {status === "new" ? "No new messages. You’re all caught up." : "No messages here."}
          </p>
        ) : (
          <ul className="space-y-3">
            {data.items.map((m) => (
              <li key={m.id} className="rounded-md border border-line bg-white p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-2 font-semibold text-ink">
                      {m.name}
                      <StatusPill status={m.status} />
                      <span className="rounded-sm bg-navy-100 px-1.5 py-0.5 text-[0.7rem] font-semibold text-navy-800">
                        {m.topic_label}
                      </span>
                    </p>
                    <p className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-stone-600">
                      <a
                        href={`mailto:${m.email}?subject=${encodeURIComponent(`Re: your message to Erudsway (${m.topic_label})`)}`}
                        className="inline-flex items-center gap-1 text-navy-800 hover:underline"
                      >
                        <Mail className="size-3.5" aria-hidden /> {m.email}
                      </a>
                      {m.phone && (
                        <a
                          href={`tel:${m.phone.replace(/\s/g, "")}`}
                          className="inline-flex items-center gap-1 hover:underline"
                        >
                          <Phone className="size-3.5" aria-hidden /> {m.phone}
                        </a>
                      )}
                      <span className="tabular">{formatDate(m.created_at)}</span>
                    </p>
                  </div>
                  {m.status === "new" ? (
                    <Button
                      size="sm"
                      onClick={() => update.mutate({ id: m.id, next: "handled" })}
                      disabled={update.isPending}
                    >
                      <Check /> Mark handled
                    </Button>
                  ) : (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => update.mutate({ id: m.id, next: "new" })}
                      disabled={update.isPending}
                    >
                      <RotateCcw /> Reopen
                    </Button>
                  )}
                </div>
                <p className="mt-3 text-sm leading-relaxed whitespace-pre-line text-stone-800">{m.message}</p>
                {m.status === "handled" && (
                  <p className="mt-3 text-xs text-stone-500">
                    Handled{m.handled_by ? ` by ${m.handled_by}` : ""} on {formatDate(m.handled_at)}
                  </p>
                )}
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
