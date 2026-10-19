"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { SaveBar } from "@/components/authoring/save-bar";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, unwrap } from "@/lib/api/client";
import { useEditorAction, type CourseEditor } from "@/lib/authoring/editor";
import { formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";
import { StepHeader, StepSkeleton, useEditableCourse } from "../builder-shell";

export default function PricingStep() {
  const { course } = useEditableCourse();
  if (!course) return <StepSkeleton />;
  return <PricingForm key={course.id} course={course} />;
}

function PricingForm({ course }: { course: CourseEditor }) {
  // "free" | tier id as string | "" (not chosen yet)
  const initial = course.pricing_confirmed ? (course.price_tier_id ? String(course.price_tier_id) : "free") : "";
  const [choice, setChoice] = useState(initial);
  const [baseline, setBaseline] = useState(initial);
  const [error, setError] = useState<string | null>(null);
  const locked = course.status === "in_review" || course.status === "archived";

  const tiers = useQuery({
    queryKey: ["price-tiers"],
    queryFn: async () => (await unwrap(api.GET("/instructor/price-tiers"))).data,
  });

  const save = useEditorAction(course.id, () =>
    unwrap(
      api.PATCH("/instructor/courses/{course}", {
        params: { path: { course: course.id } },
        body: { price_tier_id: choice === "free" ? null : Number(choice) },
      }),
    ),
  );

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        setError(null);
        save.mutate(undefined, {
          onSuccess: () => setBaseline(choice),
          onError: (err) => setError(err instanceof ApiRequestError ? err.message : "Couldn’t save."),
        });
      }}
    >
      <StepHeader title="Pricing">
        Choose one of Erudsway&rsquo;s price points, or make the course free. Prices include GST. Your earnings per sale are
        shown in your revenue statement once checkout opens.
      </StepHeader>

      <fieldset disabled={locked} className="max-w-xl">
        <legend className="sr-only">Course price</legend>
        {tiers.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : (
          <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
            {[{ value: "free", label: "Free" }, ...(tiers.data ?? []).map((t) => ({ value: String(t.id), label: formatPaise(t.amount) }))].map((o) => (
              <label
                key={o.value}
                className={cn(
                  "flex cursor-pointer items-center gap-3 rounded-md border bg-white px-4 py-3.5 text-sm font-semibold transition-colors",
                  choice === o.value ? "border-navy-900 text-navy-900 ring-1 ring-navy-900" : "border-line text-stone-700 hover:border-input",
                )}
              >
                <input type="radio" name="price" value={o.value} checked={choice === o.value} onChange={() => setChoice(o.value)} className="accent-[var(--navy-900)]" />
                <span className="tabular">{o.label}</span>
              </label>
            ))}
          </div>
        )}
        {choice === "free" && (
          <p className="mt-4 text-sm text-stone-500">Free courses are a good way to build an audience; they can&rsquo;t be switched to paid for learners who already enrolled.</p>
        )}
      </fieldset>

      <SaveBar dirty={choice !== "" && choice !== baseline} saving={save.isPending} saved={save.isSuccess && choice === baseline} error={error} locked={locked} />
    </form>
  );
}
