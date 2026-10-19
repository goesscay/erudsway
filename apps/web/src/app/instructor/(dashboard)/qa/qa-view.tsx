"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { MessageSquare } from "lucide-react";
import { QuestionRow, Thread } from "@/components/engagement/qa-panel";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useInstructorQuestions } from "@/lib/engagement/api";
import { cn } from "@/lib/utils";

export function InstructorQaView() {
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams();
  const open = Number(search.get("question")) || null;
  const [unanswered, setUnanswered] = useState(true);
  const [page, setPage] = useState(1);
  const list = useInstructorQuestions(unanswered, page);

  const select = (id: number | null) => router.replace(id ? `${pathname}?question=${id}` : pathname, { scroll: false });

  return (
    <>
      <header className="border-b border-line bg-white px-4 py-6 sm:px-8">
        <h1 className="text-h2">Q&amp;A</h1>
        <p className="mt-1 text-sm text-stone-500">
          Questions learners ask in your courses. Your answers are marked as the instructor&rsquo;s.
        </p>
      </header>

      <div className="mx-auto max-w-4xl space-y-5 px-4 py-8 sm:px-8">
        {open !== null ? (
          <Thread id={open} canModerate onBack={() => select(null)} />
        ) : (
          <>
            <div className="flex gap-2" role="group" aria-label="Show">
              {(
                [
                  [true, "Needs an answer"],
                  [false, "All questions"],
                ] as const
              ).map(([value, label]) => (
                <button
                  key={String(value)}
                  type="button"
                  aria-pressed={unanswered === value}
                  onClick={() => {
                    setUnanswered(value);
                    setPage(1);
                  }}
                  className={cn(
                    "h-8 rounded-full border px-3.5 text-xs font-semibold",
                    unanswered === value
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
              <FormAlert>Couldn&rsquo;t load questions.</FormAlert>
            ) : list.data.data.length === 0 ? (
              <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
                <MessageSquare className="mx-auto size-8 text-stone-300" strokeWidth={1.5} />
                <p className="mt-3 text-sm text-stone-700">
                  {unanswered ? "Every question has an instructor answer." : "No questions yet."}
                </p>
              </div>
            ) : (
              <>
                <ul
                  className={cn(
                    "divide-y divide-line rounded-md border border-line bg-white",
                    list.isPlaceholderData && "opacity-60",
                  )}
                >
                  {list.data.data.map((q) => (
                    <QuestionRow key={q.id} question={q} showCourse onOpen={() => select(q.id)} />
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
          </>
        )}
      </div>
    </>
  );
}
