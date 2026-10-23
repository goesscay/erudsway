"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";

/** The sign-up heading; it speaks to the GST simulator trial when that's where the visitor came from. */
export function RegisterIntro() {
  const next = useSearchParams().get("next") ?? "";
  const forTrial = next.startsWith("/gst-simulator");

  return <Intro forTrial={forTrial} login={next ? `/login?next=${encodeURIComponent(next)}` : "/login"} />;
}

RegisterIntro.Fallback = function RegisterIntroFallback() {
  return <Intro forTrial={false} login="/login" />;
};

function Intro({ forTrial, login }: { forTrial: boolean; login: string }) {
  return (
    <>
      <h1 className="text-h2">{forTrial ? "Start your free GST simulator trial" : "Start learning today"}</h1>
      <p className="mt-2 mb-8 text-sm text-stone-700">
        {forTrial && "Create your free account — your 7-day trial starts right after. "}
        Already have an account?{" "}
        <Link href={login} className="font-semibold text-navy-800 underline-offset-2 hover:underline">
          Log in
        </Link>
      </p>
    </>
  );
}
