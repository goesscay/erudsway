"use client";

import { useState } from "react";
import { ListEditor } from "@/components/authoring/list-editor";
import { SaveBar } from "@/components/authoring/save-bar";
import { api, ApiRequestError, unwrap } from "@/lib/api/client";
import { useEditorAction, type CourseEditor } from "@/lib/authoring/editor";
import { StepHeader, StepSkeleton, useEditableCourse } from "../builder-shell";

export default function LearnersStep() {
  const { course } = useEditableCourse();
  if (!course) return <StepSkeleton />;
  return <LearnersForm key={course.id} course={course} />;
}

function LearnersForm({ course }: { course: CourseEditor }) {
  const [outcomes, setOutcomes] = useState(course.outcomes);
  const [requirements, setRequirements] = useState(course.requirements);
  const [audience, setAudience] = useState(course.audience);
  const [error, setError] = useState<string | null>(null);
  const save = useEditorAction(course.id, () =>
    unwrap(
      api.PATCH("/instructor/courses/{course}", {
        params: { path: { course: course.id } },
        body: { outcomes: clean(outcomes), requirements: clean(requirements), audience: clean(audience) },
      }),
    ),
  );
  const dirty =
    JSON.stringify(clean(outcomes)) !== JSON.stringify(course.outcomes) ||
    JSON.stringify(clean(requirements)) !== JSON.stringify(course.requirements) ||
    JSON.stringify(clean(audience)) !== JSON.stringify(course.audience);
  const locked = course.status === "in_review" || course.status === "archived";

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        setError(null);
        save.mutate(undefined, {
          onError: (err) => setError(err instanceof ApiRequestError ? (Object.values(err.fields)[0]?.[0] ?? err.message) : "Couldn’t save."),
        });
      }}
    >
      <StepHeader title="Intended learners">
        These appear on your course landing page and help the right learners find the course.
      </StepHeader>

      <fieldset disabled={locked} className="space-y-10">
        <Section
          title="What will learners be able to do?"
          hint="At least four. Start each with a verb — “File GSTR-3B without late fees”, “Reconcile ITC with GSTR-2B”."
        >
          <ListEditor label="Outcome" items={outcomes} onChange={setOutcomes} max={12} minVisible={4} placeholder="e.g. Prepare and file GSTR-9 with the reconciliation statement" />
        </Section>
        <Section title="What do they need to know beforehand?" hint="Prerequisites, software or accounts they should have.">
          <ListEditor label="Requirement" items={requirements} onChange={setRequirements} max={10} placeholder="e.g. Basic understanding of double-entry bookkeeping" />
        </Section>
        <Section title="Who is this course for?" hint="Be specific about roles and experience.">
          <ListEditor label="Audience" items={audience} onChange={setAudience} max={10} placeholder="e.g. Articled assistants handling GST compliance for clients" />
        </Section>
      </fieldset>

      <SaveBar dirty={dirty} saving={save.isPending} saved={save.isSuccess && !dirty} error={error} locked={locked} />
    </form>
  );
}

function Section({ title, hint, children }: { title: string; hint: string; children: React.ReactNode }) {
  return (
    <section className="max-w-2xl">
      <h2 className="font-sans text-base font-bold text-ink">{title}</h2>
      <p className="mt-1 mb-4 text-sm text-stone-500">{hint}</p>
      {children}
    </section>
  );
}

const clean = (items: string[]) => items.map((s) => s.trim()).filter(Boolean);
