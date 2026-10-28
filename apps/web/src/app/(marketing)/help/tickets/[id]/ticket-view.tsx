"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2 } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { AttachmentPicker, Conversation, formatDateTime, TicketStatus } from "@/components/help/ticket-parts";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { checkFiles, postWithFiles } from "@/lib/help/api";

export function TicketView({ id }: { id: string }) {
  return <RequireAuth>{() => <Ticket id={id} />}</RequireAuth>;
}

function Ticket({ id }: { id: string }) {
  const queryClient = useQueryClient();
  const key = ["support", "ticket", id];
  const ticket = useQuery({
    queryKey: key,
    queryFn: async () =>
      (await unwrap(api.GET("/me/support/tickets/{ticket}", { params: { path: { ticket: id } } }))).data,
  });
  const [body, setBody] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [created] = useState(() => {
    try {
      const fresh = sessionStorage.getItem("support-ticket-created") === id;
      if (fresh) sessionStorage.removeItem("support-ticket-created");
      return fresh;
    } catch {
      return false;
    }
  });

  const refresh = () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: key }),
      queryClient.invalidateQueries({ queryKey: ["support", "tickets"] }),
    ]);

  const reply = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);
    if (body.trim().length < 2) return setError("Write a reply first.");
    const fileProblem = checkFiles(files);
    if (fileProblem) return setError(fileProblem);
    setBusy(true);
    try {
      await postWithFiles(`/me/support/tickets/${id}/replies`, { message: body.trim() }, files);
      setBody("");
      setFiles([]);
      await refresh();
    } catch (e) {
      setError(
        e instanceof ApiRequestError
          ? (Object.values(e.fields)[0]?.[0] ?? e.message)
          : "We couldn’t send your reply. Please try again.",
      );
    } finally {
      setBusy(false);
    }
  };

  const close = async () => {
    setBusy(true);
    try {
      await mutate(() => api.POST("/me/support/tickets/{ticket}/close", { params: { path: { ticket: id } } }));
      await refresh();
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t close the ticket.");
    } finally {
      setBusy(false);
    }
  };

  if (ticket.isPending) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-10">
        <Skeleton className="h-96 w-full" />
      </div>
    );
  }
  if (ticket.isError) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-10">
        <FormAlert>We couldn&rsquo;t find that ticket.</FormAlert>
        <Link href="/help/tickets" className="mt-4 inline-block font-semibold text-navy-800 hover:underline">
          Back to my tickets
        </Link>
      </div>
    );
  }
  const t = ticket.data;
  const closed = t.status === "closed";

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
      <nav aria-label="Breadcrumb" className="text-sm text-stone-500">
        <Link href="/help" className="hover:text-navy-800">
          Help Centre
        </Link>{" "}
        /{" "}
        <Link href="/help/tickets" className="hover:text-navy-800">
          My tickets
        </Link>{" "}
        / <span className="tabular">{t.number}</span>
      </nav>

      {created && (
        <FormAlert tone="success">
          <span className="flex items-start gap-2">
            <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden />
            Ticket {t.number} has reached our support team. We&rsquo;ll email you when we reply.
          </span>
        </FormAlert>
      )}

      <div className="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h1 className="text-h2 break-words">{t.subject}</h1>
          <p className="mt-2 flex flex-wrap items-center gap-2 text-sm text-stone-500">
            <TicketStatus status={t.status} label={t.status_label} />
            <span className="tabular">{t.number}</span>· {t.category_label}
            {t.course && <>· {t.course.title}</>}
            {t.module && <>· {t.module}</>}
          </p>
          <p className="mt-1 text-xs text-stone-500">
            Opened {formatDateTime(t.created_at)}
            {t.order_reference && <> · Reference {t.order_reference}</>}
          </p>
        </div>
        {!closed && (
          <Button variant="outline" onClick={close} disabled={busy}>
            My problem is solved — close ticket
          </Button>
        )}
      </div>

      <div className="mt-8">
        <Conversation messages={t.messages} />
      </div>

      {closed ? (
        <p className="mt-8 rounded-md border border-line bg-paper-2 px-4 py-4 text-sm text-stone-700">
          This ticket is closed. Need more help?{" "}
          <Link href="/help/tickets/new" className="font-semibold text-navy-800 underline underline-offset-2">
            Raise a new ticket
          </Link>
          .
        </p>
      ) : (
        <form onSubmit={reply} className="mt-8 space-y-4 rounded-md border border-line bg-white p-5">
          {error && <FormAlert>{error}</FormAlert>}
          <div className="space-y-1.5">
            <Label htmlFor="reply" className="text-sm font-semibold text-ink">
              Your reply
            </Label>
            <Textarea
              id="reply"
              rows={5}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              maxLength={5000}
              className="bg-white"
            />
            {t.status === "resolved" && (
              <p className="text-xs text-stone-500">Replying reopens this ticket for our team.</p>
            )}
          </div>
          <AttachmentPicker files={files} onChange={setFiles} />
          <Button type="submit" disabled={busy}>
            {busy ? "Sending…" : "Send reply"}
          </Button>
        </form>
      )}
    </div>
  );
}
