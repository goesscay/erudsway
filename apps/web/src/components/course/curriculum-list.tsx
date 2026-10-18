import { ChevronDown, FileText, ListChecks, PlayCircle } from "lucide-react";
import type { Lecture, Section } from "@/lib/demo-data";
import { formatClock, formatDuration } from "@/lib/format";

const icon: Record<Lecture["kind"], typeof PlayCircle> = {
  video: PlayCircle,
  article: FileText,
  quiz: ListChecks,
};

export function CurriculumList({ sections }: { sections: Section[] }) {
  const lectures = sections.flatMap((s) => s.lectures);
  const total = lectures.reduce((n, l) => n + l.seconds, 0);

  return (
    <div>
      <p className="mb-3 text-sm text-stone-700">
        {sections.length} sections · {lectures.length} lectures · {formatDuration(total)} total length
      </p>
      <div className="divide-y divide-line overflow-hidden rounded-md border border-line bg-white">
        {sections.map((section, i) => {
          const secs = section.lectures.reduce((n, l) => n + l.seconds, 0);
          return (
            <details key={section.title} open={i === 0} className="group">
              <summary className="flex cursor-pointer list-none items-center gap-3 bg-paper-2/60 px-5 py-4 hover:bg-paper-2 [&::-webkit-details-marker]:hidden">
                <ChevronDown className="size-4 text-stone-500 transition-transform group-open:rotate-180" />
                <span className="flex-1 font-semibold text-ink">{section.title}</span>
                <span className="tabular text-xs text-stone-500">
                  {section.lectures.length} lectures · {formatDuration(secs)}
                </span>
              </summary>
              <ul className="space-y-0.5 px-5 py-3">
                {section.lectures.map((l) => {
                  const Icon = icon[l.kind];
                  return (
                    <li key={l.title} className="flex items-center gap-3 py-2 text-sm">
                      <Icon className="size-4 shrink-0 text-stone-500" strokeWidth={1.75} />
                      <span className={l.preview ? "flex-1 text-navy-800 underline underline-offset-2" : "flex-1 text-stone-700"}>
                        {l.title}
                      </span>
                      {l.preview && <span className="text-xs font-semibold text-navy-800">Preview</span>}
                      <span className="tabular w-12 text-right text-xs text-stone-500">{formatClock(l.seconds)}</span>
                    </li>
                  );
                })}
              </ul>
            </details>
          );
        })}
      </div>
    </div>
  );
}
