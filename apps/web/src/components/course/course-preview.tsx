"use client";

import { createContext, useContext, useState } from "react";
import { PlayCircle } from "lucide-react";
import { VideoPlayer } from "@/components/player/video-player";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { formatClock } from "@/lib/format";
import { cn } from "@/lib/utils";

export interface PreviewLecture {
  id: number;
  title: string;
  duration_seconds: number;
}

const PreviewContext = createContext<((lectureId?: number) => void) | null>(null);

/**
 * "Preview this course": free-preview videos play for anyone, signed in or not. The API
 * still decides what may play; this only offers what the catalogue marks as preview.
 */
export function CoursePreview({ courseTitle, lectures, children }: { courseTitle: string; lectures: PreviewLecture[]; children: React.ReactNode }) {
  const [current, setCurrent] = useState<number | null>(null);
  const open = (lectureId?: number) => setCurrent(lectureId ?? lectures[0]?.id ?? null);
  const playing = lectures.find((l) => l.id === current);

  return (
    <PreviewContext.Provider value={lectures.length ? open : null}>
      {children}
      <Dialog open={current !== null} onOpenChange={(o) => !o && setCurrent(null)}>
        <DialogContent className="dark max-h-[calc(100dvh-2rem)] gap-0 overflow-y-auto bg-navy-950 p-0 text-foreground sm:max-w-3xl">
          <DialogHeader className="px-6 pt-5 pb-4">
            <DialogDescription className="text-xs font-bold tracking-[0.15em] text-white/60 uppercase">Course preview</DialogDescription>
            <DialogTitle className="font-sans text-lg font-bold text-white">{courseTitle}</DialogTitle>
          </DialogHeader>
          {playing && <VideoPlayer key={playing.id} lectureId={playing.id} title={playing.title} autoPlay />}
          <div className="max-h-56 overflow-y-auto px-3 py-3">
            <p className="px-3 pb-2 text-sm font-bold text-white">Free sample videos</p>
            <ul>
              {lectures.map((l) => (
                <li key={l.id}>
                  <button
                    type="button"
                    onClick={() => setCurrent(l.id)}
                    aria-current={l.id === current ? "true" : undefined}
                    className={cn("flex w-full items-center gap-3 rounded-sm px-3 py-2.5 text-left text-sm hover:bg-white/10", l.id === current && "bg-white/10")}
                  >
                    <PlayCircle className="size-4 shrink-0 text-gold-400" />
                    <span className={cn("flex-1", l.id === current ? "font-semibold text-white" : "text-white/80")}>{l.title}</span>
                    <span className="tabular text-xs text-white/60">{formatClock(l.duration_seconds)}</span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        </DialogContent>
      </Dialog>
    </PreviewContext.Provider>
  );
}

/** Opens the preview dialog (at a given lecture). Renders plain children when there's nothing to preview. */
export function PreviewTrigger({ lectureId, className, children }: { lectureId?: number; className?: string; children: React.ReactNode }) {
  const open = useContext(PreviewContext);
  if (!open) return <span className={className}>{children}</span>;
  return (
    <button type="button" onClick={() => open(lectureId)} className={className}>
      {children}
    </button>
  );
}
