"use client";

import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import { Award, BookOpen, PlayCircle } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useMyLearning, type Enrollment } from "@/lib/learning/api";
import { cn } from "@/lib/utils";

type Filter = "all" | "in_progress" | "completed";

const filters: { value: Filter; label: string }[] = [
  { value: "all", label: "All courses" },
  { value: "in_progress", label: "In progress" },
  { value: "completed", label: "Completed" },
];

export function MyLearning() {
  return (
    <RequireAuth>
      {() => (
        <>
          <section className="dark bg-navy-950 text-foreground">
            <div className="mx-auto max-w-7xl px-4 pt-10 pb-8 sm:px-6 lg:px-8">
              <h1 className="font-serif text-4xl font-semibold text-white">My learning</h1>
            </div>
          </section>
          <Library />
        </>
      )}
    </RequireAuth>
  );
}

function Library() {
  const list = useMyLearning();
  const [filter, setFilter] = useState<Filter>("all");
  const all = list.data ?? [];
  const shown = all.filter((e) =>
    filter === "completed" ? e.completed_at !== null : filter === "in_progress" ? e.completed_at === null : true,
  );

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div role="tablist" aria-label="Filter courses" className="mb-8 flex gap-6 border-b border-line">
        {filters.map((f) => (
          <button
            key={f.value}
            role="tab"
            aria-selected={filter === f.value}
            onClick={() => setFilter(f.value)}
            className={cn(
              "-mb-px border-b-2 pb-3 text-sm font-semibold",
              filter === f.value
                ? "border-gold-500 text-navy-900"
                : "border-transparent text-stone-500 hover:text-navy-900",
            )}
          >
            {f.label}
          </button>
        ))}
      </div>

      {list.isPending ? (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <div key={i} className="space-y-3">
              <Skeleton className="aspect-video w-full" />
              <Skeleton className="h-4 w-4/5" />
              <Skeleton className="h-3 w-1/2" />
            </div>
          ))}
        </div>
      ) : list.isError ? (
        <FormAlert>We couldn&rsquo;t load your courses. Refresh to try again.</FormAlert>
      ) : all.length === 0 ? (
        <div className="mx-auto max-w-md py-16 text-center">
          <BookOpen className="mx-auto size-10 text-gold-600" strokeWidth={1.5} />
          <h2 className="mt-4 text-h3">Start learning</h2>
          <p className="mt-2 text-sm text-stone-700">
            Courses you enroll in appear here, with your progress and where you left off.
          </p>
          <Link href="/courses" className={buttonVariants({ size: "lg", className: "mt-6" })}>
            Browse courses
          </Link>
        </div>
      ) : shown.length === 0 ? (
        <p className="py-16 text-center text-sm text-stone-500">
          {filter === "completed" ? "No completed courses yet — keep going." : "Every course here is complete."}
        </p>
      ) : (
        <ul className="grid gap-x-6 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
          {shown.map((e) => (
            <li key={e.id}>
              <EnrolledCard enrollment={e} />
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function EnrolledCard({ enrollment: e }: { enrollment: Enrollment }) {
  const started = e.completed_count > 0 || e.last_accessed_at !== null;
  const href = e.last_lecture_id ? `/learn/${e.course.slug}?lecture=${e.last_lecture_id}` : `/learn/${e.course.slug}`;

  return (
    <div>
      <Link href={href} className="group block">
        <div className="relative aspect-video overflow-hidden rounded-sm bg-paper-2 ring-1 ring-line">
          {e.course.thumbnail_url && (
            <Image
              src={e.course.thumbnail_url}
              alt=""
              fill
              sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw"
              className="object-cover"
            />
          )}
          <span className="absolute inset-0 flex items-center justify-center bg-navy-950/0 transition-colors group-hover:bg-navy-950/40">
            <PlayCircle
              className="size-12 text-white opacity-0 transition-opacity group-hover:opacity-100"
              strokeWidth={1.25}
            />
          </span>
        </div>
        <h3 className="mt-3 line-clamp-2 font-sans text-[0.95rem] leading-snug font-bold text-ink group-hover:text-navy-800">
          {e.course.title}
        </h3>
        <p className="mt-1 truncate text-xs text-stone-500">{e.course.instructor_name}</p>
        <div className="mt-3">
          <div
            className="h-1 overflow-hidden rounded-full bg-line"
            role="progressbar"
            aria-valuenow={e.progress_percent}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label="Course progress"
          >
            <div className="h-full rounded-full bg-navy-900" style={{ width: `${e.progress_percent}%` }} />
          </div>
          <p className="mt-1.5 text-xs text-stone-700">
            {e.completed_at ? (
              "Completed"
            ) : started ? (
              `${e.progress_percent}% complete`
            ) : (
              <span className="font-semibold text-navy-800">Start course</span>
            )}
          </p>
        </div>
      </Link>
      {e.certificate_code && (
        <Link
          href={`/certificates/${e.certificate_code}`}
          className="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-gold-600 hover:underline"
        >
          <Award className="size-3.5" /> View certificate
        </Link>
      )}
    </div>
  );
}
