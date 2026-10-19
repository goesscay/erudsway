"use client";

import { Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useLectureContent } from "@/lib/learning/api";

/** An article lecture, shown in place of the video. */
export function ArticleView({
  lectureId,
  title,
  completed,
  tracked,
  onComplete,
}: {
  lectureId: number;
  title: string;
  completed: boolean;
  tracked: boolean;
  onComplete: () => void;
}) {
  const content = useLectureContent(lectureId);

  return (
    <article className="max-h-[70vh] min-h-[50vh] overflow-y-auto border-b border-line bg-white px-6 py-10 sm:px-12">
      <div className="mx-auto max-w-[70ch]">
        <h2 className="text-h2 mb-6">{title}</h2>
        {content.isPending ? (
          <div className="space-y-3">
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-11/12" />
            <Skeleton className="h-4 w-4/5" />
          </div>
        ) : content.isError ? (
          <p className="text-sm text-stone-500">We couldn’t load this article. Refresh to try again.</p>
        ) : (
          // Sanitised server-side (HTMLPurifier) when the instructor saved it.
          <div className="prose-content leading-relaxed text-stone-700" dangerouslySetInnerHTML={{ __html: content.data?.article_html ?? "" }} />
        )}
        {tracked && (
          <div className="mt-10 border-t border-line pt-6">
            {completed ? (
              <p className="flex items-center gap-2 text-sm font-semibold text-success">
                <Check className="size-4" /> Completed
              </p>
            ) : (
              <Button onClick={onComplete}>Mark as complete</Button>
            )}
          </div>
        )}
      </div>
    </article>
  );
}
