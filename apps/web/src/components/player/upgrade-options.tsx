"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ArrowUpCircle, Check } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { ApiRequestError } from "@/lib/api/client";
import { useCartActions } from "@/lib/commerce/api";
import { formatPaise } from "@/lib/format";
import type { LearnCourse } from "@/lib/learning/api";

/** Higher editions the learner can move to; buying one charges only the difference. */
export function UpgradeOptions({ course, highlight }: { course: LearnCourse; highlight?: number | null }) {
  const router = useRouter();
  const { add } = useCartActions();
  const [busy, setBusy] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const upgrade = async (edition: number) => {
    setBusy(edition);
    setError(null);
    try {
      await add.mutateAsync({ slug: course.slug, edition });
      router.push("/checkout");
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Something went wrong. Please try again.");
      setBusy(null);
    }
  };

  return (
    <div className="space-y-3">
      {error && <FormAlert>{error}</FormAlert>}
      {course.upgrades.map((u) => (
        <div
          key={u.id}
          className={`flex flex-wrap items-start justify-between gap-3 rounded-md border bg-white p-4 ${u.id === highlight ? "border-navy-900 ring-1 ring-navy-900" : "border-line"}`}
        >
          <div className="min-w-0">
            <p className="font-semibold text-ink">{u.name}</p>
            <ul className="mt-1 space-y-0.5 text-xs text-stone-700">
              {u.includes.map((item) => (
                <li key={item} className="flex gap-1.5">
                  <Check className="mt-0.5 size-3 shrink-0 text-success" aria-hidden />
                  {item}
                </li>
              ))}
            </ul>
          </div>
          <Button onClick={() => upgrade(u.id)} disabled={busy !== null}>
            <ArrowUpCircle /> {busy === u.id ? "Adding…" : `Upgrade for ${formatPaise(u.upgrade_price)}`}
          </Button>
        </div>
      ))}
    </div>
  );
}
