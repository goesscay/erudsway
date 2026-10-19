"use client";

import { useState } from "react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { ApiRequestError } from "@/lib/api/client";
import { useMyReview, useReviewActions, type Review } from "@/lib/engagement/api";
import { Stars, StarInput } from "./stars";

/** The learner's own rating for the course, in the player's Reviews tab. */
export function MyReview({ slug, instructorName }: { slug: string; instructorName: string }) {
  const mine = useMyReview(slug);

  if (mine.isPending) return <Skeleton className="h-40 w-full" />;
  if (mine.isError) return <FormAlert>Couldn&rsquo;t load your review.</FormAlert>;
  return <MyReviewInner slug={slug} review={mine.data ?? null} instructorName={instructorName} />;
}

function MyReviewInner({
  slug,
  review,
  instructorName,
}: {
  slug: string;
  review: Review | null;
  instructorName: string;
}) {
  const { save, remove } = useReviewActions(slug);
  const [editing, setEditing] = useState(review === null);
  const [rating, setRating] = useState(review?.rating ?? 0);
  const [body, setBody] = useState(review?.body ?? "");
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  if (review?.status === "hidden") {
    return (
      <FormAlert tone="info">
        Your review was removed by our moderators because it didn&rsquo;t follow the review guidelines, so it
        can&rsquo;t be edited.
      </FormAlert>
    );
  }

  if (!editing && review) {
    return (
      <div className="space-y-4 rounded-md border border-line bg-white p-5">
        {saved && <FormAlert tone="success">Thanks — your review is live on the course page.</FormAlert>}
        <div className="flex items-center justify-between gap-3">
          <p className="text-sm font-semibold text-ink">Your review</p>
          <Stars value={review.rating} />
        </div>
        {review.body ? (
          <p className="text-sm leading-relaxed whitespace-pre-line text-stone-700">{review.body}</p>
        ) : (
          <p className="text-sm text-stone-500">You rated the course without a written review.</p>
        )}
        {review.instructor_reply && (
          <div className="rounded-md border-l-2 border-gold-500 bg-paper-2/60 px-4 py-3">
            <p className="text-xs font-bold text-navy-900">Response from {instructorName}</p>
            <p className="mt-1 text-sm whitespace-pre-line text-stone-700">{review.instructor_reply}</p>
          </div>
        )}
        <div className="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setEditing(true);
              setSaved(false);
            }}
          >
            Edit review
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="text-destructive"
            disabled={remove.isPending}
            onClick={() =>
              window.confirm("Delete your review? Your rating will be removed from the course.") &&
              remove.mutate(undefined, {
                onSuccess: () => {
                  setRating(0);
                  setBody("");
                  setSaved(false);
                  setEditing(true);
                },
              })
            }
          >
            Delete
          </Button>
        </div>
      </div>
    );
  }

  const submit = () => {
    setError(null);
    save.mutate(
      { rating, body: body.trim() || null },
      {
        onSuccess: () => {
          setEditing(false);
          setSaved(true);
        },
        onError: (e) =>
          setError(
            e instanceof ApiRequestError
              ? (Object.values(e.fields)[0]?.[0] ?? e.message)
              : "Couldn’t save your review.",
          ),
      },
    );
  };

  return (
    <div className="space-y-5 rounded-md border border-line bg-white p-6">
      <div className="text-center">
        <h3 className="font-serif text-xl font-semibold text-ink">
          {review ? "Edit your rating" : "How would you rate this course?"}
        </h3>
        <p className="mt-1 text-sm text-stone-500">Your first name and last initial are shown with your review.</p>
      </div>
      <StarInput id="my-rating-label" value={rating} onChange={setRating} />
      {error && <FormAlert>{error}</FormAlert>}
      {rating > 0 && (
        <div className="space-y-1.5">
          <label htmlFor="review-body" className="text-sm font-semibold text-ink">
            Tell other learners what you think <span className="font-normal text-stone-500">(optional)</span>
          </label>
          <Textarea
            id="review-body"
            rows={5}
            maxLength={2000}
            value={body}
            onChange={(e) => setBody(e.target.value)}
            className="bg-white"
            placeholder="What did you learn? Was it practical for your work? Who would you recommend it to?"
          />
        </div>
      )}
      <div className="flex justify-center gap-2">
        {review && (
          <Button variant="outline" onClick={() => setEditing(false)}>
            Cancel
          </Button>
        )}
        <Button onClick={submit} disabled={rating === 0 || save.isPending}>
          {save.isPending ? "Saving…" : review ? "Save changes" : "Submit review"}
        </Button>
      </div>
    </div>
  );
}
