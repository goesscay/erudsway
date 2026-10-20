"use client";

import { Check, ChevronDown, ClipboardList, FileText, FolderOpen, ListChecks, Lock, PlayCircle } from "lucide-react";
import type { LearnCourse, LearnLecture } from "@/lib/learning/api";
import { formatClock, formatDuration } from "@/lib/format";
import { cn } from "@/lib/utils";

const icon: Record<LearnLecture["kind"], typeof PlayCircle> = {
  video: PlayCircle,
  article: FileText,
  quiz: ListChecks,
  assignment: ClipboardList,
};

export function lectureLength(l: Pick<LearnLecture, "kind" | "duration_seconds">): string {
  if (l.kind === "article") return `${Math.max(1, Math.round(l.duration_seconds / 60))} min read`;
  return formatClock(l.duration_seconds);
}

/**
 * Course outline beside the player. Learners tick lectures off here; instructors and
 * reviewers previewing see the same list without checkboxes.
 */
export function CurriculumSidebar({
  course,
  currentId,
  tracked,
  onSelect,
  onToggle,
  className,
}: {
  course: LearnCourse;
  currentId: number | null;
  tracked: boolean;
  onSelect: (lectureId: number) => void;
  onToggle: (lectureId: number, completed: boolean) => void;
  className?: string;
}) {
  // Number of the first lecture in each section (lectures are numbered across the course).
  const offsets = course.curriculum.map((_, s) => course.curriculum.slice(0, s).reduce((n, sec) => n + sec.lectures.length, 0));

  return (
    <nav aria-label="Course content" className={cn("flex h-full flex-col bg-white", className)}>
      <h2 className="border-b border-line px-5 py-4 font-sans text-base font-bold text-ink">Course content</h2>
      <div className="flex-1 overflow-y-auto">
        {course.curriculum.map((section, s) => {
          const total = section.lectures.reduce((n, l) => n + l.duration_seconds, 0);
          const done = section.lectures.filter((l) => l.completed).length;
          const holdsCurrent = section.lectures.some((l) => l.id === currentId);
          return (
            <details key={section.id} open={s === 0 || holdsCurrent} className="group border-b border-line">
              <summary className="flex cursor-pointer list-none items-start gap-3 bg-paper-2/60 px-5 py-3.5 hover:bg-paper-2 [&::-webkit-details-marker]:hidden">
                <span className="flex-1">
                  <span className="block text-sm font-bold text-ink">
                    Section {s + 1}: {section.title}
                  </span>
                  <span className="tabular text-xs text-stone-500">
                    {tracked ? `${done} / ${section.lectures.length}` : `${section.lectures.length} lectures`} · {formatDuration(total)}
                  </span>
                </span>
                <ChevronDown className="mt-0.5 size-4 text-stone-500 transition-transform group-open:rotate-180" />
              </summary>
              <ol>
                {section.lectures.map((l, i) => {
                  const n = offsets[s]! + i + 1;
                  const isCurrent = l.id === currentId;
                  const Icon = icon[l.kind];
                  return (
                    <li key={l.id} className={cn("flex gap-3 px-5 py-3 text-sm", isCurrent ? "bg-navy-100/70" : "hover:bg-paper")}>
                      {tracked && !l.locked_until && (l.kind === "quiz" || l.kind === "assignment") && (
                        // Completed by passing or handing in, so it's a status, not a checkbox.
                        <span
                          role="img"
                          aria-label={l.completed ? "Done" : l.kind === "quiz" ? "Not passed yet" : "Not handed in yet"}
                          title={l.completed ? undefined : l.kind === "quiz" ? "Completes when you pass" : "Completes when you hand in"}
                          className={cn(
                            "mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full ring-1",
                            l.completed ? "bg-navy-900 ring-navy-900" : "ring-stone-500/40",
                          )}
                        >
                          {l.completed && <Check className="size-3 text-white" strokeWidth={3} />}
                        </span>
                      )}
                      {tracked && l.locked_until && (
                        <span role="img" aria-label={`Part of the ${l.locked_until.name} edition`} className="mt-0.5 flex size-4 shrink-0 items-center justify-center text-stone-500">
                          <Lock className="size-3.5" />
                        </span>
                      )}
                      {tracked && !l.locked_until && l.kind !== "quiz" && l.kind !== "assignment" && (
                        <button
                          type="button"
                          onClick={() => onToggle(l.id, !l.completed)}
                          aria-pressed={l.completed}
                          aria-label={`${l.completed ? "Mark as not done" : "Mark as done"}: ${l.title}`}
                          className={cn(
                            "mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-[3px] ring-1 transition-colors",
                            l.completed ? "bg-navy-900 ring-navy-900" : "ring-stone-500/60 hover:ring-navy-900",
                          )}
                        >
                          {l.completed && <Check className="size-3 text-white" strokeWidth={3} />}
                        </button>
                      )}
                      <button
                        type="button"
                        onClick={() => onSelect(l.id)}
                        aria-current={isCurrent ? "true" : undefined}
                        className="flex-1 text-left"
                      >
                        <span className={cn("block leading-snug", isCurrent ? "font-semibold text-navy-900" : "text-stone-700")}>
                          {n}. {l.title}
                        </span>
                        <span className="tabular mt-1 flex items-center gap-3 text-xs text-stone-500">
                          <span className="inline-flex items-center gap-1">
                            <Icon className="size-3.5" /> {lectureLength(l)}
                          </span>
                          {l.locked_until && <span className="font-semibold text-stone-500">{l.locked_until.name}</span>}
                          {l.resource_count > 0 && (
                            <span className="inline-flex items-center gap-1">
                              <FolderOpen className="size-3.5" /> {l.resource_count === 1 ? "1 resource" : `${l.resource_count} resources`}
                            </span>
                          )}
                        </span>
                      </button>
                    </li>
                  );
                })}
              </ol>
            </details>
          );
        })}
      </div>
    </nav>
  );
}
