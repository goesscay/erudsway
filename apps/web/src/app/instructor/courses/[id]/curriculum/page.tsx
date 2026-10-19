import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import {
  BarChart3,
  Check,
  ChevronLeft,
  CircleAlert,
  FileText,
  GripVertical,
  IndianRupee,
  ListChecks,
  Loader2,
  MessageSquare,
  MonitorPlay,
  Pencil,
  Plus,
  Star,
  Trash2,
  Upload,
  Users,
} from "lucide-react";
import { Logo } from "@/components/brand/logo";
import { Button } from "@/components/ui/button";
import { courses } from "@/lib/demo-data";
import { formatClock } from "@/lib/format";
import { cn } from "@/lib/utils";

export const metadata: Metadata = { title: "Curriculum", robots: { index: false } };

type Params = Promise<{ id: string }>;

export function generateStaticParams() {
  return [{ id: "demo" }];
}

type VideoState = "ready" | "processing" | "failed" | "none";

const states: Record<string, { state: VideoState; progress?: number }> = {
  "Valuation rules with examples": { state: "processing", progress: 64 },
  "Place of supply for goods and services": { state: "failed" },
  "Reverse charge mechanism": { state: "none" },
};

const appNav = [
  [MonitorPlay, "Courses", true],
  [BarChart3, "Performance", false],
  [IndianRupee, "Revenue", false],
  [Users, "Students", false],
  [MessageSquare, "Q&A", false],
  [Star, "Reviews", false],
] as const;

const steps = [
  { group: "Plan your course", items: [["Intended learners", true], ["Course structure", true]] },
  { group: "Create your content", items: [["Curriculum", false], ["Captions", false]] },
  { group: "Publish your course", items: [["Landing page", true], ["Pricing", true], ["Promotions", false]] },
] as const;

export default function CurriculumPage({ params }: { params: Params }) {
  const course = courses[0]!;

  return (
    <div className="flex min-h-dvh">
      {/* App rail */}
      <nav aria-label="Instructor" className="dark sticky top-0 hidden h-dvh w-60 shrink-0 flex-col bg-navy-950 px-4 py-5 text-foreground md:flex">
        <Logo inverted />
        <p className="mt-8 mb-2 px-3 text-[0.65rem] font-bold tracking-[0.2em] text-white/40 uppercase">Instructor</p>
        <ul className="space-y-0.5">
          {appNav.map(([Icon, label, active]) => (
            <li key={label}>
              <Link
                href="#"
                className={cn(
                  "flex items-center gap-3 rounded-md px-3 py-2.5 text-sm",
                  active ? "bg-white/10 font-semibold text-white" : "text-white/65 hover:bg-white/5 hover:text-white",
                )}
              >
                <Icon className={cn("size-4", active && "text-gold-400")} />
                {label}
              </Link>
            </li>
          ))}
        </ul>
      </nav>

      <div className="flex min-w-0 flex-1 flex-col">
        {/* Course bar */}
        <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-line bg-white px-6">
          <Link href="#" className="flex items-center gap-1 text-sm font-semibold text-stone-700 hover:text-navy-900">
            <ChevronLeft className="size-4" /> Back to courses
          </Link>
          <span className="h-6 w-px bg-line" />
          <h1 className="truncate font-sans text-base font-bold text-ink">{course.title}</h1>
          <span className="rounded-sm bg-paper-2 px-2 py-0.5 text-[0.7rem] font-bold tracking-wide text-stone-700 uppercase ring-1 ring-line">
            Draft
          </span>
          <span className="ml-auto hidden text-xs text-stone-500 sm:block">All changes saved</span>
          <Button variant="outline">Preview</Button>
          <Button>Submit for review</Button>
        </header>

        <div className="mx-auto grid w-full max-w-6xl gap-10 px-6 py-10 lg:grid-cols-[220px_1fr]">
          {/* Builder steps */}
          <nav aria-label="Course builder steps" className="space-y-7">
            {steps.map((g) => (
              <div key={g.group}>
                <p className="mb-2 text-sm font-bold text-ink">{g.group}</p>
                <ul className="space-y-0.5 border-l border-line">
                  {g.items.map(([label, complete]) => {
                    const active = label === "Curriculum";
                    return (
                      <li key={label}>
                        <Link
                          href="#"
                          aria-current={active ? "page" : undefined}
                          className={cn(
                            "-ml-px flex items-center gap-2.5 border-l-2 py-1.5 pl-4 text-sm",
                            active ? "border-gold-500 font-semibold text-navy-900" : "border-transparent text-stone-700 hover:text-navy-900",
                          )}
                        >
                          <span
                            className={cn(
                              "flex size-4 items-center justify-center rounded-full ring-1",
                              complete ? "bg-success ring-success" : "ring-stone-500/50",
                            )}
                          >
                            {complete && <Check className="size-2.5 text-white" strokeWidth={3.5} />}
                          </span>
                          {label}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))}
          </nav>

          <main>
            <div className="border-b border-line pb-6">
              <h2 className="text-h2">Curriculum</h2>
              <p className="mt-2 max-w-[65ch] text-sm leading-relaxed text-stone-700">
                Organise lectures into sections. Videos are processed into multiple qualities after upload — you can
                keep working while they process. Drag the handle to reorder.
              </p>
            </div>

            <Suspense>
              <CourseIdNote params={params} />
            </Suspense>

            <ol className="mt-8 space-y-5">
              {course.curriculum.slice(0, 3).map((section, s) => (
                <li key={section.title} className="rounded-md border border-line bg-white">
                  <div className="flex items-center gap-3 border-b border-line px-4 py-3">
                    <GripVertical className="size-4 cursor-grab text-stone-500" aria-label="Reorder section" />
                    <p className="flex-1 text-sm">
                      <span className="font-bold text-ink">Section {s + 1}:</span>{" "}
                      <span className="text-stone-700">{section.title}</span>
                    </p>
                    <IconAction label="Edit section" icon={Pencil} />
                    <IconAction label="Delete section" icon={Trash2} />
                  </div>

                  <ol className="space-y-2 p-3">
                    {section.lectures.map((l, i) => {
                      const meta = l.kind === "video" ? (states[l.title] ?? { state: "ready" as const }) : null;
                      const KindIcon = l.kind === "quiz" ? ListChecks : l.kind === "article" ? FileText : MonitorPlay;
                      return (
                        <li
                          key={l.title}
                          className="group flex items-center gap-3 rounded-sm border border-line bg-paper/60 px-3 py-2.5 hover:border-input"
                        >
                          <GripVertical className="size-4 cursor-grab text-stone-500/70" aria-label="Reorder lecture" />
                          <KindIcon className="size-4 text-stone-500" strokeWidth={1.75} />
                          <span className="flex-1 truncate text-sm text-ink">
                            <span className="text-stone-500">
                              {l.kind === "quiz" ? "Quiz" : "Lecture"} {i + 1}:
                            </span>{" "}
                            {l.title}
                          </span>
                          {meta && <VideoStatus {...meta} seconds={l.seconds} />}
                          {l.preview && (
                            <span className="rounded-sm bg-navy-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-navy-800">
                              FREE PREVIEW
                            </span>
                          )}
                          <span className="flex opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                            <IconAction label="Edit lecture" icon={Pencil} />
                            <IconAction label="Delete lecture" icon={Trash2} />
                          </span>
                        </li>
                      );
                    })}
                  </ol>

                  <div className="px-3 pb-3">
                    <button
                      type="button"
                      className="flex w-full items-center gap-2 rounded-sm border border-dashed border-input px-3 py-2.5 text-sm font-semibold text-navy-900 hover:border-navy-900 hover:bg-navy-100/40"
                    >
                      <Plus className="size-4" /> Add curriculum item
                    </button>
                  </div>
                </li>
              ))}
            </ol>

            <Button variant="outline" className="mt-5">
              <Plus /> Add section
            </Button>
          </main>
        </div>
      </div>
    </div>
  );
}

/** Demonstrates the params-in-Suspense pattern; real data loads by course id in Phase 3. */
async function CourseIdNote({ params }: { params: Params }) {
  const { id } = await params;
  return <p className="sr-only">Editing course {id}</p>;
}

function VideoStatus({ state, progress, seconds }: { state: VideoState; progress?: number; seconds: number }) {
  if (state === "processing") {
    return (
      <span className="inline-flex items-center gap-2 text-xs text-stone-700">
        <Loader2 className="size-3.5 animate-spin text-gold-600" />
        Processing
        <span className="h-1 w-16 overflow-hidden rounded-full bg-line">
          <span className="block h-full bg-gold-500" style={{ width: `${progress}%` }} />
        </span>
        <span className="tabular w-8">{progress}%</span>
      </span>
    );
  }
  if (state === "failed") {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-destructive">
        <CircleAlert className="size-3.5" /> Processing failed — <button className="underline">re-upload</button>
      </span>
    );
  }
  if (state === "none") {
    return (
      <Button size="xs" variant="outline">
        <Upload /> Upload video
      </Button>
    );
  }
  return <span className="tabular text-xs text-stone-500">{formatClock(seconds)}</span>;
}

function IconAction({ label, icon: Icon }: { label: string; icon: typeof Pencil }) {
  return (
    <button
      type="button"
      aria-label={label}
      className="rounded-sm p-1.5 text-stone-500 hover:bg-paper-2 hover:text-navy-900"
    >
      <Icon className="size-3.5" />
    </button>
  );
}
