"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { useSyncExternalStore } from "react";
import { ArrowLeft } from "lucide-react";
import { Logo } from "@/components/brand/logo";

const REDUCED_MOTION = "(prefers-reduced-motion: reduce)";

function useReducedMotion(): boolean {
  return useSyncExternalStore(
    (onChange) => {
      const query = window.matchMedia(REDUCED_MOTION);
      query.addEventListener("change", onChange);
      return () => query.removeEventListener("change", onChange);
    },
    () => window.matchMedia(REDUCED_MOTION).matches,
    () => false,
  );
}

/**
 * The content of the brand panel beside the sign-in and sign-up forms. Visitors who came from
 * the GST Simulator's free trial see a short walkthrough of the simulator instead.
 */
export function AuthPanel() {
  const pathname = usePathname();
  const next = useSearchParams().get("next") ?? "";
  const forTrial = (pathname === "/register" || pathname === "/login") && next.startsWith("/gst-simulator");

  return forTrial ? <TrialPanel /> : <DefaultPanel />;
}

export function DefaultPanel() {
  return (
    <>
      <Logo inverted />
      <div className="relative mt-auto max-w-md">
        <p className="font-serif text-[2.1rem] leading-[1.15] font-medium text-white">Knowledge that moves minds.</p>
        <p className="mt-6 text-sm text-white/60">
          Learn GST, income tax, audit and company law from chartered accountants, company secretaries and advocates who
          practise what they teach.
        </p>
      </div>
      <p className="relative mt-16 text-xs text-white/40">© 2026 Erudsway Educorp LLP</p>
    </>
  );
}

/** The simulator walkthrough fills the whole panel; only the way back sits on top of it. */
function TrialPanel() {
  const reducedMotion = useReducedMotion();

  return (
    <figure className="absolute inset-0 m-0">
      <video
        // Silent and looping; with reduced motion it waits for the viewer to press play.
        key={reducedMotion ? "still" : "moving"}
        className="size-full object-cover"
        poster="/media/gst-simulator-trial-poster.jpg"
        autoPlay={!reducedMotion}
        controls={reducedMotion}
        muted
        loop
        playsInline
        preload={reducedMotion ? "none" : "auto"}
        aria-describedby="trial-video-caption"
      >
        <source src="/media/gst-simulator-trial.webm" type="video/webm" />
        <source src="/media/gst-simulator-trial.mp4" type="video/mp4" />
      </video>
      <figcaption id="trial-video-caption" className="sr-only">
        Walkthrough of the GST simulator: log in, prepare GSTR-1, then reconcile ITC and file GSTR-3B on your own
        practice GSTIN, free for 7 days.
      </figcaption>
      <div className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-navy-950/80 to-transparent" />
      <Link
        href="/gst-simulator"
        className="absolute top-6 right-8 inline-flex items-center gap-2 rounded-sm text-sm font-semibold text-white/80 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gold-400"
      >
        <ArrowLeft className="size-4" aria-hidden /> Back to GST Simulator
      </Link>
    </figure>
  );
}
