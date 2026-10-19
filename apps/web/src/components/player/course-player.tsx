"use client";

import Link from "next/link";
import { useParams, usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import { Award, ChevronLeft, ChevronRight, Download, Eye, FileText, Lock } from "lucide-react";
import { Logo } from "@/components/brand/logo";
import { Button, buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ApiRequestError } from "@/lib/api/client";
import { flattenLectures, resumeLectureId, useLearnCourse, useLectureContent, useProgress, type LearnCourse } from "@/lib/learning/api";
import { formatDuration, plural } from "@/lib/format";
import { cn } from "@/lib/utils";
import { AnnouncementsPanel } from "@/components/engagement/announcements-panel";
import { NotificationBell } from "@/components/engagement/notification-bell";
import { MyReview } from "@/components/engagement/my-review";
import { QaPanel } from "@/components/engagement/qa-panel";
import { ArticleView } from "./article-view";
import { AssignmentView } from "./assignment-view";
import { CurriculumSidebar } from "./curriculum-sidebar";
import { NotesPanel } from "./notes-panel";
import { QuizView } from "./quiz-view";
import { VideoPlayer } from "./video-player";

const UP_NEXT_SECONDS = 5;

export function CoursePlayer() {
  const { slug } = useParams<{ slug: string }>();
  const router = useRouter();
  const pathname = usePathname();
  const query = useLearnCourse(slug);

  useEffect(() => {
    if (query.error instanceof ApiRequestError && query.error.status === 401) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [query.error, router, pathname]);

  if (query.isError) {
    const e = query.error;
    if (e instanceof ApiRequestError && e.code === "NOT_ENROLLED") {
      return (
        <Blocked title="You’re not enrolled in this course" body="Enroll to watch the lectures, track your progress and take notes.">
          <Link href={`/course/${slug}`} className={buttonVariants({ size: "lg" })}>
            View course
          </Link>
        </Blocked>
      );
    }
    if (e instanceof ApiRequestError && e.status === 401) return <div className="h-dvh bg-navy-950" />;
    return (
      <Blocked title={e instanceof ApiRequestError && e.status === 404 ? "Course not found" : "We couldn’t open this course"} body="Check the link, or find the course in My learning.">
        <Link href="/my-learning" className={buttonVariants({ size: "lg", variant: "outline" })}>
          My learning
        </Link>
      </Blocked>
    );
  }

  if (!query.data) return <PlayerSkeleton />;
  return <Player course={query.data} />;
}

function Player({ course }: { course: LearnCourse }) {
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams();
  const progress = useProgress(course.slug);
  const tracked = course.role === "learner";

  const lectures = useMemo(() => flattenLectures(course), [course]);
  const requested = Number(search.get("lecture")) || null;
  const currentId = requested && lectures.some((l) => l.id === requested) ? requested : resumeLectureId(course);
  const index = lectures.findIndex((l) => l.id === currentId);
  const current = index >= 0 ? lectures[index]! : null;
  const prev = index > 0 ? lectures[index - 1] : undefined;
  const next = index >= 0 && index < lectures.length - 1 ? lectures[index + 1] : undefined;

  const positionRef = useRef(0);
  const [seek, setSeek] = useState<{ to: number; at: number } | null>(null);
  const [autoPlay, setAutoPlay] = useState(false);
  const [upNext, setUpNext] = useState<number | null>(null);
  // The tab and open Q&A thread live in the URL, so notification links land on them.
  const tab = search.get("tab") ?? "overview";
  const questionId = Number(search.get("question")) || null;
  const setParams = (patch: Record<string, string | null>) => {
    const params = new URLSearchParams(search.toString());
    for (const [k, v] of Object.entries(patch)) {
      if (v === null) params.delete(k);
      else params.set(k, v);
    }
    params.delete("t");
    router.replace(`${pathname}?${params}`, { scroll: false });
  };
  const setTab = (value: string) => setParams({ tab: value === "overview" ? null : value, question: null });

  const go = (lectureId: number, opts: { play?: boolean; at?: number } = {}) => {
    setUpNext(null);
    setAutoPlay(opts.play ?? true);
    if (lectureId === currentId && opts.at !== undefined) {
      setSeek({ to: opts.at, at: Date.now() });
      return;
    }
    const params = new URLSearchParams({ lecture: String(lectureId) });
    if (search.get("tab")) params.set("tab", search.get("tab")!);
    if (opts.at) params.set("t", String(opts.at));
    router.push(`${pathname}?${params}`, { scroll: false });
  };

  // "Up next" countdown after a video ends.
  useEffect(() => {
    if (upNext === null || !next) return;
    const id = setTimeout(() => (upNext <= 1 ? go(next.id) : setUpNext(upNext - 1)), 1000);
    return () => clearTimeout(id);
    // `go` is recreated each render; the countdown only depends on these.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [upNext, next]);

  const toggle = (lectureId: number, completed: boolean) => void progress.setCompleted(lectureId, completed).catch(() => {});
  const save = (seconds: number, { leaving }: { leaving: boolean }) => {
    if (!tracked || !current || seconds < 1) return;
    if (leaving) progress.beaconPosition(current.id, seconds);
    else void progress.savePosition(current.id, seconds).catch(() => {});
  };

  // Resume where the learner left off, unless they'd practically finished.
  const t = Number(search.get("t"));
  const startAt = Number.isFinite(t) && t > 0 ? t : current && current.last_position_seconds < current.duration_seconds - 10 ? current.last_position_seconds : 0;
  const percent = course.enrollment?.progress_percent ?? 0;

  return (
    <div className="flex h-dvh flex-col">
      <header className="dark flex h-14 shrink-0 items-center gap-4 border-b border-white/10 bg-navy-950 px-4 text-foreground">
        <Logo inverted className="shrink-0 scale-90" />
        <span className="hidden h-6 w-px bg-white/15 md:block" />
        <Link href={`/course/${course.slug}`} className="hidden min-w-0 items-center gap-1 text-sm font-semibold text-white/90 hover:text-white md:flex">
          <ChevronLeft className="size-4 shrink-0" />
          <span className="truncate">{course.title}</span>
        </Link>
        <div className="ml-auto flex items-center gap-4 text-sm text-white/80">
          {course.enrollment?.certificate_code && (
            <Link
              href={`/certificates/${course.enrollment.certificate_code}`}
              className="hidden items-center gap-1.5 rounded-sm bg-gold-500 px-2.5 py-1 text-xs font-bold text-navy-950 hover:bg-gold-400 sm:inline-flex"
            >
              <Award className="size-3.5" /> Your certificate
            </Link>
          )}
          {tracked ? (
            <ProgressRing percent={percent} label={`${course.enrollment?.completed_count ?? 0} of ${course.lecture_count} complete`} />
          ) : (
            <span className="inline-flex items-center gap-1.5 rounded-sm bg-white/10 px-2.5 py-1 text-xs font-semibold text-white">
              <Eye className="size-3.5" /> {course.role === "instructor" ? "Instructor preview" : "Reviewer preview"} · progress isn’t saved
            </span>
          )}
          <NotificationBell inverted />
        </div>
      </header>

      <div className="grid min-h-0 flex-1 lg:grid-cols-[1fr_380px]">
        <main className="min-h-0 overflow-y-auto bg-paper">
          {current ? (
            <div className="relative">
              {current.kind === "video" ? (
                current.has_video ? (
                  <div className="relative">
                    <VideoPlayer
                      key={current.id}
                      lectureId={current.id}
                      title={`${current.number}. ${current.title}`}
                      startAt={startAt}
                      autoPlay={autoPlay}
                      positionRef={positionRef}
                      seek={seek}
                      onSave={save}
                      onEnded={() => {
                        if (next) setUpNext(UP_NEXT_SECONDS);
                      }}
                    />
                    {upNext !== null && next && (
                      <UpNext
                        title={`${next.number}. ${next.title}`}
                        seconds={upNext}
                        onPlay={() => go(next.id)}
                        onCancel={() => setUpNext(null)}
                      />
                    )}
                  </div>
                ) : (
                  <div className="flex aspect-video items-center justify-center bg-navy-950 text-sm text-white/70">This video is still being prepared.</div>
                )
              ) : current.kind === "quiz" ? (
                <QuizView
                  key={current.id}
                  slug={course.slug}
                  lectureId={current.id}
                  title={`${current.number}. ${current.title}`}
                  tracked={tracked}
                  onNext={next ? () => go(next.id, { play: false }) : undefined}
                />
              ) : current.kind === "assignment" ? (
                <AssignmentView key={current.id} slug={course.slug} lectureId={current.id} title={`${current.number}. ${current.title}`} tracked={tracked} />
              ) : (
                <ArticleView
                  key={current.id}
                  lectureId={current.id}
                  title={`${current.number}. ${current.title}`}
                  completed={current.completed}
                  tracked={tracked}
                  onComplete={() => toggle(current.id, true)}
                />
              )}

              <div className="flex items-center gap-3 border-b border-line bg-white px-4 py-2.5 sm:px-6">
                <p className="min-w-0 flex-1 truncate text-sm">
                  <span className="text-stone-500">Lecture {current.number}:</span> <span className="font-semibold text-ink">{current.title}</span>
                </p>
                <Button variant="ghost" size="sm" disabled={!prev} onClick={() => prev && go(prev.id, { play: false })} aria-label="Previous lecture">
                  <ChevronLeft /> <span className="hidden sm:inline">Previous</span>
                </Button>
                <Button variant="ghost" size="sm" disabled={!next} onClick={() => next && go(next.id, { play: false })} aria-label="Next lecture">
                  <span className="hidden sm:inline">Next</span> <ChevronRight />
                </Button>
              </div>
            </div>
          ) : (
            <div className="flex aspect-video items-center justify-center bg-navy-950 text-sm text-white/70">This course has no lectures yet.</div>
          )}

          <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
            <Tabs value={tab} onValueChange={(v) => setTab(String(v))}>
              <TabsList variant="line" className="w-full justify-start gap-6 overflow-x-auto overflow-y-hidden border-b border-line">
                <TabsTrigger value="content" className="flex-none px-0 text-[0.95rem] font-semibold lg:hidden">
                  Course content
                </TabsTrigger>
                <TabsTrigger value="overview" className="flex-none px-0 text-[0.95rem] font-semibold">
                  Overview
                </TabsTrigger>
                <TabsTrigger value="qa" className="flex-none px-0 text-[0.95rem] font-semibold">
                  Q&amp;A
                </TabsTrigger>
                <TabsTrigger value="notes" className="flex-none px-0 text-[0.95rem] font-semibold">
                  Notes
                </TabsTrigger>
                <TabsTrigger value="announcements" className="flex-none px-0 text-[0.95rem] font-semibold">
                  Announcements
                </TabsTrigger>
                {tracked && (
                  <TabsTrigger value="reviews" className="flex-none px-0 text-[0.95rem] font-semibold">
                    Reviews
                  </TabsTrigger>
                )}
                <TabsTrigger value="resources" className="flex-none px-0 text-[0.95rem] font-semibold">
                  Resources{current && current.resource_count > 0 && <span className="ml-1.5 text-xs text-stone-500">{current.resource_count}</span>}
                </TabsTrigger>
              </TabsList>

              <TabsContent value="content" className="-mx-4 pt-2 sm:-mx-6 lg:hidden">
                <CurriculumSidebar course={course} currentId={currentId} tracked={tracked} onSelect={(id) => go(id, { play: false })} onToggle={toggle} />
              </TabsContent>

              <TabsContent value="overview" className="pt-6">
                <Overview course={course} />
              </TabsContent>

              <TabsContent value="notes" className="pt-6">
                <NotesPanel
                  slug={course.slug}
                  lecture={current}
                  numberOf={(id) => lectures.find((l) => l.id === id)?.number}
                  currentTime={() => positionRef.current}
                  onJump={(lectureId, seconds) => {
                    go(lectureId, { at: seconds });
                    window.scrollTo({ top: 0 });
                  }}
                />
              </TabsContent>

              <TabsContent value="qa" className="pt-6">
                <QaPanel
                  slug={course.slug}
                  lecture={current ? { id: current.id, title: current.title, number: current.number } : null}
                  canModerate={!tracked}
                  questionId={questionId}
                  onOpenQuestion={(id) => setParams({ question: id === null ? null : String(id) })}
                />
              </TabsContent>

              <TabsContent value="announcements" className="pt-6">
                <AnnouncementsPanel slug={course.slug} />
              </TabsContent>

              {tracked && (
                <TabsContent value="reviews" className="pt-6">
                  <div className="mx-auto max-w-xl">
                    <MyReview slug={course.slug} instructorName={course.instructor.name} />
                  </div>
                </TabsContent>
              )}

              <TabsContent value="resources" className="pt-6">
                {current && <Resources lectureId={current.id} count={current.resource_count} />}
              </TabsContent>
            </Tabs>
          </div>
        </main>

        <div className="hidden min-h-0 border-l border-line lg:block">
          <CurriculumSidebar course={course} currentId={currentId} tracked={tracked} onSelect={(id) => go(id, { play: false })} onToggle={toggle} />
        </div>
      </div>
    </div>
  );
}

function Overview({ course }: { course: LearnCourse }) {
  return (
    <div>
      <h1 className="text-h2">{course.title}</h1>
      {course.subtitle && <p className="mt-2 max-w-[70ch] text-stone-700">{course.subtitle}</p>}
      <div className="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm text-stone-700">
        <span>
          <strong>{plural(course.lecture_count, "lecture")}</strong>
        </span>
        <span>
          <strong>{formatDuration(course.duration_seconds)}</strong> total
        </span>
        <span>
          by{" "}
          {course.instructor.slug ? (
            <Link href={`/instructors/${course.instructor.slug}`} className="font-semibold text-navy-800 underline-offset-2 hover:underline">
              {course.instructor.name}
            </Link>
          ) : (
            <strong>{course.instructor.name}</strong>
          )}
        </span>
      </div>
      {course.description && (
        <>
          <h2 className="mt-8 mb-2 font-sans text-base font-bold text-ink">About this course</h2>
          {/* Sanitised server-side on save. */}
          <div className="prose-content max-w-[70ch] text-stone-700" dangerouslySetInnerHTML={{ __html: course.description }} />
        </>
      )}
    </div>
  );
}

function Resources({ lectureId, count }: { lectureId: number; count: number }) {
  const content = useLectureContent(count > 0 ? lectureId : null);
  if (count === 0) return <p className="py-6 text-center text-sm text-stone-500">No downloadable files for this lecture.</p>;
  if (!content.data) return <Skeleton className="h-16 w-full" />;

  return (
    <ul className="divide-y divide-line rounded-md border border-line bg-white">
      {content.data.resources.map((r) => (
        <li key={r.id}>
          <a href={r.download_url} className="flex items-center gap-3 px-4 py-3 text-sm hover:bg-paper">
            <FileText className="size-4 shrink-0 text-stone-500" />
            <span className="flex-1 truncate font-semibold text-navy-800">{r.name}</span>
            <span className="tabular text-xs text-stone-500">{formatBytes(r.size_bytes)}</span>
            <Download className="size-4 text-stone-500" />
          </a>
        </li>
      ))}
    </ul>
  );
}

function formatBytes(n: number): string {
  if (n < 1024 * 1024) return `${Math.max(1, Math.round(n / 1024))} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

function UpNext({ title, seconds, onPlay, onCancel }: { title: string; seconds: number; onPlay: () => void; onCancel: () => void }) {
  return (
    <div className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 bg-navy-950/90 px-6 text-center text-white" role="dialog" aria-label="Up next">
      <p className="text-xs font-bold tracking-[0.2em] text-white/60 uppercase">Up next in {seconds}</p>
      <p className="max-w-md font-serif text-xl font-semibold sm:text-2xl">{title}</p>
      <div className="flex gap-3">
        <Button onClick={onPlay} className="bg-white text-navy-900 hover:bg-white/90">
          Play now
        </Button>
        <Button variant="outline" onClick={onCancel} className="border-white/40 bg-transparent text-white hover:bg-white/10">
          Cancel
        </Button>
      </div>
    </div>
  );
}

export function ProgressRing({ percent, label, className }: { percent: number; label?: string; className?: string }) {
  return (
    <div className={cn("flex items-center gap-2", className)} title={label}>
      <svg viewBox="0 0 36 36" className="size-8 -rotate-90" aria-hidden>
        <circle cx="18" cy="18" r="15" fill="none" stroke="rgb(255 255 255 / 0.15)" strokeWidth="3" />
        <circle cx="18" cy="18" r="15" fill="none" stroke="var(--gold-400)" strokeWidth="3" strokeLinecap="round" strokeDasharray={`${(percent / 100) * 94.2} 94.2`} />
      </svg>
      <span className="tabular hidden sm:inline">{percent}% complete</span>
      <span className="sr-only">{label}</span>
    </div>
  );
}

function Blocked({ title, body, children }: { title: string; body: string; children: React.ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col items-center justify-center bg-paper px-6 text-center">
      <Lock className="size-10 text-gold-600" strokeWidth={1.5} />
      <h1 className="text-h3 mt-4">{title}</h1>
      <p className="mt-2 max-w-sm text-sm text-stone-700">{body}</p>
      <div className="mt-6">{children}</div>
    </div>
  );
}

export function PlayerSkeleton() {
  return (
    <div className="flex h-dvh flex-col">
      <div className="h-14 shrink-0 bg-navy-950" />
      <div className="grid min-h-0 flex-1 lg:grid-cols-[1fr_380px]">
        <div>
          <div className="aspect-video w-full bg-navy-950/90" />
          <div className="space-y-3 p-6">
            <Skeleton className="h-8 w-72" />
            <Skeleton className="h-4 w-full max-w-xl" />
          </div>
        </div>
        <div className="hidden border-l border-line bg-white p-5 lg:block">
          <Skeleton className="h-6 w-40" />
          <Skeleton className="mt-4 h-40 w-full" />
        </div>
      </div>
    </div>
  );
}

