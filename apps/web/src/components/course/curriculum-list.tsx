import { ChevronDown, ClipboardList, FileText, ListChecks, PlayCircle } from "lucide-react";
import { formatClock, formatDuration, plural } from "@/lib/format";
import { PreviewTrigger } from "./course-preview";

export interface CurriculumLecture {
  id?: number;
  title: string;
  kind: string;
  duration_seconds: number;
  is_free_preview: boolean;
  /** The edition this lecture starts in, when it isn't in every edition. */
  edition?: string | null;
}

export interface CurriculumSection {
  title: string;
  lectures: CurriculumLecture[];
}

const icon: Record<string, typeof PlayCircle> = {
  video: PlayCircle,
  article: FileText,
  quiz: ListChecks,
  assignment: ClipboardList,
};

export function CurriculumList({ sections }: { sections: CurriculumSection[] }) {
  const lectures = sections.flatMap((s) => s.lectures);
  const total = lectures.reduce((n, l) => n + l.duration_seconds, 0);

  return (
    <div>
      <p className="mb-3 text-sm text-stone-700">
        {plural(sections.length, "section")} · {plural(lectures.length, "lecture")} · {formatDuration(total)} total length
      </p>
      <div className="divide-y divide-line overflow-hidden rounded-md border border-line bg-white">
        {sections.map((section, i) => {
          const secs = section.lectures.reduce((n, l) => n + l.duration_seconds, 0);
          return (
            <details key={`${i}-${section.title}`} open={i === 0} className="group">
              <summary className="flex cursor-pointer list-none items-center gap-3 bg-paper-2/60 px-5 py-4 hover:bg-paper-2 [&::-webkit-details-marker]:hidden">
                <ChevronDown className="size-4 text-stone-500 transition-transform group-open:rotate-180" />
                <span className="flex-1 font-semibold text-ink">{section.title}</span>
                <span className="tabular text-xs text-stone-500">
                  {plural(section.lectures.length, "lecture")} · {formatDuration(secs)}
                </span>
              </summary>
              <ul className="space-y-0.5 px-5 py-3">
                {section.lectures.map((l, j) => {
                  const Icon = icon[l.kind] ?? PlayCircle;
                  return (
                    <li key={`${j}-${l.title}`} className="flex items-center gap-3 py-2 text-sm">
                      <Icon className="size-4 shrink-0 text-stone-500" strokeWidth={1.75} />
                      <span className="flex-1 text-stone-700">
                        {l.title}
                        {l.edition && (
                          <span className="ml-2 inline-flex h-5 items-center rounded-sm bg-paper-2 px-1.5 align-middle text-[0.65rem] font-semibold text-stone-700 ring-1 ring-line ring-inset">
                            {l.edition}
                          </span>
                        )}
                      </span>
                      {l.is_free_preview &&
                        (l.kind === "video" && l.id ? (
                          <PreviewTrigger lectureId={l.id} className="text-xs font-semibold text-navy-800 underline underline-offset-2 hover:text-navy-900">
                            Preview
                          </PreviewTrigger>
                        ) : (
                          <span className="text-xs font-semibold text-navy-800">Free preview</span>
                        ))}
                      <span className="tabular w-12 text-right text-xs text-stone-500">{formatClock(l.duration_seconds)}</span>
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
