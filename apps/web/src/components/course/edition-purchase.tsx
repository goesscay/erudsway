"use client";

import { useId, useState } from "react";
import { Check } from "lucide-react";
import type { CourseDetail } from "@/lib/catalog/types";
import { formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";
import { PurchaseActions } from "./purchase-actions";

type Edition = CourseDetail["editions"][number];

/**
 * "Choose your option": a course sold in editions. Each card lists what it adds to the one
 * before; the price and the cart buttons follow the chosen edition.
 */
export function EditionPurchase({ slug, editions }: { slug: string; editions: Edition[] }) {
  const initial = editions.find((e) => e.is_recommended) ?? editions[0];
  const [chosen, setChosen] = useState(initial.id);
  const name = useId();
  const current = editions.find((e) => e.id === chosen) ?? initial;
  // An access period every edition shares is shown once, under "This course includes".
  const sharedAccess = new Set(editions.map((e) => e.access_days)).size === 1;

  return (
    <div className="space-y-4">
      <div>
        <p className="tabular font-serif text-3xl font-semibold text-ink">{formatPaise(current.price.amount)}</p>
        <p className="text-xs text-stone-500">{current.name}</p>
      </div>
      <PurchaseActions slug={slug} free={false} edition={current.id} />

      <fieldset>
        <legend className="mb-2 text-sm font-bold text-ink">Choose your option</legend>
        <div className="space-y-2">
          {editions.map((e, i) => {
            const selected = e.id === chosen;
            return (
              <label
                key={e.id}
                className={cn(
                  "block cursor-pointer rounded-md border bg-white p-3.5 transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-navy-900",
                  selected ? "border-navy-900 ring-1 ring-navy-900" : "border-line hover:border-stone-500/60",
                )}
              >
                <span className="flex items-start gap-3">
                  <input
                    type="radio"
                    name={name}
                    value={e.id}
                    checked={selected}
                    onChange={() => setChosen(e.id)}
                    className="mt-1 size-4 shrink-0 accent-[var(--navy-900)]"
                  />
                  <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-baseline justify-between gap-x-3">
                      <span className="font-semibold text-ink">
                        {e.name}
                        {e.is_recommended && (
                          <span className="ml-2 inline-flex h-5 items-center rounded-sm bg-gold-50 px-1.5 align-middle text-[0.62rem] font-bold tracking-wide text-gold-600 uppercase ring-1 ring-gold-200 ring-inset">
                            Recommended
                          </span>
                        )}
                      </span>
                      <span className="tabular text-sm font-semibold text-ink">{formatPaise(e.price.amount)}</span>
                    </span>
                    {i > 0 && (
                      <span className="mt-1 block text-xs text-stone-500">
                        Everything in {editions[i - 1].name}, plus:
                      </span>
                    )}
                    <ul className="mt-1 space-y-0.5 text-xs leading-relaxed text-stone-700">
                      {e.includes.map((item) => (
                        <li key={item} className="flex gap-1.5">
                          <Check className="mt-0.5 size-3 shrink-0 text-success" aria-hidden />
                          {item}
                        </li>
                      ))}
                      {e.video_view_limit !== null && (
                        <li className="flex gap-1.5 text-stone-500">
                          <Check className="mt-0.5 size-3 shrink-0 text-stone-500" aria-hidden />
                          {e.video_view_limit === 1
                            ? "1 full view of each video"
                            : `${e.video_view_limit} full views of each video`}
                        </li>
                      )}
                      {e.access_days !== null && !sharedAccess && (
                        <li className="flex gap-1.5 text-stone-500">
                          <Check className="mt-0.5 size-3 shrink-0 text-stone-500" aria-hidden />
                          {e.access_days} days of access
                        </li>
                      )}
                    </ul>
                  </span>
                </span>
              </label>
            );
          })}
        </div>
      </fieldset>
    </div>
  );
}
