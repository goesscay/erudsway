"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { ClipboardList, FileText, MessageSquareReply } from "lucide-react";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { ApiRequestError } from "@/lib/api/client";
import { reviewSubmission, useSubmissionInbox, type Submission } from "@/lib/assessment/api";
import { timeAgo } from "@/lib/format";
import { cn } from "@/lib/utils";

type Status = "submitted" | "reviewed" | "all";

export function AssignmentsView() {
  const search = useSearchParams();
  const highlight = Number(search.get("submission")) || null;
  const [status, setStatus] = useState<Status>("submitted");
  const [page, setPage] = useState(1);
  const list = useSubmissionInbox(status, page);

  return (
    <>
      <header className="border-b border-line bg-white px-4 py-6 sm:px-8">
        <h1 className="text-h2">Assignments</h1>
        <p className="mt-1 text-sm text-stone-500">
          What learners hand in for your assignment lectures. Feedback is sent to them by email and in their inbox.
        </p>
      </header>

      <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-8">
        <div className="flex gap-2" role="group" aria-label="Show">
          {(
            [
              ["submitted", "Needs feedback"],
              ["reviewed", "Reviewed"],
              ["all", "All"],
            ] as const
          ).map(([value, label]) => (
            <button
              key={value}
              type="button"
              aria-pressed={status === value}
              onClick={() => {
                setStatus(value);
                setPage(1);
              }}
              className={cn(
                "h-8 rounded-full border px-3.5 text-xs font-semibold",
                status === value
                  ? "border-navy-900 bg-navy-900 text-white"
                  : "border-line bg-white text-stone-700 hover:border-input",
              )}
            >
              {label}
            </button>
          ))}
        </div>

        {list.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load submissions.</FormAlert>
        ) : list.data.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
            <ClipboardList className="mx-auto size-8 text-stone-300" strokeWidth={1.5} />
            <p className="mt-3 text-sm text-stone-700">
              {status === "submitted" ? "Nothing waiting for feedback." : "No submissions here yet."}
            </p>
          </div>
        ) : (
          <>
            <ul className={cn("space-y-4", list.isPlaceholderData && "opacity-60")}>
              {list.data.data.map((s) => (
                <SubmissionCard key={s.id} submission={s} highlighted={s.id === highlight} />
              ))}
            </ul>
            {list.data.meta.last_page > 1 && (
              <div className="flex items-center justify-between text-sm">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                  Previous
                </Button>
                <span className="tabular text-stone-500">
                  Page {list.data.meta.current_page} of {list.data.meta.last_page}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= list.data.meta.last_page}
                  onClick={() => setPage(page + 1)}
                >
                  Next
                </Button>
              </div>
            )}
          </>
        )}
      </div>
    </>
  );
}

function SubmissionCard({ submission: s, highlighted }: { submission: Submission; highlighted: boolean }) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(highlighted && s.status === "submitted");
  const [feedback, setFeedback] = useState(s.feedback ?? "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const name = s.learner?.name ?? "Learner";

  const send = async () => {
    setBusy(true);
    setError(null);
    try {
      await reviewSubmission(s.id, feedback.trim());
      await queryClient.invalidateQueries({ queryKey: ["instructor", "submissions"] });
      setOpen(false);
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t send your feedback.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <li
      className={cn(
        "rounded-md border bg-white p-5",
        highlighted ? "border-gold-500 ring-1 ring-gold-500" : "border-line",
      )}
    >
      <div className="flex flex-wrap items-start gap-3">
        <InstructorAvatar name={name} className="size-10 shrink-0 text-xs" />
        <div className="min-w-0 flex-1">
          <p className="font-semibold text-ink">{name}</p>
          <p className="text-xs text-stone-500">
            {s.lecture?.title}
            {s.lecture && (
              <>
                {" "}
                ·{" "}
                <Link href={`/learn/${s.lecture.course.slug}?lecture=${s.lecture.id}`} className="hover:underline">
                  {s.lecture.course.title}
                </Link>
              </>
            )}{" "}
            · handed in {timeAgo(s.submitted_at)}
          </p>
        </div>
        <span
          className={cn(
            "inline-flex h-6 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide ring-1 ring-inset",
            s.status === "reviewed"
              ? "bg-[#e7f1ec] text-success ring-[#cfe3d8]"
              : "bg-gold-50 text-gold-600 ring-gold-200",
          )}
        >
          {s.status === "reviewed" ? "Reviewed" : "Needs feedback"}
        </span>
      </div>

      {s.body && (
        <p className="mt-4 rounded-md bg-paper-2/60 px-4 py-3 text-sm whitespace-pre-line text-stone-700">{s.body}</p>
      )}
      {s.file && (
        <a
          href={s.file.url}
          className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-navy-800 hover:underline"
        >
          <FileText className="size-4" /> {s.file.name}
        </a>
      )}

      {s.status === "reviewed" && !open ? (
        <div className="mt-4 rounded-md border-l-2 border-gold-500 bg-paper-2/40 px-4 py-3">
          <p className="flex items-center gap-1.5 text-xs font-bold text-navy-900">
            <MessageSquareReply className="size-3.5" /> Your feedback
          </p>
          <p className="mt-1 text-sm whitespace-pre-line text-stone-700">{s.feedback}</p>
          <button
            type="button"
            onClick={() => setOpen(true)}
            className="mt-2 text-xs font-semibold text-navy-800 hover:underline"
          >
            Edit feedback
          </button>
        </div>
      ) : open ? (
        <div className="mt-4 space-y-2">
          {error && <FormAlert>{error}</FormAlert>}
          <Textarea
            aria-label={`Feedback for ${name}`}
            rows={4}
            maxLength={5000}
            value={feedback}
            onChange={(e) => setFeedback(e.target.value)}
            className="bg-white"
            placeholder="What did they get right, and what should they look at again?"
            autoFocus
          />
          <div className="flex gap-2">
            <Button size="sm" disabled={feedback.trim().length < 2 || busy} onClick={() => void send()}>
              {busy ? "Sending…" : "Send feedback"}
            </Button>
            <Button size="sm" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : (
        <Button size="sm" className="mt-4" onClick={() => setOpen(true)}>
          Give feedback
        </Button>
      )}
    </li>
  );
}
