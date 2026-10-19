"use client";

import Image from "next/image";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, CircleAlert, FileText, MonitorPlay, X } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { formatClock, formatDuration, formatPaise, plural } from "@/lib/format";
import { levelLabel } from "@/lib/catalog/types";
import { cn } from "@/lib/utils";

type Submission = components["schemas"]["CourseSubmissionResource"];
type Status = "pending" | "approved" | "rejected";

const tabs: { value: Status; label: string }[] = [
  { value: "pending", label: "Awaiting review" },
  { value: "approved", label: "Approved" },
  { value: "rejected", label: "Changes requested" },
];

export default function CourseReviewsPage() {
  return <RequireAuth permission="moderate_courses">{() => <Queue />}</RequireAuth>;
}

function Queue() {
  const [status, setStatus] = useState<Status>("pending");
  const [selected, setSelected] = useState<Submission | null>(null);
  const list = useQuery({
    queryKey: ["admin", "course-submissions", status],
    queryFn: () => unwrap(api.GET("/admin/course-submissions", { params: { query: { status } } })),
  });
  const rows = (list.data?.data ?? []) as Submission[];

  return (
    <>
      <AdminPageHeader title="Course reviews" description="Every course is checked before learners can see it." />
      <div className="px-8 py-6">
        <div role="tablist" aria-label="Review status" className="mb-6 flex gap-6 border-b border-line">
          {tabs.map((t) => (
            <button
              key={t.value}
              role="tab"
              aria-selected={status === t.value}
              onClick={() => setStatus(t.value)}
              className={cn("-mb-px border-b-2 pb-3 text-sm font-semibold", status === t.value ? "border-gold-500 text-navy-900" : "border-transparent text-stone-500 hover:text-navy-900")}
            >
              {t.label}
            </button>
          ))}
        </div>

        {list.isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load submissions.</FormAlert>
        ) : rows.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">
            {status === "pending" ? "No courses are waiting for review." : "Nothing here yet."}
          </div>
        ) : (
          <ul className="space-y-3">
            {rows.map((s) => (
              <li key={s.id} className="grid grid-cols-[140px_1fr_auto] items-center gap-5 rounded-md border border-line bg-white p-4">
                <div className="relative aspect-video overflow-hidden rounded-sm bg-paper-2">
                  {s.course.thumbnail_url && <Image src={s.course.thumbnail_url} alt="" fill sizes="140px" className="object-cover" />}
                </div>
                <div className="min-w-0">
                  <p className="truncate font-semibold text-ink">{s.course.title}</p>
                  <p className="mt-0.5 text-xs text-stone-500">
                    {s.instructor.name} · {s.course.category} · {plural(s.course.lecture_count, "lecture")} · {formatDuration(s.course.duration_seconds)}
                  </p>
                  <p className="mt-0.5 text-xs text-stone-500">
                    Submitted {formatDate(s.submitted_at)}
                    {s.decided_at && <> · decided {formatDate(s.decided_at)} by {s.reviewer?.name}</>}
                  </p>
                </div>
                <Button size="sm" variant={status === "pending" ? "default" : "outline"} onClick={() => setSelected(s)}>
                  {status === "pending" ? "Review" : "View"}
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <Sheet open={!!selected} onOpenChange={(o) => !o && setSelected(null)}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
          {selected && <Review submission={selected} onDone={() => setSelected(null)} />}
        </SheetContent>
      </Sheet>
    </>
  );
}

function Review({ submission, onDone }: { submission: Submission; onDone: () => void }) {
  const queryClient = useQueryClient();
  const [feedback, setFeedback] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<"approve" | "reject" | null>(null);
  const course = useQuery({
    queryKey: ["admin", "course", submission.course.id],
    queryFn: async () => (await unwrap(api.GET("/admin/courses/{course}", { params: { path: { course: submission.course.id } } }))).data,
  });

  const decide = async (decision: "approve" | "reject") => {
    setError(null);
    setBusy(decision);
    try {
      if (decision === "approve") {
        await mutate(() => api.POST("/admin/course-submissions/{submission}/approve", { params: { path: { submission: submission.id } }, body: { notes: feedback.trim() || null } }));
      } else {
        await mutate(() => api.POST("/admin/course-submissions/{submission}/reject", { params: { path: { submission: submission.id } }, body: { feedback: feedback.trim() } }));
      }
      await queryClient.invalidateQueries({ queryKey: ["admin", "course-submissions"] });
      onDone();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(null);
    }
  };

  const c = course.data;

  return (
    <>
      <SheetHeader className="border-b border-line">
        <SheetTitle className="font-sans text-lg font-bold text-ink">{submission.course.title}</SheetTitle>
        <SheetDescription>
          by {submission.instructor.name} ({submission.instructor.email})
        </SheetDescription>
      </SheetHeader>

      {!c ? (
        <div className="space-y-3 px-4">
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : (
        <div className="space-y-7 px-4 pb-8 text-sm">
          <div className="flex items-center gap-3">
            <StatusPill status={submission.status} />
            <span className="text-stone-500">
              {c.category.parent?.name} › {c.category.name} · {c.language} · {levelLabel[c.level]} · {c.price ? formatPaise(c.price.amount) : "Free"}
            </span>
          </div>

          {c.thumbnail_url && (
            <div className="relative aspect-video w-full overflow-hidden rounded-md bg-paper-2">
              <Image src={c.thumbnail_url} alt="" fill sizes="600px" className="object-cover" />
            </div>
          )}

          <Block title="Subtitle">{c.subtitle}</Block>
          <Block title="Description">
            {/* Sanitised server-side on save. */}
            <div className="prose-content text-stone-700" dangerouslySetInnerHTML={{ __html: c.description ?? "" }} />
          </Block>
          <Block title="Learning outcomes">
            <ul className="list-disc space-y-1 pl-5 text-stone-700">
              {c.outcomes.map((o) => (
                <li key={o}>{o}</li>
              ))}
            </ul>
          </Block>

          <Block title="Checklist">
            <ul className="grid gap-1 sm:grid-cols-2">
              {c.checklist.map((i) => (
                <li key={i.key} className="flex items-center gap-2 text-stone-700">
                  {i.ok ? <Check className="size-3.5 text-success" /> : <X className="size-3.5 text-destructive" />} {i.label}
                </li>
              ))}
            </ul>
          </Block>

          <Block title={`Curriculum · ${plural(c.lecture_count, "lecture")} · ${formatDuration(c.duration_seconds)}`}>
            <ol className="space-y-3">
              {c.curriculum.map((s, i) => (
                <li key={s.id}>
                  <p className="font-semibold text-ink">
                    Section {i + 1}: {s.title}
                  </p>
                  <ul className="mt-1 space-y-1">
                    {s.lectures.map((l) => (
                      <li key={l.id} className="flex items-center gap-2 text-stone-700">
                        {l.kind === "article" ? <FileText className="size-3.5 text-stone-500" /> : <MonitorPlay className="size-3.5 text-stone-500" />}
                        <span className="flex-1">{l.title}</span>
                        {l.is_free_preview && <span className="text-[0.65rem] font-bold text-navy-800">PREVIEW</span>}
                        {l.video?.status === "failed" && <CircleAlert className="size-3.5 text-destructive" />}
                        <span className="tabular text-xs text-stone-500">{formatClock(l.duration_seconds)}</span>
                      </li>
                    ))}
                  </ul>
                </li>
              ))}
            </ol>
          </Block>

          {submission.status === "pending" ? (
            <div className="space-y-3 border-t border-line pt-6">
              {error && <FormAlert>{error}</FormAlert>}
              <Label htmlFor="review-feedback" className="font-semibold text-ink">
                Feedback to the instructor
              </Label>
              <Textarea
                id="review-feedback"
                rows={5}
                value={feedback}
                onChange={(e) => setFeedback(e.target.value)}
                placeholder="Required when requesting changes: say exactly what to fix (lecture, timestamp, issue). Optional note when approving."
                className="bg-white"
              />
              <div className="flex gap-3">
                <Button onClick={() => decide("approve")} disabled={!!busy}>
                  {busy === "approve" ? "Publishing…" : "Approve and publish"}
                </Button>
                <Button variant="destructive" onClick={() => decide("reject")} disabled={!!busy || feedback.trim().length < 20}>
                  {busy === "reject" ? "Sending…" : "Request changes"}
                </Button>
              </div>
              <p className="text-xs text-stone-500">The instructor is emailed either way. Decisions are recorded in the audit log.</p>
            </div>
          ) : (
            submission.feedback && <Block title="Feedback given">{submission.feedback}</Block>
          )}
        </div>
      )}
    </>
  );
}

function Block({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section>
      <h3 className="mb-1.5 font-sans text-xs font-semibold tracking-wide text-stone-500 uppercase">{title}</h3>
      <div className="text-ink">{children}</div>
    </section>
  );
}
