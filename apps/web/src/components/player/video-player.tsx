"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type Hls from "hls.js";
import { Loader2, Maximize, Minimize, Pause, Play, RotateCcw, RotateCw, Settings, Volume1, Volume2, VolumeX } from "lucide-react";
import { ApiRequestError } from "@/lib/api/client";
import { requestPlayback, type Playback } from "@/lib/learning/api";
import { formatClock } from "@/lib/format";
import { cn } from "@/lib/utils";

const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2] as const;
const SAVE_EVERY_MS = 15_000;
const SKIP_SECONDS = 5;

type Level = { index: number; height: number };

function readPref(key: string, fallback: number): number {
  try {
    const v = Number(window.localStorage.getItem(`player.${key}`));
    return Number.isFinite(v) && v > 0 ? v : fallback;
  } catch {
    return fallback;
  }
}

function writePref(key: string, value: number) {
  try {
    window.localStorage.setItem(`player.${key}`, String(value));
  } catch {
    // Private mode or blocked storage: preferences just aren't remembered.
  }
}

/**
 * Encrypted-HLS player for one lecture. Playback URLs are per viewer and short-lived; when
 * they expire mid-lecture the player quietly asks for fresh ones and carries on where it was.
 */
export function VideoPlayer({
  lectureId,
  title,
  startAt = 0,
  autoPlay = false,
  onSave,
  onEnded,
  positionRef,
  seek,
}: {
  lectureId: number;
  title: string;
  /** Resume position in seconds. */
  startAt?: number;
  autoPlay?: boolean;
  /** Called with the current time every 15 s of playback, on pause, on end and on leave. */
  onSave?: (seconds: number, opts: { leaving: boolean }) => void;
  onEnded?: () => void;
  /** Kept up to date with the playback position (for timestamped notes). */
  positionRef?: React.RefObject<number>;
  /** Jump to a time; a new object (e.g. with a fresh `at`) triggers a new jump. */
  seek?: { to: number; at: number } | null;
}) {
  const containerRef = useRef<HTMLDivElement>(null);
  const videoRef = useRef<HTMLVideoElement>(null);
  const hlsRef = useRef<Hls | null>(null);
  const lastRefresh = useRef(0);
  const reloadRef = useRef<(resumeAt: number, play: boolean) => void>(() => {});
  const saveRef = useRef(onSave);
  const endedRef = useRef(onEnded);
  useEffect(() => {
    saveRef.current = onSave;
    endedRef.current = onEnded;
  });

  const [playback, setPlayback] = useState<Playback | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [playing, setPlaying] = useState(false);
  const [waiting, setWaiting] = useState(true);
  const [time, setTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [buffered, setBuffered] = useState(0);
  // The player only renders in the browser, so preferences can be read on first render.
  const [volume, setVolume] = useState(() => Math.min(1, readPref("volume", 1)));
  const [muted, setMuted] = useState(false);
  const [speed, setSpeed] = useState(() => readPref("speed", 1));
  const [levels, setLevels] = useState<Level[]>([]);
  const [level, setLevel] = useState(-1); // -1 = auto
  const [menu, setMenu] = useState(false);
  const [fullscreen, setFullscreen] = useState(false);
  const [active, setActive] = useState(true);
  const idleTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Load (or reload, after expiry) the stream for this lecture.
  const load = useCallback(
    async (resumeAt: number, play: boolean) => {
      const video = videoRef.current;
      if (!video) return;
      try {
        const pb = await requestPlayback(lectureId);
        setPlayback(pb);
        setError(null);
        hlsRef.current?.destroy();
        hlsRef.current = null;

        const { default: HlsClass } = await import("hls.js");
        const start = () => {
          if (resumeAt > 0) video.currentTime = resumeAt;
          if (play) void video.play().catch(() => setPlaying(false));
        };

        if (HlsClass.isSupported()) {
          const hls = new HlsClass({ maxBufferLength: 30, startPosition: resumeAt > 0 ? resumeAt : -1 });
          hlsRef.current = hls;
          hls.on(HlsClass.Events.MANIFEST_PARSED, (_e, data) => {
            setLevels(data.levels.map((l, index) => ({ index, height: l.height })).sort((a, b) => b.height - a.height));
            if (play) void video.play().catch(() => setPlaying(false));
          });
          hls.on(HlsClass.Events.ERROR, (_e, data) => {
            if (!data.fatal) return;
            if (data.type === HlsClass.ErrorTypes.MEDIA_ERROR) {
              hls.recoverMediaError();
            } else if (Date.now() - lastRefresh.current > 30_000) {
              // Most likely the signed URLs expired: get fresh ones and continue from here.
              lastRefresh.current = Date.now();
              reloadRef.current(video.currentTime, !video.paused);
            } else {
              setError("Playback stopped. Check your connection and try again.");
            }
          });
          hls.loadSource(pb.playlist_url);
          hls.attachMedia(video);
        } else if (video.canPlayType("application/vnd.apple.mpegurl")) {
          // Safari plays HLS (and fetches the key) natively.
          video.src = pb.playlist_url;
          video.addEventListener("loadedmetadata", start, { once: true });
        } else {
          setError("This browser can’t play our videos. Try a recent Chrome, Edge, Firefox or Safari.");
        }
      } catch (e) {
        setError(e instanceof ApiRequestError ? e.message : "We couldn’t start this video. Please try again.");
      }
    },
    [lectureId],
  );

  useEffect(() => {
    reloadRef.current = (resumeAt, play) => void load(resumeAt, play);
  }, [load]);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    setTime(startAt);
    setWaiting(true);
    void load(startAt, autoPlay);
    return () => {
      if (video.currentTime > 0) saveRef.current?.(video.currentTime, { leaving: true });
      hlsRef.current?.destroy();
      hlsRef.current = null;
      video.removeAttribute("src");
    };
    // startAt/autoPlay are read once per lecture on purpose.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [load]);

  // Keep element state in sync with the controls.
  useEffect(() => {
    const v = videoRef.current;
    if (!v) return;
    v.playbackRate = speed;
    v.volume = volume;
    v.muted = muted;
  }, [speed, volume, muted]);

  // Periodic save while playing, plus on tab close.
  useEffect(() => {
    if (!playing) return;
    const id = setInterval(() => saveRef.current?.(videoRef.current?.currentTime ?? 0, { leaving: false }), SAVE_EVERY_MS);
    const onHide = () => {
      if (document.visibilityState === "hidden") saveRef.current?.(videoRef.current?.currentTime ?? 0, { leaving: true });
    };
    document.addEventListener("visibilitychange", onHide);
    return () => {
      clearInterval(id);
      document.removeEventListener("visibilitychange", onHide);
    };
  }, [playing]);

  useEffect(() => {
    const v = videoRef.current;
    if (!v || !seek) return;
    v.currentTime = seek.to;
    void v.play().catch(() => {});
  }, [seek]);

  useEffect(() => {
    const onChange = () => setFullscreen(document.fullscreenElement === containerRef.current);
    document.addEventListener("fullscreenchange", onChange);
    return () => document.removeEventListener("fullscreenchange", onChange);
  }, []);

  const toggle = () => {
    const v = videoRef.current;
    if (!v) return;
    if (v.paused) void v.play().catch(() => {});
    else v.pause();
  };
  const seekBy = (delta: number) => {
    const v = videoRef.current;
    if (v) v.currentTime = Math.max(0, Math.min(v.duration || 0, v.currentTime + delta));
  };
  const toggleFullscreen = () => {
    if (document.fullscreenElement) void document.exitFullscreen();
    else void containerRef.current?.requestFullscreen?.();
  };
  const changeSpeed = (s: number) => {
    setSpeed(s);
    writePref("speed", s);
  };
  const changeVolume = (v: number) => {
    setVolume(v);
    setMuted(v === 0);
    writePref("volume", v || 1);
  };
  const changeLevel = (l: number) => {
    setLevel(l);
    if (hlsRef.current) hlsRef.current.currentLevel = l;
  };

  const wake = () => {
    setActive(true);
    if (idleTimer.current) clearTimeout(idleTimer.current);
    idleTimer.current = setTimeout(() => setActive(false), 2500);
  };

  const onKeyDown = (e: React.KeyboardEvent) => {
    if ((e.target as HTMLElement).closest("input,button[role=menuitemradio]")) return;
    const keys: Record<string, () => void> = {
      " ": toggle,
      k: toggle,
      ArrowLeft: () => seekBy(-SKIP_SECONDS),
      ArrowRight: () => seekBy(SKIP_SECONDS),
      ArrowUp: () => changeVolume(Math.min(1, volume + 0.1)),
      ArrowDown: () => changeVolume(Math.max(0, volume - 0.1)),
      m: () => setMuted((m) => !m),
      f: toggleFullscreen,
    };
    const action = keys[e.key];
    if (action) {
      e.preventDefault();
      action();
      wake();
    }
  };

  const showChrome = !playing || active || menu;
  const VolumeIcon = muted || volume === 0 ? VolumeX : volume < 0.5 ? Volume1 : Volume2;
  const pct = duration ? (time / duration) * 100 : 0;

  return (
    <div
      ref={containerRef}
      tabIndex={0}
      role="region"
      aria-label={`Video player: ${title}`}
      onKeyDown={onKeyDown}
      onMouseMove={wake}
      onMouseLeave={() => setActive(false)}
      className={cn("group relative aspect-video w-full overflow-hidden bg-black outline-none select-none", fullscreen && "aspect-auto h-full", !showChrome && "cursor-none")}
    >
      <video
        ref={videoRef}
        poster={playback?.poster_url ?? undefined}
        playsInline
        preload="metadata"
        className="absolute inset-0 size-full"
        onClick={toggle}
        onDoubleClick={toggleFullscreen}
        onPlay={() => setPlaying(true)}
        onPause={() => {
          setPlaying(false);
          saveRef.current?.(videoRef.current?.currentTime ?? 0, { leaving: false });
        }}
        onWaiting={() => setWaiting(true)}
        onCanPlay={() => setWaiting(false)}
        onPlaying={() => setWaiting(false)}
        onTimeUpdate={(e) => {
          const v = e.currentTarget;
          setTime(v.currentTime);
          if (positionRef) positionRef.current = v.currentTime;
          for (let i = 0; i < v.buffered.length; i++) {
            if (v.buffered.start(i) <= v.currentTime && v.currentTime <= v.buffered.end(i)) setBuffered(v.buffered.end(i));
          }
        }}
        onDurationChange={(e) => setDuration(e.currentTarget.duration || 0)}
        onEnded={() => {
          setPlaying(false);
          saveRef.current?.(videoRef.current?.duration ?? 0, { leaving: false });
          endedRef.current?.();
        }}
      />

      {playback?.watermark && <Watermark text={playback.watermark} />}

      {error ? (
        <div className="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-navy-950/90 px-6 text-center text-white">
          <p className="max-w-sm text-sm">{error}</p>
          <button type="button" onClick={() => void load(time, false)} className="rounded-md bg-white px-4 py-2 text-sm font-semibold text-navy-900">
            Try again
          </button>
        </div>
      ) : (
        <>
          {waiting && (
            <Loader2 className="pointer-events-none absolute top-1/2 left-1/2 size-10 -translate-x-1/2 -translate-y-1/2 animate-spin text-white/80" aria-label="Loading" />
          )}
          {!playing && !waiting && (
            <button
              type="button"
              onClick={toggle}
              aria-label="Play"
              className="absolute top-1/2 left-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-navy-900 shadow-xl transition-transform hover:scale-105 sm:size-20"
            >
              <Play className="ml-1 size-7 fill-current sm:size-8" />
            </button>
          )}
        </>
      )}

      {/* Controls */}
      <div
        className={cn(
          "absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-3 pt-10 pb-2 text-white transition-opacity duration-200 sm:px-5",
          showChrome ? "opacity-100" : "pointer-events-none opacity-0",
        )}
      >
        <div className="relative mb-2 h-4">
          <div className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full bg-white/25">
            <div className="absolute inset-y-0 left-0 rounded-full bg-white/35" style={{ width: `${duration ? (buffered / duration) * 100 : 0}%` }} />
            <div className="absolute inset-y-0 left-0 rounded-full bg-gold-400" style={{ width: `${pct}%` }} />
          </div>
          <input
            type="range"
            min={0}
            max={duration || 0}
            step={0.1}
            value={Math.min(time, duration || 0)}
            onChange={(e) => {
              const v = videoRef.current;
              if (v) v.currentTime = Number(e.target.value);
            }}
            aria-label="Seek"
            aria-valuetext={`${formatClock(Math.floor(time))} of ${formatClock(Math.floor(duration))}`}
            className="player-range absolute inset-0 w-full cursor-pointer"
          />
        </div>

        <div className="flex items-center gap-3 sm:gap-4">
          <ControlButton label={playing ? "Pause (k)" : "Play (k)"} onClick={toggle}>
            {playing ? <Pause className="size-5 fill-current" /> : <Play className="size-5 fill-current" />}
          </ControlButton>
          <ControlButton label={`Back ${SKIP_SECONDS} seconds`} onClick={() => seekBy(-SKIP_SECONDS)}>
            <RotateCcw className="size-5" />
          </ControlButton>
          <ControlButton label={`Forward ${SKIP_SECONDS} seconds`} onClick={() => seekBy(SKIP_SECONDS)}>
            <RotateCw className="size-5" />
          </ControlButton>
          <button
            type="button"
            onClick={() => changeSpeed(SPEEDS[(SPEEDS.indexOf(speed as (typeof SPEEDS)[number]) + 1) % SPEEDS.length]!)}
            aria-label={`Playback speed ${speed}×`}
            className="tabular min-w-10 rounded-sm px-1.5 py-0.5 text-xs font-bold ring-1 ring-white/30 hover:ring-white/60"
          >
            {speed}×
          </button>
          <span className="tabular text-xs text-white/85">
            {formatClock(Math.floor(time))} / {formatClock(Math.floor(duration))}
          </span>

          <span className="ml-auto flex items-center gap-2 sm:gap-3">
            <span className="group/vol hidden items-center gap-2 sm:flex">
              <ControlButton label={muted ? "Unmute (m)" : "Mute (m)"} onClick={() => setMuted((m) => !m)}>
                <VolumeIcon className="size-5" />
              </ControlButton>
              <input
                type="range"
                min={0}
                max={1}
                step={0.05}
                value={muted ? 0 : volume}
                onChange={(e) => changeVolume(Number(e.target.value))}
                aria-label="Volume"
                className="player-range w-0 cursor-pointer opacity-0 transition-all group-hover/vol:w-20 group-hover/vol:opacity-100 focus:w-20 focus:opacity-100"
              />
            </span>
            <span className="relative">
              <ControlButton label="Settings" onClick={() => setMenu((m) => !m)} expanded={menu}>
                <Settings className={cn("size-5 transition-transform", menu && "rotate-45")} />
              </ControlButton>
              {menu && (
                <SettingsMenu
                  speed={speed}
                  levels={levels}
                  level={level}
                  onSpeed={(s) => {
                    changeSpeed(s);
                    setMenu(false);
                  }}
                  onLevel={(l) => {
                    changeLevel(l);
                    setMenu(false);
                  }}
                />
              )}
            </span>
            <ControlButton label={fullscreen ? "Exit full screen (f)" : "Full screen (f)"} onClick={toggleFullscreen}>
              {fullscreen ? <Minimize className="size-5" /> : <Maximize className="size-5" />}
            </ControlButton>
          </span>
        </div>
      </div>
    </div>
  );
}

function ControlButton({ label, onClick, children, expanded }: { label: string; onClick: () => void; children: React.ReactNode; expanded?: boolean }) {
  return (
    <button type="button" onClick={onClick} aria-label={label} title={label} aria-expanded={expanded} className="rounded-sm p-1 text-white/90 hover:text-white focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:outline-none">
      {children}
    </button>
  );
}

function SettingsMenu({
  speed,
  levels,
  level,
  onSpeed,
  onLevel,
}: {
  speed: number;
  levels: Level[];
  level: number;
  onSpeed: (s: number) => void;
  onLevel: (l: number) => void;
}) {
  return (
    <div role="menu" aria-label="Playback settings" className="absolute right-0 bottom-10 z-10 flex w-56 gap-4 rounded-md bg-navy-950/95 p-3 text-sm shadow-xl ring-1 ring-white/10">
      <div className="flex-1">
        <p className="mb-1.5 text-[0.65rem] font-bold tracking-[0.15em] text-white/50 uppercase">Speed</p>
        {SPEEDS.map((s) => (
          <MenuItem key={s} checked={s === speed} onSelect={() => onSpeed(s)}>
            {s === 1 ? "Normal" : `${s}×`}
          </MenuItem>
        ))}
      </div>
      {levels.length > 0 && (
        <div className="flex-1">
          <p className="mb-1.5 text-[0.65rem] font-bold tracking-[0.15em] text-white/50 uppercase">Quality</p>
          <MenuItem checked={level === -1} onSelect={() => onLevel(-1)}>
            Auto
          </MenuItem>
          {levels.map((l) => (
            <MenuItem key={l.index} checked={level === l.index} onSelect={() => onLevel(l.index)}>
              {l.height}p{l.height >= 720 && <span className="ml-1 text-[0.6rem] font-bold text-gold-400">HD</span>}
            </MenuItem>
          ))}
        </div>
      )}
    </div>
  );
}

function MenuItem({ checked, onSelect, children }: { checked: boolean; onSelect: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      role="menuitemradio"
      aria-checked={checked}
      onClick={onSelect}
      className={cn("block w-full rounded-sm px-2 py-1 text-left hover:bg-white/10", checked ? "font-semibold text-gold-400" : "text-white/85")}
    >
      {children}
    </button>
  );
}

/** Faint, slowly moving identity mark to discourage screen recording and re-sharing. */
function Watermark({ text }: { text: string }) {
  const [pos, setPos] = useState({ top: 14, left: 10 });
  useEffect(() => {
    const id = setInterval(() => setPos({ top: 8 + Math.random() * 70, left: 5 + Math.random() * 60 }), 20_000);
    return () => clearInterval(id);
  }, []);

  return (
    <span
      aria-hidden
      className="pointer-events-none absolute z-[1] text-xs font-medium whitespace-nowrap text-white/20 transition-all duration-[3000ms] sm:text-sm"
      style={{ top: `${pos.top}%`, left: `${pos.left}%` }}
    >
      {text}
    </span>
  );
}
