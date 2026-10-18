import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { ChevronLeft, Share2, Star } from "lucide-react";
import { Logo } from "@/components/brand/logo";
import { CurriculumSidebar } from "@/components/player/curriculum-sidebar";
import { VideoStage } from "@/components/player/video-stage";
import { Rating } from "@/components/course/rating";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { courses, getCourse } from "@/lib/demo-data";
import { formatCount, formatDuration } from "@/lib/format";

type Params = Promise<{ slug: string }>;

export const metadata: Metadata = { title: "Learning", robots: { index: false } };

export function generateStaticParams() {
  return courses.slice(0, 1).map((c) => ({ slug: c.slug }));
}

export default function LearnPage({ params }: { params: Params }) {
  return (
    <Suspense fallback={<div className="h-dvh bg-navy-950" />}>
      <Player params={params} />
    </Suspense>
  );
}

const CURRENT = 9; // "State, props and data flow"
const DONE = 9;

async function Player({ params }: { params: Params }) {
  const { slug } = await params;
  const course = getCourse(slug);
  if (!course) notFound();
  const sections = course.curriculum.length ? course.curriculum : courses[0]!.curriculum;
  const lectures = sections.flatMap((s) => s.lectures);
  const current = lectures[CURRENT]!;
  const percent = Math.round((DONE / lectures.length) * 100);

  return (
    <div className="flex h-dvh flex-col">
      {/* Top bar */}
      <header className="dark flex h-14 shrink-0 items-center gap-4 border-b border-white/10 bg-navy-950 px-4 text-foreground">
        <Logo inverted className="scale-90" />
        <span className="hidden h-6 w-px bg-white/15 md:block" />
        <Link
          href={`/course/${course.slug}`}
          className="hidden min-w-0 items-center gap-1 truncate text-sm font-semibold text-white/90 hover:text-white md:flex"
        >
          <ChevronLeft className="size-4 shrink-0" />
          <span className="truncate">{course.title}</span>
        </Link>
        <div className="ml-auto flex items-center gap-5 text-sm text-white/80">
          <button type="button" className="hidden items-center gap-1.5 hover:text-white sm:flex">
            <Star className="size-4" /> Leave a rating
          </button>
          <div className="flex items-center gap-2" aria-label={`${percent}% complete`}>
            <svg viewBox="0 0 36 36" className="size-8 -rotate-90">
              <circle cx="18" cy="18" r="15" fill="none" stroke="rgb(255 255 255 / 0.15)" strokeWidth="3" />
              <circle
                cx="18"
                cy="18"
                r="15"
                fill="none"
                stroke="var(--gold-400)"
                strokeWidth="3"
                strokeLinecap="round"
                strokeDasharray={`${(percent / 100) * 94.2} 94.2`}
              />
            </svg>
            <span className="tabular hidden sm:inline">{percent}% complete</span>
          </div>
          <button type="button" aria-label="Share" className="hover:text-white">
            <Share2 className="size-4" />
          </button>
        </div>
      </header>

      <div className="grid min-h-0 flex-1 lg:grid-cols-[1fr_380px]">
        <main className="min-h-0 overflow-y-auto">
          <VideoStage poster={course.image.src} title={`${CURRENT + 1}. ${current.title}`} duration={current.seconds} />

          <div className="mx-auto max-w-4xl px-6 py-6">
            <Tabs defaultValue="overview">
              <TabsList variant="line" className="w-full justify-start gap-6 border-b border-line">
                {["Overview", "Q&A", "Notes", "Announcements", "Reviews"].map((t) => (
                  <TabsTrigger key={t} value={t.toLowerCase()} className="flex-none px-0 text-[0.95rem] font-semibold">
                    {t}
                  </TabsTrigger>
                ))}
              </TabsList>

              <TabsContent value="overview" className="pt-6">
                <h1 className="text-h2">{course.title}</h1>
                <div className="mt-4 flex flex-wrap gap-x-8 gap-y-3 text-sm">
                  <Rating value={course.rating} count={course.ratingCount} />
                  <span className="text-stone-700">
                    <strong className="tabular">{formatCount(course.students)}</strong> learners
                  </span>
                  <span className="text-stone-700">
                    <strong>{formatDuration(course.seconds)}</strong> total
                  </span>
                </div>
                <p className="mt-6 max-w-[70ch] leading-relaxed text-stone-700">{course.subtitle}</p>
              </TabsContent>

              <TabsContent value="q&a" className="pt-6">
                <EmptyTab title="No questions yet" body="Ask the instructor or other learners about this lecture." />
              </TabsContent>
              <TabsContent value="notes" className="pt-6">
                <EmptyTab title="Take timestamped notes" body="Notes are saved at the current video time and stay private to you." />
              </TabsContent>
              <TabsContent value="announcements" className="pt-6">
                <EmptyTab title="No announcements" body="Updates from the instructor will appear here." />
              </TabsContent>
              <TabsContent value="reviews" className="pt-6">
                <EmptyTab title="Reviews" body="Course reviews from learners will appear here." />
              </TabsContent>
            </Tabs>
          </div>
        </main>

        <div className="hidden min-h-0 lg:block">
          <CurriculumSidebar sections={sections} done={DONE} current={CURRENT} />
        </div>
      </div>
    </div>
  );
}

function EmptyTab({ title, body }: { title: string; body: string }) {
  return (
    <div className="rounded-md border border-dashed border-line px-6 py-10 text-center">
      <p className="font-semibold text-ink">{title}</p>
      <p className="mt-1 text-sm text-stone-500">{body}</p>
    </div>
  );
}
