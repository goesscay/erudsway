"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarClock, Copy, ExternalLink, MonitorCog } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";

export const extrasKey = (slug: string) => ["learn", slug, "extras"] as const;

/** What the learner's edition adds beyond the course: simulator access and a doubt session. */
export function useExtras(slug: string, enabled: boolean) {
  return useQuery({
    queryKey: extrasKey(slug),
    queryFn: async () => (await unwrap(api.GET("/me/courses/{slug}/extras", { params: { path: { slug } } }))).data,
    enabled,
    // While the simulator login is being created, check back now and then.
    refetchInterval: (q) => (q.state.data?.simulator?.status === "preparing" ? 15_000 : false),
  });
}

export function ExtrasPanel({ slug }: { slug: string }) {
  const extras = useExtras(slug, true);
  if (!extras.data) return <Skeleton className="h-40 w-full" />;
  const { simulator, doubt_session: doubt } = extras.data;

  return (
    <div className="grid gap-5 md:grid-cols-2">
      {simulator && <SimulatorCard slug={slug} simulator={simulator} />}
      {doubt && <DoubtSessionCard slug={slug} doubt={doubt} />}
    </div>
  );
}

type Extras = NonNullable<ReturnType<typeof useExtras>["data"]>;

function SimulatorCard({ slug, simulator }: { slug: string; simulator: NonNullable<Extras["simulator"]> }) {
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const open = useMutation({
    mutationFn: async () => (await mutate(() => api.POST("/me/courses/{slug}/simulator-link", { params: { path: { slug } } }))).data.url,
    onSuccess: (url) => window.open(url, "_blank", "noopener"),
    onError: (e) => setError(e instanceof ApiRequestError ? e.message : "Couldn’t open the simulator."),
  });

  return (
    <section aria-labelledby="sim-title" className="rounded-md border border-line bg-white p-5">
      <MonitorCog className="size-6 text-gold-600" aria-hidden />
      <h3 id="sim-title" className="mt-2 font-sans text-base font-bold text-ink">
        GST simulator
      </h3>
      {simulator.status !== "ready" ? (
        <p className="mt-1 text-sm text-stone-700">Your practice login is being created — this usually takes a few minutes. We&rsquo;ll email you too.</p>
      ) : (
        <>
          <p className="mt-1 text-sm text-stone-700">Practise registrations, returns and reconciliations on a realistic GST portal.</p>
          {error && (
            <div className="mt-3">
              <FormAlert>{error}</FormAlert>
            </div>
          )}
          <Button className="mt-4" onClick={() => open.mutate()} disabled={open.isPending}>
            <ExternalLink /> {open.isPending ? "Opening…" : "Open the GST simulator"}
          </Button>
          <dl className="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-sm bg-paper px-3 py-2.5 text-sm">
            <dt className="text-stone-500">Username</dt>
            <dd className="font-mono text-ink">{simulator.username}</dd>
            <dt className="text-stone-500">Password</dt>
            <dd className="font-mono text-ink">{simulator.password}</dd>
            <dt className="text-stone-500">Practice GSTIN</dt>
            <dd className="font-mono text-ink">{simulator.gstin}</dd>
          </dl>
          <div className="mt-2 flex items-center gap-3 text-xs">
            <button
              type="button"
              className="inline-flex items-center gap-1 font-semibold text-navy-800 hover:underline"
              onClick={() => {
                void navigator.clipboard.writeText(`${simulator.username}\n${simulator.password}`).then(() => setCopied(true));
              }}
            >
              <Copy className="size-3.5" aria-hidden /> {copied ? "Copied" : "Copy login"}
            </button>
            <a href={simulator.login_url} target="_blank" rel="noopener" className="font-semibold text-navy-800 hover:underline">
              Simulator login page
            </a>
          </div>
        </>
      )}
    </section>
  );
}

function DoubtSessionCard({ slug, doubt }: { slug: string; doubt: NonNullable<Extras["doubt_session"]> }) {
  const queryClient = useQueryClient();
  const [times, setTimes] = useState(doubt.preferred_times ?? "");
  const [questions, setQuestions] = useState(doubt.questions ?? "");
  const [phone, setPhone] = useState("");
  const [error, setError] = useState<string | null>(null);
  const request = useMutation({
    mutationFn: async () =>
      (
        await mutate(() =>
          api.POST("/me/courses/{slug}/doubt-session", {
            params: { path: { slug } },
            body: { preferred_times: times.trim(), questions: questions.trim(), phone: phone.trim() || null },
          }),
        )
      ).data,
    onSuccess: (data) => queryClient.setQueryData(extrasKey(slug), data),
    onError: (e) => setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Couldn’t send your request."),
  });
  const when = doubt.scheduled_for
    ? new Date(doubt.scheduled_for).toLocaleString("en-IN", { weekday: "long", day: "numeric", month: "long", hour: "numeric", minute: "2-digit" })
    : null;

  return (
    <section aria-labelledby="doubt-title" className="rounded-md border border-line bg-white p-5">
      <CalendarClock className="size-6 text-gold-600" aria-hidden />
      <h3 id="doubt-title" className="mt-2 font-sans text-base font-bold text-ink">
        60-minute doubt session
      </h3>
      {doubt.status === "scheduled" || doubt.status === "done" ? (
        <div className="mt-1 space-y-1 text-sm text-stone-700">
          <p>
            {doubt.status === "done" ? "Completed." : "Scheduled for"} {when && <strong className="text-ink">{when} IST</strong>}
          </p>
          {doubt.notes && <p className="whitespace-pre-line">{doubt.notes}</p>}
        </div>
      ) : (
        <form
          className="mt-2 space-y-3"
          onSubmit={(e) => {
            e.preventDefault();
            setError(null);
            request.mutate();
          }}
        >
          <p className="text-sm text-stone-700">
            {doubt.status === "requested"
              ? "Request received — our faculty will confirm a time by email. You can still update it."
              : "Tell us when suits you and what you’d like to cover; we’ll confirm a time."}
          </p>
          {error && <FormAlert>{error}</FormAlert>}
          <div className="space-y-1.5">
            <Label htmlFor="ds-times">Preferred days and times</Label>
            <Input id="ds-times" value={times} onChange={(e) => setTimes(e.target.value)} placeholder="Weekday evenings after 7 pm" maxLength={500} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ds-questions">What would you like to cover?</Label>
            <Textarea id="ds-questions" rows={3} value={questions} onChange={(e) => setQuestions(e.target.value)} maxLength={3000} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ds-phone">Phone / WhatsApp (optional)</Label>
            <Input id="ds-phone" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={20} />
          </div>
          <Button type="submit" disabled={request.isPending || times.trim().length < 5 || questions.trim().length < 10}>
            {request.isPending ? "Sending…" : doubt.status === "requested" ? "Update request" : "Request my session"}
          </Button>
        </form>
      )}
    </section>
  );
}
