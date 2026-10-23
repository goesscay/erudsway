"use client";

import Link from "next/link";
import { useState } from "react";
import { Check, Copy, ExternalLink, Loader2, MailCheck, Sparkles } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiRequestError } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { simulatorSignInLink, useSimulatorState, useStartTrial, type SimulatorState } from "@/lib/simulator/api";
import { useHydrated } from "@/lib/use-hydrated";

const NEXT = "/gst-simulator";

function formatDate(iso: string | null | undefined) {
  if (!iso) return "";
  return new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
}

function daysLeft(iso: string | null | undefined) {
  if (!iso) return null;
  return Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000));
}

/** The free-trial card in the hero: start the trial, or see and open your practice login. */
export function SimulatorAccessPanel({ trialDays, loginUrl }: { trialDays: number; loginUrl: string }) {
  const hydrated = useHydrated();
  const { data: me, isPending: mePending } = useMe();
  const state = useSimulatorState(!!me);

  return (
    <div className="rounded-lg bg-white p-6 text-ink shadow-xl ring-1 ring-black/5 sm:p-7">
      {!hydrated || mePending || (me && state.isPending) ? (
        <div className="space-y-3">
          <Skeleton className="h-7 w-2/3" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-12 w-full" />
        </div>
      ) : !me ? (
        <Guest trialDays={trialDays} />
      ) : !me.email_verified ? (
        <Unverified email={me.email} />
      ) : state.isError || !state.data ? (
        <FormAlert>Couldn&rsquo;t load your simulator access. Refresh the page to try again.</FormAlert>
      ) : (
        <Signed state={state.data} trialDays={trialDays} loginUrl={loginUrl} />
      )}
    </div>
  );
}

function Heading({ children }: { children: React.ReactNode }) {
  return <h2 className="font-sans text-xl font-bold text-ink">{children}</h2>;
}

function Guest({ trialDays }: { trialDays: number }) {
  return (
    <div className="space-y-4">
      <Heading>Try it free for {trialDays} days</Heading>
      <p className="text-sm leading-relaxed text-stone-700">
        Create a free Erudsway account and get your own practice GST login in under a minute. No card needed.
      </p>
      <Link
        href={`/register?next=${encodeURIComponent(NEXT)}`}
        className={buttonVariants({ size: "lg", className: "w-full" })}
      >
        <Sparkles /> Start my free trial
      </Link>
      <p className="text-center text-sm text-stone-500">
        Already have an account?{" "}
        <Link href={`/login?next=${encodeURIComponent(NEXT)}`} className="font-semibold text-navy-800 hover:underline">
          Log in
        </Link>
      </p>
    </div>
  );
}

function Unverified({ email }: { email: string }) {
  return (
    <div className="space-y-4">
      <Heading>One step before your trial</Heading>
      <p className="flex gap-2 text-sm leading-relaxed text-stone-700">
        <MailCheck className="mt-0.5 size-4 shrink-0 text-gold-600" aria-hidden />
        <span>
          Verify <strong>{email}</strong> using the link we emailed you, then come back here to start your free trial.
        </span>
      </p>
      <Link href="/verify-email" className={buttonVariants({ variant: "outline", size: "lg", className: "w-full" })}>
        Resend the verification email
      </Link>
    </div>
  );
}

function Signed({ state, trialDays, loginUrl }: { state: SimulatorState; trialDays: number; loginUrl: string }) {
  const start = useStartTrial();
  const [error, setError] = useState<string | null>(null);

  if (state.state === "preparing") {
    return (
      <div className="space-y-3" role="status">
        <Heading>Setting up your practice login…</Heading>
        <p className="flex items-center gap-2 text-sm text-stone-700">
          <Loader2 className="size-4 animate-spin text-gold-600" aria-hidden />
          This usually takes a few seconds. You can stay on this page.
        </p>
      </div>
    );
  }

  if (state.state === "trial" || state.state === "paid") {
    return <LoginCard state={state} loginUrl={loginUrl} />;
  }

  if (state.state === "trial_ended") {
    return (
      <div className="space-y-4">
        <Heading>Your free trial has ended</Heading>
        <p className="text-sm leading-relaxed text-stone-700">
          Your {trialDays}-day trial ended on {formatDate(state.trial_ends_at)}. Choose a plan to get a practice login
          again and keep going.
        </p>
        <Link href="#plans" className={buttonVariants({ size: "lg", className: "w-full" })}>
          See plans
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <Heading>Your {trialDays}-day free trial is ready to start</Heading>
      <ul className="space-y-1.5 text-sm text-stone-700">
        {[
          "Your own practice GSTIN, supplier and recipient",
          "Three months of practice data to file",
          "No card, no commitment",
        ].map((t) => (
          <li key={t} className="flex gap-2">
            <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
            {t}
          </li>
        ))}
      </ul>
      {error && <FormAlert>{error}</FormAlert>}
      <Button
        size="lg"
        className="w-full"
        disabled={start.isPending || !state.can_start_trial}
        onClick={() => {
          setError(null);
          start.mutate(undefined, {
            onError: (e) =>
              setError(e instanceof ApiRequestError ? e.message : "Something went wrong. Please try again."),
          });
        }}
      >
        <Sparkles /> {start.isPending ? "Starting…" : `Start my ${trialDays}-day free trial`}
      </Button>
      <p className="text-xs text-stone-500">
        One trial per account. When it ends, the practice login is removed unless you choose a plan.
      </p>
    </div>
  );
}

function LoginCard({ state, loginUrl }: { state: SimulatorState; loginUrl: string }) {
  const [opening, setOpening] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const left = daysLeft(state.ends_at);
  const trial = state.state === "trial";

  const open = async () => {
    setOpening(true);
    setError(null);
    try {
      window.location.assign(await simulatorSignInLink());
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t open the simulator. Use the login details below.");
      setOpening(false);
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <Heading>{trial ? "Your free trial is running" : "You have simulator access"}</Heading>
        <p className="mt-1 text-sm text-stone-700">
          {state.ends_at ? (
            <>
              {trial ? "Trial ends" : "Access until"} <strong>{formatDate(state.ends_at)}</strong>
              {left !== null && (
                <span className="text-stone-500">
                  {" "}
                  · {left === 0 ? "ends today" : `${left} day${left === 1 ? "" : "s"} left`}
                </span>
              )}
            </>
          ) : (
            "No end date."
          )}
        </p>
      </div>
      {error && <FormAlert>{error}</FormAlert>}
      <Button size="lg" className="w-full" onClick={open} disabled={opening}>
        <ExternalLink /> {opening ? "Opening…" : "Open the GST simulator"}
      </Button>
      {state.login && (
        <dl className="space-y-1.5 rounded-md border border-line bg-paper px-4 py-3 text-sm">
          <Detail label="Username" value={state.login.username} />
          {state.login.password && <Detail label="Password" value={state.login.password} secret />}
          {state.login.gstin && <Detail label="GSTIN" value={state.login.gstin} />}
        </dl>
      )}
      <p className="text-xs text-stone-500">
        Or sign in yourself at{" "}
        <a href={loginUrl} className="font-semibold text-navy-800 hover:underline" target="_blank" rel="noreferrer">
          {loginUrl.replace(/^https?:\/\//, "")}
        </a>
        .{trial && " Choose a plan before the trial ends to keep this login and your work."}
      </p>
      {trial && (
        <Link href="#plans" className={buttonVariants({ variant: "outline", className: "w-full" })}>
          See plans
        </Link>
      )}
    </div>
  );
}

function Detail({ label, value, secret = false }: { label: string; value: string; secret?: boolean }) {
  const [shown, setShown] = useState(!secret);
  const [copied, setCopied] = useState(false);

  return (
    <div className="flex items-center justify-between gap-3">
      <dt className="text-stone-500">{label}</dt>
      <dd className="flex min-w-0 items-center gap-1.5">
        <span className="truncate font-mono text-[0.82rem] text-ink">{shown ? value : "••••••••••"}</span>
        {secret && (
          <button
            type="button"
            onClick={() => setShown((s) => !s)}
            className="rounded px-1 text-xs font-semibold text-navy-800 hover:underline"
          >
            {shown ? "Hide" : "Show"}
          </button>
        )}
        <button
          type="button"
          aria-label={`Copy ${label.toLowerCase()}`}
          onClick={async () => {
            try {
              await navigator.clipboard.writeText(value);
              setCopied(true);
              setTimeout(() => setCopied(false), 1500);
            } catch {
              // Clipboard blocked: the value is on screen to copy by hand.
            }
          }}
          className="rounded p-1 text-stone-500 hover:bg-white hover:text-ink"
        >
          {copied ? <Check className="size-3.5 text-success" /> : <Copy className="size-3.5" />}
        </button>
      </dd>
    </div>
  );
}
