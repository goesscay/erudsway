"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { Star } from "lucide-react";
import { ReviewItem } from "@/components/engagement/course-reviews";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { ApiRequestError } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { replyToReview, useInstructorReviews, type Review } from "@/lib/engagement/api";
import { cn } from "@/lib/utils";

export function InstructorReviewsView() {
  const { data: me } = useMe();
  const search = useSearchParams();
  const highlight = Number(search.get("review")) || null;
  const [unreplied, setUnreplied] = useState(false);
  const [page, setPage] = useState(1);
  const list = useInstructorReviews(unreplied, page);
  const name = me?.instructor?.display_name ?? me?.name ?? "the instructor";

  return (
    <>
      <header className="border-b border-line bg-white px-4 py-6 sm:px-8">
        <h1 className="text-h2">Reviews</h1>
        <p className="mt-1 text-sm text-stone-500">
          What learners say about your courses. A thoughtful public reply builds trust with future learners.
        </p>
      </header>

      <div className="space-y-5 px-4 py-8 sm:px-8">
        <div className="flex gap-2" role="group" aria-label="Show">
          {[
            [false, "All reviews"],
            [true, "Awaiting your reply"],
          ].map(([value, label]) => (
            <button
              key={String(value)}
              type="button"
              aria-pressed={unreplied === value}
              onClick={() => {
                setUnreplied(value as boolean);
                setPage(1);
              }}
              className={cn(
                "h-8 rounded-full border px-3.5 text-xs font-semibold",
                unreplied === value
                  ? "border-navy-900 bg-navy-900 text-white"
                  : "border-line bg-white text-stone-700 hover:border-input",
              )}
            >
              {label as string}
            </button>
          ))}
        </div>

        {list.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load reviews.</FormAlert>
        ) : list.data.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
            <Star className="mx-auto size-8 text-stone-300" strokeWidth={1.5} />
            <p className="mt-3 text-sm text-stone-700">
              {unreplied
                ? "You’ve replied to every review. Nice work."
                : "No reviews yet. Learners can rate a course once they’ve started it."}
            </p>
          </div>
        ) : (
          <>
            <ul
              className={cn(
                "divide-y divide-line rounded-md border border-line bg-white px-5",
                list.isPlaceholderData && "opacity-60",
              )}
            >
              {list.data.data.map((r) => (
                <ReviewWithReply key={r.id} review={r} instructorName={name} highlighted={r.id === highlight} />
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

function ReviewWithReply({
  review,
  instructorName,
  highlighted,
}: {
  review: Review;
  instructorName: string;
  highlighted: boolean;
}) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(highlighted && !review.instructor_reply);
  const [reply, setReply] = useState(review.instructor_reply ?? "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const save = async (value: string | null) => {
    setBusy(true);
    setError(null);
    try {
      await replyToReview(review.id, value);
      await queryClient.invalidateQueries({ queryKey: ["instructor", "reviews"] });
      setOpen(false);
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t save your reply.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className={cn(highlighted && "-mx-5 bg-gold-50/60 px-5")}>
      <ReviewItem review={review} instructorName={instructorName}>
        {review.course && (
          <p className="mt-2 text-xs text-stone-500">
            on{" "}
            <Link
              href={`/course/${review.course.slug}#reviews`}
              className="font-semibold text-navy-800 hover:underline"
            >
              {review.course.title}
            </Link>
          </p>
        )}
        {open ? (
          <div className="mt-3 space-y-2">
            {error && <FormAlert>{error}</FormAlert>}
            <Textarea
              aria-label="Your public reply"
              rows={3}
              maxLength={2000}
              value={reply}
              onChange={(e) => setReply(e.target.value)}
              className="bg-white"
              placeholder="Thank the learner and respond to their points. Replies are public."
              autoFocus
            />
            <div className="flex gap-2">
              <Button size="sm" disabled={!reply.trim() || busy} onClick={() => void save(reply.trim())}>
                {busy ? "Saving…" : "Post reply"}
              </Button>
              <Button size="sm" variant="outline" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              {review.instructor_reply && (
                <Button
                  size="sm"
                  variant="ghost"
                  className="ml-auto text-destructive"
                  disabled={busy}
                  onClick={() => void save(null)}
                >
                  Remove reply
                </Button>
              )}
            </div>
          </div>
        ) : (
          <Button size="sm" variant="outline" className="mt-3" onClick={() => setOpen(true)}>
            {review.instructor_reply ? "Edit reply" : "Reply"}
          </Button>
        )}
      </ReviewItem>
    </div>
  );
}
