"use client";

import { Plus, X } from "lucide-react";
import { TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";

/** Editable list of short text items (learning outcomes, requirements, audience). */
export function ListEditor({
  items,
  onChange,
  placeholder,
  max,
  minVisible = 1,
  label,
}: {
  items: string[];
  onChange: (items: string[]) => void;
  placeholder: string;
  max: number;
  minVisible?: number;
  label: string;
}) {
  const rows = items.length >= minVisible ? items : [...items, ...Array(minVisible - items.length).fill("")];

  const update = (i: number, value: string) => onChange(rows.map((r, j) => (j === i ? value : r)));
  const remove = (i: number) => onChange(rows.filter((_, j) => j !== i));

  return (
    <div className="space-y-2">
      {rows.map((value, i) => (
        <div key={i} className="flex items-center gap-2">
          <TextInput
            value={value}
            maxLength={160}
            aria-label={`${label} ${i + 1}`}
            placeholder={i === 0 ? placeholder : ""}
            onChange={(e) => update(i, e.target.value)}
          />
          <button
            type="button"
            onClick={() => remove(i)}
            aria-label={`Remove ${label.toLowerCase()} ${i + 1}`}
            className="rounded-sm p-2 text-stone-500 hover:bg-paper-2 hover:text-destructive"
          >
            <X className="size-4" />
          </button>
        </div>
      ))}
      {rows.length < max && (
        <Button type="button" variant="ghost" size="sm" onClick={() => onChange([...rows, ""])} className="text-navy-800">
          <Plus /> Add another
        </Button>
      )}
    </div>
  );
}
