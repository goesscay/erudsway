"use client";

import Link from "next/link";
import { useParams, usePathname } from "next/navigation";
import { useState } from "react";
import { Check, ChevronLeft, CircleAlert, Clock3, ExternalLink, Eye, Lock, X } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, unwrap } from "@/lib/api/client";
import { statusLabel, statusStyle, useCourseEditor, useEditorAction, type CourseEditor } from "@/lib/authoring/editor";
import { cn } from "@/lib/utils";

const steps = [
  { slug: "learners", label: "Intended learners", group: "learners" },
  { slug: "curriculum", label: "Curriculum", group: "curriculum" },
  { slug: "landing", label: "Landing page", group: "landing" },
  { slug: "pricing", label: "Pricing", group: "pricing" },
] as const;

/**
 * Builder chrome. The header and step nav read the course id and current step from the URL,
 * which only exists at request time, so the layout wraps each in its own Suspense boundary
 * and the step page itself always renders.
 */
export function BuilderHeader() {
  const { id } = useParams<{ id: string }>();
  const { data: course, isError, error } = useCourseEditor(id);
  const [submitOpen, setSubmitOpen] = useState(false);

  if (isError) {
    const notFound = error instanceof ApiRequestError && error.status === 404;
    return (
      <div className="fixed inset-0 z-40 bg-paper">
        <div className="mx-auto max-w-md px-6 py-24 text-center">
          <h1 className="text-h3">{notFound ? "Course not found" : "We couldn’t load this course"}</h1>
          <Link href="/instructor" className={buttonVariants({ variant: "outline", className: "mt-6" })}>
            Back to courses
          </Link>
        </div>
      </div>
    );
  }

  return (
    <>
      <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-line bg-white px-4 sm:px-6">
        <Link href="/instructor" className="flex items-center gap-1 text-sm font-semibold text-stone-700 hover:text-navy-900">
          <ChevronLeft className="size-4" /> <span className="hidden sm:inline">Courses</span>
        </Link>
        <span className="h-6 w-px bg-line" />
        {course ? (
          <>
            <p className="min-w-0 truncate font-sans text-base font-bold text-ink">{course.title}</p>
            <span className={cn("hidden h-6 shrink-0 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide ring-1 ring-inset sm:inline-flex", statusStyle[course.status])}>
              {statusLabel[course.status]}
            </span>
            <span className="ml-auto" />
            {course.lecture_count > 0 && (
              <Link href={`/learn/${course.slug}`} target="_blank" className={buttonVariants({ variant: "ghost", className: "hidden md:inline-flex" })}>
                <Eye /> Preview
              </Link>
            )}
            {course.status === "published" && (
              <Link href={`/course/${course.slug}`} target="_blank" className={buttonVariants({ variant: "outline", className: "hidden sm:inline-flex" })}>
                <ExternalLink /> View live
              </Link>
            )}
            {["draft", "changes_requested", "unpublished"].includes(course.status) && (
              <Button onClick={() => setSubmitOpen(true)}>Submit for review</Button>
            )}
          </>
        ) : (
          <Skeleton className="h-5 w-64" />
        )}
      </header>

      {course && <StatusBanner course={course} />}
      {course && <SubmitDialog course={course} open={submitOpen} onClose={() => setSubmitOpen(false)} />}
    </>
  );
}

export function BuilderHeaderFallback() {
  return <div className="sticky top-0 z-30 h-16 shrink-0 border-b border-line bg-white" />;
}

export function BuilderNav() {
  const { id } = useParams<{ id: string }>();
  const pathname = usePathname();
  const { data: course } = useCourseEditor(id);
  const groupDone = (group: string) => !!course && course.checklist.filter((c) => c.group === group).every((c) => c.ok);

  return (
    <ul className="flex gap-1 overflow-x-auto border-b border-line pb-2 lg:block lg:space-y-0.5 lg:border-b-0 lg:border-l lg:pb-0">
      {steps.map((s) => {
        const active = pathname.endsWith(`/${s.slug}`);
        const done = groupDone(s.group);
        return (
          <li key={s.slug} className="shrink-0">
            <Link
              href={`/instructor/courses/${id}/${s.slug}`}
              aria-current={active ? "page" : undefined}
              className={cn(
                "flex items-center gap-2.5 rounded-sm py-1.5 pr-3 pl-2 text-sm lg:-ml-px lg:rounded-none lg:border-l-2 lg:pl-4",
                active ? "font-semibold text-navy-900 lg:border-gold-500" : "text-stone-700 hover:text-navy-900 lg:border-transparent",
              )}
            >
              <span className={cn("flex size-4 shrink-0 items-center justify-center rounded-full ring-1", done ? "bg-success ring-success" : "ring-stone-500/50")}>
                {done && <Check className="size-2.5 text-white" strokeWidth={3.5} />}
              </span>
              {s.label}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}

export function BuilderNavFallback() {
  return (
    <div className="space-y-2 lg:pl-4">
      {steps.map((s) => (
        <Skeleton key={s.slug} className="h-5 w-32" />
      ))}
    </div>
  );
}

/** Loading state for a step page while the course loads. */
export function StepSkeleton() {
  return (
    <div className="space-y-4" aria-busy>
      <Skeleton className="h-9 w-56" />
      <Skeleton className="h-4 w-96 max-w-full" />
      <Skeleton className="h-64 w-full" />
    </div>
  );
}

function StatusBanner({ course }: { course: CourseEditor }) {
  const feedback = course.latest_submission;
  if (course.status === "in_review") {
    return (
      <div className="border-b border-gold-200 bg-gold-50 px-6 py-3 text-sm text-stone-700">
        <div className="mx-auto flex max-w-6xl items-center gap-2">
          <Lock className="size-4 text-gold-600" />
          Submitted for review — editing is paused until our team responds, usually within three working days.
        </div>
      </div>
    );
  }
  if (course.status === "changes_requested" && feedback?.feedback) {
    return (
      <div className="border-b border-destructive/20 bg-destructive/5 px-6 py-3 text-sm">
        <div className="mx-auto flex max-w-6xl gap-2">
          <CircleAlert className="mt-0.5 size-4 shrink-0 text-destructive" />
          <p className="text-stone-700">
            <strong className="text-ink">Changes requested by our review team:</strong> {feedback.feedback}
          </p>
        </div>
      </div>
    );
  }
  return null;
}

function SubmitDialog({ course, open, onClose }: { course: CourseEditor; open: boolean; onClose: () => void }) {
  const [error, setError] = useState<string | null>(null);
  const submit = useEditorAction(course.id, () =>
    unwrap(api.POST("/instructor/courses/{course}/submit", { params: { path: { course: course.id } } })),
  );
  const ready = course.checklist.every((c) => c.ok);
  const stepFor = (group: string) => steps.find((s) => s.group === group)?.slug ?? "curriculum";

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Submit for review</DialogTitle>
          <DialogDescription>
            {ready
              ? "Everything is in place. Our team will review the course and email you with a decision."
              : "A few things still need attention before you can submit."}
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <ul className="max-h-[50vh] space-y-1.5 overflow-y-auto">
          {course.checklist.map((item) => (
            <li key={item.key} className="flex items-start gap-2.5 text-sm">
              {item.ok ? (
                <Check className="mt-0.5 size-4 shrink-0 text-success" strokeWidth={2.5} />
              ) : (
                <X className="mt-0.5 size-4 shrink-0 text-destructive" strokeWidth={2.5} />
              )}
              <span className={item.ok ? "text-stone-500" : "text-ink"}>
                {item.label}
                {!item.ok && (
                  <Link href={`/instructor/courses/${course.id}/${stepFor(item.group)}`} onClick={onClose} className="ml-2 font-semibold text-navy-800 hover:underline">
                    Fix
                  </Link>
                )}
              </span>
            </li>
          ))}
        </ul>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Not yet
          </Button>
          <Button
            disabled={!ready || submit.isPending}
            onClick={() =>
              submit.mutate(undefined, {
                onSuccess: onClose,
                onError: (e) => setError(e instanceof ApiRequestError ? e.message : "Something went wrong."),
              })
            }
          >
            {submit.isPending ? (
              "Submitting…"
            ) : (
              <>
                <Clock3 /> Submit for review
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** Shared heading for each builder step. */
export function StepHeader({ title, children }: { title: string; children?: React.ReactNode }) {
  return (
    <div className="mb-8 border-b border-line pb-6">
      <h1 className="text-h2">{title}</h1>
      {children && <p className="mt-2 max-w-[65ch] text-sm leading-relaxed text-stone-700">{children}</p>}
    </div>
  );
}

export function useEditableCourse() {
  const { id } = useParams<{ id: string }>();
  const query = useCourseEditor(id);
  const course = query.data;
  const locked = course?.status === "in_review" || course?.status === "archived";
  return { id, course, locked };
}
