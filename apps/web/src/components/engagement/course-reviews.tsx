"use client";

import Link from "next/link";
import { useState } from "react";
import { Flag, MessageSquareReply } from "lucide-react";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { Rating } from "@/components/course/rating";
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
import { useMe } from "@/lib/auth/session";
import { reportReview, useCourseReviews, type Review } from "@/lib/engagement/api";
import { formatCount, timeAgo } from "@/lib/format";
import { useHydrated } from "@/lib/use-hydrated";
import { cn } from "@/lib/utils";
import { Stars } from "./stars";

/** Ratings summary, star filter and the review list on a course page. */
export function CourseReviews({ slug, instructorName }: { slug: string; instructorName: string }) {
  const [rating, setRating] = useState<number | null>(null);
  const [page, setPage] = useState(1);
  const reviews = useCourseReviews(slug, rating, page);
  const [reporting, setReporting] = useState<Review | null>(null);

  if (reviews.isPending) return <Skeleton className="h-48 w-full" />;
  if (reviews.isError) return null;

  const { summary, data, meta } = reviews.data;
  if (summary.count === 0) {
    return (
      <section id="reviews">
        <h2 className="mb-2 text-h3">Learner reviews</h2>
        <p className="text-sm text-stone-500">
          No reviews yet. Learners can rate the course once they&rsquo;ve started it.
        </p>
      </section>
    );
  }

  const pick = (r: number | null) => {
    setRating(r);
    setPage(1);
  };

  return (
    <section id="reviews" aria-labelledby="reviews-title">
      <h2 id="reviews-title" className="mb-5 text-h3">
        Learner reviews
      </h2>
      <div className="grid gap-8 sm:grid-cols-[180px_1fr]">
        <div className="text-center sm:text-left">
          <p className="tabular font-serif text-6xl font-semibold text-gold-600">{summary.average.toFixed(1)}</p>
          <Rating
            value={summary.average}
            className="mt-1 justify-center sm:justify-start [&>span:first-child]:hidden"
          />
          <p className="mt-1 text-sm font-semibold text-gold-600">
            Course rating · {formatCount(summary.count)} {summary.count === 1 ? "review" : "reviews"}
          </p>
        </div>
        <ul className="space-y-1.5" aria-label="Filter by rating">
          {summary.distribution.map((d) => {
            const active = rating === d.rating;
            return (
              <li key={d.rating}>
                <button
                  type="button"
                  onClick={() => pick(active ? null : d.rating)}
                  disabled={d.count === 0}
                  aria-pressed={active}
                  className={cn(
                    "group flex w-full items-center gap-3 rounded-sm px-1 py-0.5 text-left text-sm disabled:cursor-default",
                    rating !== null && !active && "opacity-50",
                  )}
                >
                  <span className="h-2 flex-1 overflow-hidden rounded-full bg-paper-2">
                    <span
                      className="block h-full rounded-full bg-stone-500 group-enabled:group-hover:bg-navy-800"
                      style={{ width: `${d.percent}%` }}
                    />
                  </span>
                  <Stars value={d.rating} />
                  <span className={cn("tabular w-10 text-right text-navy-800", active && "font-bold")}>
                    {d.percent}%
                  </span>
                </button>
              </li>
            );
          })}
        </ul>
      </div>

      {rating !== null && (
        <p className="mt-6 text-sm text-stone-700">
          Showing {data.length === 0 ? "no" : meta.total} {rating}-star {meta.total === 1 ? "review" : "reviews"}.{" "}
          <button
            type="button"
            className="font-semibold text-navy-800 underline-offset-2 hover:underline"
            onClick={() => pick(null)}
          >
            Show all
          </button>
        </p>
      )}

      <ul className={cn("mt-6 divide-y divide-line border-y border-line", reviews.isPlaceholderData && "opacity-60")}>
        {data.map((r) => (
          <ReviewItem key={r.id} review={r} instructorName={instructorName} onReport={() => setReporting(r)} />
        ))}
      </ul>

      {meta.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-sm">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>
            Previous
          </Button>
          <span className="tabular text-stone-500">
            Page {meta.current_page} of {meta.last_page}
          </span>
          <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>
            More reviews
          </Button>
        </div>
      )}

      <ReportDialog review={reporting} onClose={() => setReporting(null)} slug={slug} />
    </section>
  );
}

export function ReviewItem({
  review,
  instructorName,
  onReport,
  children,
}: {
  review: Review;
  instructorName: string;
  onReport?: () => void;
  children?: React.ReactNode;
}) {
  const hydrated = useHydrated();

  return (
    <li className="py-5">
      <div className="flex gap-4">
        <InstructorAvatar name={review.author} className="size-11 shrink-0 text-sm" />
        <div className="min-w-0 flex-1">
          <p className="font-bold text-ink">{review.author}</p>
          <div className="mt-0.5 flex items-center gap-2 text-xs text-stone-500">
            <Stars value={review.rating} />
            <span suppressHydrationWarning>{hydrated ? timeAgo(review.created_at) : ""}</span>
          </div>
          {review.body && (
            <p className="mt-2 text-sm leading-relaxed whitespace-pre-line text-stone-700">{review.body}</p>
          )}
          {review.instructor_reply && (
            <div className="mt-3 rounded-md border-l-2 border-gold-500 bg-paper-2/60 px-4 py-3">
              <p className="flex items-center gap-1.5 text-xs font-bold text-navy-900">
                <MessageSquareReply className="size-3.5" /> Response from {instructorName}
              </p>
              <p className="mt-1 text-sm leading-relaxed whitespace-pre-line text-stone-700">
                {review.instructor_reply}
              </p>
            </div>
          )}
          {onReport && (
            <button
              type="button"
              onClick={onReport}
              className="mt-2 inline-flex items-center gap-1 text-xs text-stone-500 hover:text-navy-900"
            >
              <Flag className="size-3" /> Report
            </button>
          )}
          {children}
        </div>
      </div>
    </li>
  );
}

function ReportDialog({ review, onClose, slug }: { review: Review | null; onClose: () => void; slug: string }) {
  const { data: me } = useMe();
  const [reason, setReason] = useState("");
  const [state, setState] = useState<"idle" | "sending" | "sent">("idle");
  const [error, setError] = useState<string | null>(null);

  const close = () => {
    onClose();
    setReason("");
    setState("idle");
    setError(null);
  };

  const send = async () => {
    if (!review) return;
    setState("sending");
    setError(null);
    try {
      await reportReview(review.id, reason.trim());
      setState("sent");
    } catch (e) {
      setError(e instanceof ApiRequestError ? (e.fields.reason?.[0] ?? e.message) : "Couldn’t send the report.");
      setState("idle");
    }
  };

  return (
    <Dialog open={review !== null} onOpenChange={(o) => !o && close()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Report this review</DialogTitle>
          <DialogDescription>
            Tell us what&rsquo;s wrong. Our moderators check every report; the reviewer isn&rsquo;t told who reported
            it.
          </DialogDescription>
        </DialogHeader>
        {!me ? (
          <p className="text-sm text-stone-700">
            <Link
              href={`/login?next=${encodeURIComponent(`/course/${slug}#reviews`)}`}
              className="font-semibold text-navy-800 underline"
            >
              Log in
            </Link>{" "}
            to report a review.
          </p>
        ) : state === "sent" ? (
          <FormAlert tone="success">Thanks — we&rsquo;ll take a look.</FormAlert>
        ) : (
          <>
            {error && <FormAlert>{error}</FormAlert>}
            <Textarea
              aria-label="Reason"
              rows={4}
              maxLength={500}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="For example: spam, abusive language, or not about this course"
              className="bg-white"
            />
          </>
        )}
        <DialogFooter>
          <Button variant="outline" onClick={close}>
            {state === "sent" ? "Close" : "Cancel"}
          </Button>
          {me && state !== "sent" && (
            <Button onClick={send} disabled={reason.trim().length < 5 || state === "sending"}>
              {state === "sending" ? "Sending…" : "Send report"}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
