"use client";

import Image from "next/image";
import { useState } from "react";
import { Captions, Maximize, Pause, Play, RotateCcw, RotateCw, Settings, Volume2 } from "lucide-react";
import { formatClock } from "@/lib/format";

/**
 * Phase 0 visual of the player chrome. The real player (Phase 4) mounts hls.js
 * on a <video> element here, with signed per-user playlists from the API.
 */
export function VideoStage({ poster, title, duration }: { poster: string; title: string; duration: number }) {
  const [playing, setPlaying] = useState(false);
  const position = Math.round(duration * 0.38);

  return (
    <div className="group relative aspect-video w-full overflow-hidden bg-black">
      <Image src={poster} alt="" fill sizes="(min-width: 1024px) 70vw, 100vw" className="object-cover opacity-60" priority />
      <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/30" />

      {/* Discourage screen recording: faint moving watermark with the learner's identity. */}
      <span className="pointer-events-none absolute top-[18%] left-[12%] text-xs font-medium text-white/15 select-none">
        learner@example.com
      </span>

      <p className="absolute top-4 left-5 max-w-[70%] truncate text-sm font-medium text-white/85">{title}</p>

      <button
        type="button"
        onClick={() => setPlaying((p) => !p)}
        aria-label={playing ? "Pause" : "Play"}
        className="absolute top-1/2 left-1/2 flex size-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-navy-900 shadow-xl transition-transform hover:scale-105"
      >
        {playing ? <Pause className="size-8 fill-current" /> : <Play className="ml-1 size-8 fill-current" />}
      </button>

      <div className="absolute inset-x-0 bottom-0 px-5 pb-3">
        <div className="group/scrub relative mb-3 h-1 cursor-pointer rounded-full bg-white/25">
          <div className="absolute inset-y-0 left-0 w-[62%] rounded-full bg-white/35" aria-hidden />
          <div className="absolute inset-y-0 left-0 rounded-full bg-gold-400" style={{ width: "38%" }} />
          <span
            className="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold-400 opacity-0 transition-opacity group-hover/scrub:opacity-100"
            style={{ left: "38%" }}
          />
        </div>
        <div className="flex items-center gap-4 text-white">
          <button type="button" aria-label={playing ? "Pause" : "Play"} onClick={() => setPlaying((p) => !p)}>
            {playing ? <Pause className="size-5 fill-current" /> : <Play className="size-5 fill-current" />}
          </button>
          <button type="button" aria-label="Back 5 seconds">
            <RotateCcw className="size-5" />
          </button>
          <button type="button" aria-label="Forward 5 seconds">
            <RotateCw className="size-5" />
          </button>
          <span className="rounded-sm px-1.5 py-0.5 text-xs font-bold ring-1 ring-white/30">1×</span>
          <span className="tabular text-xs text-white/80">
            {formatClock(position)} / {formatClock(duration)}
          </span>
          <span className="ml-auto flex items-center gap-4">
            <Volume2 className="size-5" aria-label="Volume" />
            <Captions className="size-5" aria-label="Captions" />
            <Settings className="size-5" aria-label="Quality" />
            <Maximize className="size-5" aria-label="Full screen" />
          </span>
        </div>
      </div>
    </div>
  );
}
