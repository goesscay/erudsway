import { Check, ChevronDown, FileText, ListChecks, PlayCircle } from "lucide-react";
import type { Lecture, Section } from "@/lib/demo-data";
import { formatClock, formatDuration } from "@/lib/format";
import { cn } from "@/lib/utils";

const icon: Record<Lecture["kind"], typeof PlayCircle> = { video: PlayCircle, article: FileText, quiz: ListChecks };

/** `done` and `current` are flat lecture indexes across all sections. */
export function CurriculumSidebar({ sections, done, current }: { sections: Section[]; done: number; current: number }) {
  // Flat index of each section's first lecture, computed up front (no mutation during render).
  const offsets = sections.map((_, s) => sections.slice(0, s).reduce((n, sec) => n + sec.lectures.length, 0));

  return (
    <aside className="flex h-full flex-col border-l border-line bg-white">
      <h2 className="border-b border-line px-5 py-4 font-sans text-base font-bold text-ink">Course content</h2>
      <div className="flex-1 overflow-y-auto">
        {sections.map((section, s) => {
          const total = section.lectures.reduce((n, l) => n + l.seconds, 0);
          const start = offsets[s]!;
          const completed = section.lectures.filter((_, i) => start + i < done).length;
          return (
            <details key={section.title} open={s <= 2} className="group border-b border-line">
              <summary className="flex cursor-pointer list-none items-start gap-3 bg-paper-2/60 px-5 py-3.5 [&::-webkit-details-marker]:hidden">
                <span className="flex-1">
                  <span className="block text-sm font-bold text-ink">
                    Section {s + 1}: {section.title}
                  </span>
                  <span className="tabular text-xs text-stone-500">
                    {completed} / {section.lectures.length} · {formatDuration(total)}
                  </span>
                </span>
                <ChevronDown className="mt-0.5 size-4 text-stone-500 transition-transform group-open:rotate-180" />
              </summary>
              <ol>
                {section.lectures.map((l, i) => {
                  const index = start + i;
                  const isDone = index < done;
                  const isCurrent = index === current;
                  const Icon = icon[l.kind];
                  return (
                    <li key={l.title}>
                      <button
                        type="button"
                        aria-current={isCurrent ? "true" : undefined}
                        className={cn(
                          "flex w-full gap-3 px-5 py-3 text-left text-sm hover:bg-paper",
                          isCurrent && "bg-navy-100/70 hover:bg-navy-100",
                        )}
                      >
                        <span
                          className={cn(
                            "mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-[3px] ring-1",
                            isDone ? "bg-navy-900 ring-navy-900" : "ring-stone-500/60",
                          )}
                          aria-label={isDone ? "Completed" : "Not completed"}
                        >
                          {isDone && <Check className="size-3 text-white" strokeWidth={3} />}
                        </span>
                        <span className="flex-1">
                          <span className={cn("block leading-snug", isCurrent ? "font-semibold text-navy-900" : "text-stone-700")}>
                            {index + 1}. {l.title}
                          </span>
                          <span className="tabular mt-1 inline-flex items-center gap-1 text-xs text-stone-500">
                            <Icon className="size-3.5" /> {formatClock(l.seconds)}
                          </span>
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
    </aside>
  );
}
