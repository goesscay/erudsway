"use client";

import Link from "next/link";
import { useState } from "react";
import { Controller, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { CircleCheck, Clock3, X } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { SectionHeading } from "@/components/brand/section-heading";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, mutate, unwrap } from "@/lib/api/client";
import { applyApiErrors } from "@/lib/api/form-errors";
import { useMe } from "@/lib/auth/session";
import { applicationSchema, teachingExperience, type ApplicationValues } from "@/lib/validation/auth";
import { cn } from "@/lib/utils";

type Application = components["schemas"]["InstructorApplicationResource"];

const applicationKey = ["instructor-application", "latest"] as const;

export function ApplySection() {
  const { data: me, isPending } = useMe();
  const latest = useQuery({
    queryKey: applicationKey,
    enabled: !!me,
    queryFn: async () => (await unwrap(api.GET("/instructor/applications/latest"))).data as Application | null,
  });

  if (isPending || (me && latest.isPending)) {
    return <Skeleton className="h-72 w-full" />;
  }

  if (!me) {
    return (
      <Panel title="Ready to apply?">
        <p className="text-stone-700">Create a free learner account first — it takes a minute — then come back here to apply.</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link href="/register?next=/teach%23apply" className={buttonVariants({ size: "lg" })}>
            Create account
          </Link>
          <Link href="/login?next=/teach%23apply" className={buttonVariants({ size: "lg", variant: "outline" })}>
            Log in
          </Link>
        </div>
      </Panel>
    );
  }

  if (me.instructor) {
    return (
      <Panel title="You're an Erudsway instructor" icon={<CircleCheck className="size-8 text-success" strokeWidth={1.5} />}>
        <p className="text-stone-700">Head to your instructor dashboard to start building a course.</p>
        <Link href="/instructor" className={buttonVariants({ size: "lg", className: "mt-6" })}>
          Go to instructor dashboard
        </Link>
      </Panel>
    );
  }

  if (!me.email_verified) {
    return (
      <Panel title="Verify your email to apply">
        <p className="text-stone-700">
          We sent a link to <strong className="text-ink">{me.email}</strong>. Once verified, you can apply here.
        </p>
        <Link href="/verify-email?next=/teach%23apply" className={buttonVariants({ variant: "outline", className: "mt-6" })}>
          Resend verification email
        </Link>
      </Panel>
    );
  }

  const application = latest.data;

  if (application?.status === "pending") {
    return (
      <Panel title="Your application is being reviewed" icon={<Clock3 className="size-8 text-gold-600" strokeWidth={1.5} />}>
        <p className="text-stone-700">
          Submitted on{" "}
          {new Date(application.submitted_at).toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" })}.
          We usually respond within five working days, by email.
        </p>
        <dl className="mt-6 grid gap-4 rounded-md border border-line bg-paper p-5 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-stone-500">Headline</dt>
            <dd className="font-medium text-ink">{application.headline}</dd>
          </div>
          <div>
            <dt className="text-stone-500">Topics</dt>
            <dd className="font-medium text-ink">{application.topics.join(", ")}</dd>
          </div>
        </dl>
      </Panel>
    );
  }

  return (
    <div>
      {application?.status === "rejected" && (
        <div className="mb-10">
          <FormAlert tone="info">
            <strong className="block">Your previous application wasn&rsquo;t approved.</strong>
            <span className="mt-1 block">Reviewer notes: {application.review_notes}</span>
            <span className="mt-1 block">You&rsquo;re welcome to apply again with those points addressed.</span>
          </FormAlert>
        </div>
      )}
      <SectionHeading eyebrow="Application" title="Tell us about your expertise">
        Takes about five minutes. Be specific — we read every word.
      </SectionHeading>
      <ApplicationForm />
    </div>
  );
}

function Panel({ title, icon, children }: { title: string; icon?: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="rounded-md border border-line bg-white p-8">
      {icon}
      <h2 className={cn("text-h3", icon && "mt-4")}>{title}</h2>
      <div className="mt-3">{children}</div>
    </div>
  );
}

function ApplicationForm() {
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const [topicDraft, setTopicDraft] = useState("");

  const form = useForm<ApplicationValues>({
    resolver: zodResolver(applicationSchema),
    defaultValues: { headline: "", topics: [], experience: "", teaching_experience: undefined, sample_url: "" },
  });
  const { errors, isSubmitting } = form.formState;
  const topics = useWatch({ control: form.control, name: "topics" });
  const experienceLength = useWatch({ control: form.control, name: "experience" }).trim().length;

  const addTopic = () => {
    const t = topicDraft.trim();
    if (!t || topics.length >= 5 || topics.some((x) => x.toLowerCase() === t.toLowerCase())) return;
    form.setValue("topics", [...topics, t], { shouldValidate: true });
    setTopicDraft("");
  };

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      const res = await mutate(() =>
        api.POST("/instructor/applications", { body: { ...values, sample_url: values.sample_url || null } }),
      );
      queryClient.setQueryData(applicationKey, res.data);
    } catch (e) {
      setFormError(applyApiErrors(e, form.setError, ["headline", "topics", "experience", "teaching_experience", "sample_url"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="mt-10 space-y-7">
      {formError && <FormAlert>{formError}</FormAlert>}

      <Field label="Professional headline" error={errors.headline?.message} hint="e.g. Chartered Accountant · 10 years in indirect tax">
        {(p) => <TextInput {...p} maxLength={120} {...form.register("headline")} />}
      </Field>

      <Field label="What would you teach?" error={errors.topics?.message} hint="Add up to five topics. Press Enter after each.">
        {(p) => (
          <div>
            <div className="flex gap-2">
              <TextInput
                {...p}
                value={topicDraft}
                maxLength={40}
                disabled={topics.length >= 5}
                onChange={(e) => setTopicDraft(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" || e.key === ",") {
                    e.preventDefault();
                    addTopic();
                  }
                }}
                placeholder={topics.length >= 5 ? "Five topics added" : "e.g. GST returns"}
              />
              <Button type="button" variant="outline" className="h-11" onClick={addTopic} disabled={topics.length >= 5}>
                Add
              </Button>
            </div>
            {topics.length > 0 && (
              <ul className="mt-3 flex flex-wrap gap-2">
                {topics.map((t) => (
                  <li key={t} className="inline-flex items-center gap-1.5 rounded-sm bg-navy-100 py-1 pr-1.5 pl-2.5 text-sm font-medium text-navy-800">
                    {t}
                    <button
                      type="button"
                      aria-label={`Remove ${t}`}
                      className="rounded-sm p-0.5 hover:bg-navy-900/10"
                      onClick={() => form.setValue("topics", topics.filter((x) => x !== t), { shouldValidate: true })}
                    >
                      <X className="size-3.5" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </Field>

      <Field
        label="Your professional experience"
        error={errors.experience?.message}
        hint={`${experienceLength} / 3,000 characters · minimum 100`}
      >
        {(p) => (
          <Textarea
            {...p}
            rows={7}
            className="bg-white"
            placeholder="Where you've worked, what you've built or delivered, and why you're the right person to teach this."
            {...form.register("experience")}
          />
        )}
      </Field>

      <fieldset className="space-y-2.5">
        <legend className="mb-2.5 text-sm font-semibold text-ink">Have you taught before?</legend>
        <Controller
          control={form.control}
          name="teaching_experience"
          render={({ field }) => (
            <div className="grid gap-2.5 sm:grid-cols-2">
              {teachingExperience.map((o) => (
                <label
                  key={o.value}
                  className={cn(
                    "flex cursor-pointer items-start gap-3 rounded-md border bg-white px-4 py-3.5 text-sm transition-colors",
                    field.value === o.value ? "border-navy-900 ring-1 ring-navy-900" : "border-line hover:border-input",
                  )}
                >
                  <input
                    type="radio"
                    name={field.name}
                    value={o.value}
                    checked={field.value === o.value}
                    onChange={() => field.onChange(o.value)}
                    className="mt-0.5 accent-[var(--navy-900)]"
                  />
                  {o.label}
                </label>
              ))}
            </div>
          )}
        />
        {errors.teaching_experience && (
          <p className="text-xs font-medium text-destructive">{errors.teaching_experience.message}</p>
        )}
      </fieldset>

      <Field
        label="Sample lesson or portfolio link"
        optional
        error={errors.sample_url?.message}
        hint="A YouTube video, slide deck, blog or portfolio that shows how you explain things."
      >
        {(p) => <TextInput {...p} type="url" placeholder="https://" {...form.register("sample_url")} />}
      </Field>

      <div className="flex items-center gap-4 border-t border-line pt-7">
        <Button type="submit" size="lg" disabled={isSubmitting}>
          {isSubmitting ? "Submitting…" : "Submit application"}
        </Button>
        <p className="text-xs text-stone-500">We&rsquo;ll email you when it&rsquo;s been reviewed.</p>
      </div>
    </form>
  );
}
