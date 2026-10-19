"use client";

import { useRouter } from "next/navigation";
import { sortOptions } from "@/lib/catalog/sort-options";

/** Changes `sort` while keeping the other filters; the target URLs are computed on the server. */
export function SortSelect({ value, hrefs }: { value: string; hrefs: Record<string, string> }) {
  const router = useRouter();

  return (
    <label className="flex items-center gap-2 text-sm text-stone-700">
      <span className="whitespace-nowrap">Sort by</span>
      <select
        value={value}
        onChange={(e) => {
          const target = hrefs[e.target.value];
          if (target) router.push(target);
        }}
        className="h-10 rounded-md border border-input bg-white px-3 text-sm font-medium text-ink focus:border-navy-900 focus:outline-none"
      >
        {sortOptions.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  );
}
