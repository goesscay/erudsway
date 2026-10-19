"use client";

import Link from "next/link";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { Stars } from "@/components/engagement/stars";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { ApiRequestError } from "@/lib/api/client";
import { moderateReview, useModerationQueue, type Review } from "@/lib/engagement/api";
import { cn } from "@/lib/utils";

type Status = "flagged" | "hidden" | "visible";

export default function ReviewModerationPage() {
  return <RequireAuth permission="moderate_courses">{() => <Moderation />}</RequireAuth>;
}

function Moderation() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<Status>("flagged");
  const queue = useModerationQueue(status);
  const [hiding, setHiding] = useState<Review | null>(null);

  const restore = async (r: Review) => {
    await moderateReview(r.id, "restore", null);
    await queryClient.invalidateQueries({ queryKey: ["admin", "reviews"] });
  };

  return (
    <>
      <AdminPageHeader
        title="Learner reviews"
        description="Reviews learners have reported. Hide anything abusive, promotional or off-topic; clear the flag on the rest."
      />
      <div className="space-y-5 px-8 py-6">
        <div className="flex gap-2" role="group" aria-label="Show">
          {(
            [
              ["flagged", "Reported"],
              ["hidden", "Hidden"],
              ["visible", "All visible"],
            ] as const
          ).map(([value, label]) => (
            <button
              key={value}
              type="button"
              aria-pressed={status === value}
              onClick={() => setStatus(value)}
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

        {queue.isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : queue.isError ? (
          <FormAlert>Couldn&rsquo;t load reviews.</FormAlert>
        ) : queue.data.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">
            {status === "flagged" ? "Nothing reported. The queue is clear." : "No reviews here."}
          </div>
        ) : (
          <ul className="space-y-4">
            {queue.data.data.map((r) => (
              <li key={r.id} className="rounded-md border border-line bg-white p-5">
                <div className="flex flex-wrap items-center gap-3 text-sm">
                  <Stars value={r.rating} />
                  <span className="font-semibold text-ink">{r.author}</span>
                  {r.course && (
                    <Link href={`/course/${r.course.slug}#reviews`} className="text-navy-800 hover:underline">
                      {r.course.title}
                    </Link>
                  )}
                  <span className="text-xs text-stone-500">{formatDate(r.created_at)}</span>
                  <span className="ml-auto">
                    <StatusPill status={r.status === "flagged" ? "reported" : r.status} />
                  </span>
                </div>
                <p className="mt-3 text-sm leading-relaxed whitespace-pre-line text-stone-700">
                  {r.body ?? <em className="text-stone-500">Rating only, no text.</em>}
                </p>
                {r.reports && r.reports.length > 0 && (
                  <div className="mt-4 rounded-md bg-paper-2/60 px-4 py-3">
                    <p className="text-xs font-bold text-ink">
                      {r.report_count} {r.report_count === 1 ? "report" : "reports"}
                    </p>
                    <ul className="mt-1 list-disc space-y-0.5 pl-5 text-sm text-stone-700">
                      {r.reports.map((reason, i) => (
                        <li key={i}>{reason}</li>
                      ))}
                    </ul>
                  </div>
                )}
                <div className="mt-4 flex gap-2">
                  {r.status !== "hidden" && (
                    <Button size="sm" variant="destructive" onClick={() => setHiding(r)}>
                      Hide review
                    </Button>
                  )}
                  {r.status !== "visible" && (
                    <Button size="sm" variant="outline" onClick={() => void restore(r)}>
                      {r.status === "hidden" ? "Restore" : "Keep — clear the flag"}
                    </Button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
      <HideDialog review={hiding} onClose={() => setHiding(null)} />
    </>
  );
}

function HideDialog({ review, onClose }: { review: Review | null; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const hide = async () => {
    if (!review) return;
    setBusy(true);
    setError(null);
    try {
      await moderateReview(review.id, "hide", note.trim());
      await queryClient.invalidateQueries({ queryKey: ["admin", "reviews"] });
      setNote("");
      onClose();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (e.fields.note?.[0] ?? e.message) : "Couldn’t hide the review.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={review !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Hide this review</DialogTitle>
          <DialogDescription>
            It disappears from the course page and stops counting toward the rating. The reviewer can&rsquo;t edit it
            afterwards.
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <Textarea
          aria-label="Reason (kept in the audit log)"
          rows={3}
          maxLength={500}
          value={note}
          onChange={(e) => setNote(e.target.value)}
          placeholder="Reason, kept in the audit log"
          className="bg-white"
        />
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="destructive" disabled={!note.trim() || busy} onClick={() => void hide()}>
            {busy ? "Hiding…" : "Hide review"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
