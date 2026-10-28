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
    </>
  );
}

function TrialPanel() {
  const reducedMotion = useReducedMotion();

  return (
    <>
      <Link
        href="/gst-simulator"
        className="relative inline-flex w-fit items-center gap-2 rounded-sm text-sm font-semibold text-white/70 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gold-400"
      >
        <ArrowLeft className="size-4" aria-hidden /> Back to GST Simulator
      </Link>
      <figure className="relative flex flex-1 flex-col items-center justify-center py-6">
        <video
          // The walkthrough is silent and loops; with reduced motion it waits for the viewer to press play.
          key={reducedMotion ? "still" : "moving"}
          className="aspect-[4/5] max-h-[calc(100dvh-20rem)] w-auto max-w-full rounded-2xl shadow-2xl ring-1 ring-white/10"
          poster="/media/gst-simulator-trial-poster.jpg"
          autoPlay={!reducedMotion}
          controls={reducedMotion}
          muted
          loop
          playsInline
          preload={reducedMotion ? "none" : "auto"}
          aria-label="Walkthrough of the GST simulator: log in, prepare GSTR-1, then reconcile ITC and file GSTR-3B."
        >
          <source src="/media/gst-simulator-trial.webm" type="video/webm" />
          <source src="/media/gst-simulator-trial.mp4" type="video/mp4" />
        </video>
        <figcaption className="mt-5 max-w-sm text-center text-sm text-white/60">
          Log in, prepare GSTR-1, reconcile ITC and file GSTR-3B — on your own practice GSTIN, free for 7 days.
        </figcaption>
      </figure>
    </>
  );
}
