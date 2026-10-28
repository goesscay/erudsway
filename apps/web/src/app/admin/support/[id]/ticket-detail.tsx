"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Lock, Mail } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { AttachmentPicker, Conversation, formatDateTime, TicketStatus } from "@/components/help/ticket-parts";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { checkFiles, postWithFiles } from "@/lib/help/api";
import { cn } from "@/lib/utils";

type Status = "open" | "in_progress" | "waiting_learner" | "resolved" | "closed";

const selectClass = "h-9 w-full rounded-md border border-input bg-white px-2 text-sm";

export function SupportTicketDetail({ id }: { id: string }) {
  return <RequireAuth permission="handle_support">{() => <Detail id={id} />}</RequireAuth>;
}

function Detail({ id }: { id: string }) {
  const queryClient = useQueryClient();
  const key = ["admin", "support", "ticket", id];
  const ticket = useQuery({
    queryKey: key,
    queryFn: async () =>
      (await unwrap(api.GET("/admin/support/tickets/{ticket}", { params: { path: { ticket: id } } }))).data,
  });
  const options = useQuery({
    queryKey: ["admin", "support", "options"],
    queryFn: async () => (await unwrap(api.GET("/admin/support/options"))).data,
    staleTime: 5 * 60_000,
  });
  const [body, setBody] = useState("");
  const [internal, setInternal] = useState(false);
  const [nextStatus, setNextStatus] = useState<Status | "">("");
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin", "support"] });

  const update = async (body: { status?: Status; assigned_to?: number | null }) => {
    setError(null);
    setBusy(true);
    try {
      await mutate(() => api.PATCH("/admin/support/tickets/{ticket}", { params: { path: { ticket: id } }, body }));
      await refresh();
    } catch (e) {
      setError(
        e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Couldn’t update the ticket.",
      );
    } finally {
      setBusy(false);
    }
  };

  const send = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);
    if (body.trim().length < 2) return setError("Write a message first.");
    const fileProblem = checkFiles(files);
    if (fileProblem) return setError(fileProblem);
    setBusy(true);
    try {
      await postWithFiles(
        `/admin/support/tickets/${id}/replies`,
        { message: body.trim(), internal, status: nextStatus || null },
        files,
      );
      setBody("");
      setFiles([]);
      setNextStatus("");
      await refresh();
    } catch (e) {
      setError(
        e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Couldn’t send the message.",
      );
    } finally {
      setBusy(false);
    }
  };

  if (ticket.isPending) return <Skeleton className="m-8 h-96" />;
  if (ticket.isError) {
    return (
      <div className="p-8">
        <FormAlert>Couldn&rsquo;t load this ticket.</FormAlert>
      </div>
    );
  }
  const t = ticket.data;
  const statuses = options.data?.statuses ?? [];

  return (
    <>
      <AdminPageHeader title={t.subject} description={`${t.number} · ${t.category_label}`}>
        <Link
          href="/admin/support"
          className="inline-flex items-center gap-1 text-sm font-semibold text-navy-800 hover:underline"
        >
          <ArrowLeft className="size-4" /> All tickets
        </Link>
      </AdminPageHeader>
      <div className="grid gap-6 px-4 py-6 sm:px-8 xl:grid-cols-[1fr_320px]">
        <div className="min-w-0 space-y-6">
          {error && <FormAlert>{error}</FormAlert>}
          <Conversation messages={t.messages} />
          <form onSubmit={send} className="space-y-4 rounded-md border border-line bg-white p-5">
            <div className="flex gap-2" role="group" aria-label="Message type">
              <Button
                type="button"
                size="sm"
                variant={internal ? "outline" : "default"}
                aria-pressed={!internal}
                onClick={() => setInternal(false)}
              >
                <Mail /> Reply to learner
              </Button>
              <Button
                type="button"
                size="sm"
                variant={internal ? "default" : "outline"}
                aria-pressed={internal}
                onClick={() => setInternal(true)}
              >
                <Lock /> Internal note
              </Button>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="staff-reply" className="sr-only">
                Message
              </Label>
              <Textarea
                id="staff-reply"
                rows={6}
                value={body}
                onChange={(e) => setBody(e.target.value)}
                maxLength={5000}
                placeholder={internal ? "Only staff can see this note." : "The learner is emailed this reply."}
                className={cn("bg-white", internal && "border-dashed border-gold-400 bg-gold-50")}
              />
            </div>
            <AttachmentPicker files={files} onChange={setFiles} />
            <div className="flex flex-wrap items-center gap-3">
              <label className="flex items-center gap-2 text-sm text-stone-700">
                Then set status
                <select
                  value={nextStatus}
                  onChange={(e) => setNextStatus(e.target.value as Status | "")}
                  className="h-9 rounded-md border border-input bg-white px-2 text-sm"
                >
                  <option value="">{internal ? "Leave as it is" : "Waiting for learner (default)"}</option>
                  {statuses.map((s) => (
                    <option key={s.value} value={s.value}>
                      {s.label}
                    </option>
                  ))}
                </select>
              </label>
              <Button type="submit" disabled={busy}>
                {busy ? "Sending…" : internal ? "Add note" : "Send reply"}
              </Button>
            </div>
          </form>
        </div>

        <aside className="space-y-4">
          <div className="space-y-4 rounded-md border border-line bg-white p-5 text-sm">
            <div>
              <p className="text-xs font-bold tracking-wide text-stone-500 uppercase">Status</p>
              <div className="mt-2 flex items-center gap-2">
                <TicketStatus status={t.status} label={t.status_label} />
              </div>
              <select
                aria-label="Change status"
                value={t.status}
                disabled={busy}
                onChange={(e) => update({ status: e.target.value as Status })}
                className={cn(selectClass, "mt-2")}
              >
                {statuses.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <p className="text-xs font-bold tracking-wide text-stone-500 uppercase">Assigned to</p>
              <select
                aria-label="Assign to"
                value={t.assignee?.id ?? ""}
                disabled={busy}
                onChange={(e) => update({ assigned_to: e.target.value ? Number(e.target.value) : null })}
                className={cn(selectClass, "mt-2")}
              >
                <option value="">Unassigned</option>
                {(options.data?.staff ?? []).map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <dl className="space-y-3 rounded-md border border-line bg-white p-5 text-sm">
            <div>
              <dt className="text-xs font-bold tracking-wide text-stone-500 uppercase">Learner</dt>
              <dd className="mt-1 font-semibold text-ink">{t.learner.name}</dd>
              <dd>
                <a href={`mailto:${t.learner.email}`} className="text-navy-800 hover:underline">
                  {t.learner.email}
                </a>
              </dd>
            </div>
            {t.course && (
              <div>
                <dt className="text-xs font-bold tracking-wide text-stone-500 uppercase">Course</dt>
                <dd className="mt-1">{t.course.title}</dd>
              </div>
            )}
            {t.module && (
              <div>
                <dt className="text-xs font-bold tracking-wide text-stone-500 uppercase">Lesson / screen</dt>
                <dd className="mt-1">{t.module}</dd>
              </div>
            )}
            {t.order_reference && (
              <div>
                <dt className="text-xs font-bold tracking-wide text-stone-500 uppercase">Order reference</dt>
                <dd className="tabular mt-1">{t.order_reference}</dd>
              </div>
            )}
            <div>
              <dt className="text-xs font-bold tracking-wide text-stone-500 uppercase">Opened</dt>
              <dd className="mt-1">{formatDateTime(t.created_at)}</dd>
            </div>
          </dl>
        </aside>
      </div>
    </>
  );
}
