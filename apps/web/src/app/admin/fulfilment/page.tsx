"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";

type Task = components["schemas"]["FulfilmentTaskResource"];

const statusLabel: Record<Task["status"], string> = {
  pending: "pending",
  awaiting_learner: "waiting for learner",
  requested: "requested",
  scheduled: "scheduled",
  done: "done",
  failed: "failed",
  cancelled: "cancelled",
};

const key = ["admin", "fulfilment"];

export default function FulfilmentPage() {
  return <RequireAuth permission="manage_fulfilment">{() => <Queue />}</RequireAuth>;
}

function Queue() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<"open" | "done" | "all">("open");
  const [page, setPage] = useState(1);
  const [acting, setActing] = useState<{ task: Task; action: "scheduled" | "done" | "cancelled" } | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const list = useQuery({
    queryKey: [...key, status, page],
    queryFn: () => unwrap(api.GET("/admin/fulfilment", { params: { query: { status, page } } })),
    placeholderData: (prev) => prev,
  });
  const rows = list.data?.data ?? [];
  const meta = (list.data as { meta?: { total: number; last_page: number } } | undefined)?.meta;

  const retry = async (task: Task) => {
    setNotice(null);
    try {
      await mutate(() => api.POST("/admin/fulfilment/{task}/retry", { params: { path: { task: task.id } } }));
      setNotice(`Creating ${task.learner.name}’s simulator login again.`);
      await queryClient.invalidateQueries({ queryKey: key });
    } catch (e) {
      setNotice(e instanceof ApiRequestError ? e.message : "Couldn’t retry.");
    }
  };

  return (
    <>
      <AdminPageHeader title="Fulfilment" description="What editions promise beyond the course: simulator logins (created automatically) and faculty doubt sessions." />
      <div className="px-8 py-6">
        <div className="mb-5 flex flex-wrap gap-2" role="group" aria-label="Show">
          {(["open", "done", "all"] as const).map((s) => (
            <Button
              key={s}
              size="sm"
              variant={status === s ? "default" : "outline"}
              aria-pressed={status === s}
              onClick={() => {
                setStatus(s);
                setPage(1);
              }}
            >
              {s === "open" ? "Needs attention" : s === "done" ? "Done" : "All"}
            </Button>
          ))}
        </div>
        {notice && (
          <p role="status" className="mb-4 rounded-md border border-line bg-white px-4 py-3 text-sm text-stone-700">
            {notice}
          </p>
        )}
        {list.isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load the queue.</FormAlert>
        ) : rows.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">Nothing here.</div>
        ) : (
          <ul className="space-y-3">
            {rows.map((t) => (
              <li key={t.id} className="rounded-md border border-line bg-white p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="font-semibold text-ink">
                      {t.type_label} <span className="font-normal text-stone-500">· {t.course.title}</span>
                    </p>
                    <p className="text-sm text-stone-700">
                      {t.learner.name} · {t.learner.email}
                      {t.edition && <span className="text-stone-500"> · {t.edition}</span>}
                    </p>
                    <p className="mt-1 text-xs text-stone-500">Opened {formatDate(t.created_at)}</p>
                  </div>
                  <StatusPill status={statusLabel[t.status]} />
                </div>

                {t.type === "simulator_access" && (
                  <div className="mt-3 text-sm text-stone-700">
                    {t.simulator_username && (
                      <p>
                        Simulator username <span className="font-mono text-ink">{t.simulator_username}</span>
                      </p>
                    )}
                    {t.last_error && <p className="text-destructive">Last attempt: {t.last_error}</p>}
                    {t.status !== "done" && t.status !== "cancelled" && (
                      <Button size="sm" variant="outline" className="mt-2" onClick={() => void retry(t)}>
                        Retry now
                      </Button>
                    )}
                  </div>
                )}

                {t.type === "doubt_session" && (
                  <div className="mt-3 space-y-2 text-sm text-stone-700">
                    {t.status === "awaiting_learner" && <p className="text-stone-500">The learner hasn&rsquo;t sent their preferred times yet.</p>}
                    {t.preferred_times && (
                      <p>
                        <span className="text-stone-500">Prefers:</span> {t.preferred_times}
                      </p>
                    )}
                    {t.questions && <p className="whitespace-pre-line rounded-sm bg-paper px-3 py-2">{t.questions}</p>}
                    {t.phone && (
                      <p>
                        <span className="text-stone-500">Phone:</span> {t.phone}
                      </p>
                    )}
                    {t.scheduled_for && (
                      <p>
                        <span className="text-stone-500">Scheduled:</span>{" "}
                        {new Date(t.scheduled_for).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" })} IST
                      </p>
                    )}
                    {t.staff_notes && <p className="text-stone-500">Notes: {t.staff_notes}</p>}
                    {t.status !== "done" && t.status !== "cancelled" && (
                      <div className="flex flex-wrap gap-2 pt-1">
                        <Button size="sm" onClick={() => setActing({ task: t, action: "scheduled" })}>
                          {t.status === "scheduled" ? "Reschedule" : "Schedule"}
                        </Button>
                        {t.status === "scheduled" && (
                          <Button size="sm" variant="outline" onClick={() => setActing({ task: t, action: "done" })}>
                            Mark done
                          </Button>
                        )}
                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setActing({ task: t, action: "cancelled" })}>
                          Cancel
                        </Button>
                      </div>
                    )}
                  </div>
                )}
              </li>
            ))}
          </ul>
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
      <ActDialog acting={acting} onClose={() => setActing(null)} onDone={setNotice} />
    </>
  );
}

function ActDialog({
  acting,
  onClose,
  onDone,
}: {
  acting: { task: Task; action: "scheduled" | "done" | "cancelled" } | null;
  onClose: () => void;
  onDone: (msg: string) => void;
}) {
  const queryClient = useQueryClient();
  const [when, setWhen] = useState("");
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const close = () => {
    setWhen("");
    setNotes("");
    setError(null);
    onClose();
  };
  const submit = async () => {
    if (!acting) return;
    setBusy(true);
    setError(null);
    try {
      await mutate(() =>
        api.PATCH("/admin/fulfilment/{task}", {
          params: { path: { task: acting.task.id } },
          body: {
            status: acting.action,
            scheduled_for: acting.action === "scheduled" && when ? new Date(`${when}:00+05:30`).toISOString() : null,
            staff_notes: notes.trim() || null,
          },
        }),
      );
      await queryClient.invalidateQueries({ queryKey: key });
      onDone(acting.action === "scheduled" ? `Scheduled — ${acting.task.learner.name} has been emailed.` : "Updated.");
      close();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  const title = acting?.action === "scheduled" ? "Schedule the doubt session" : acting?.action === "done" ? "Mark as done" : "Cancel this task";

  return (
    <Dialog open={!!acting} onOpenChange={(o) => !o && close()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>
            {acting && `${acting.task.learner.name} · ${acting.task.course.title}`}
            {acting?.action === "scheduled" && " — the learner is emailed the time and your notes."}
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <div className="space-y-4">
          {acting?.action === "scheduled" && (
            <div className="space-y-1.5">
              <Label htmlFor="act-when">Date and time (IST)</Label>
              <Input id="act-when" type="datetime-local" value={when} onChange={(e) => setWhen(e.target.value)} />
            </div>
          )}
          <div className="space-y-1.5">
            <Label htmlFor="act-notes">{acting?.action === "scheduled" ? "Notes for the learner (meeting link, faculty name…)" : "Notes (optional)"}</Label>
            <Textarea id="act-notes" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={close}>
            Back
          </Button>
          <Button onClick={submit} disabled={busy || (acting?.action === "scheduled" && !when)}>
            {busy ? "Saving…" : acting?.action === "scheduled" ? "Schedule and email" : "Confirm"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
