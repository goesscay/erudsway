"use client";

import { Check } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";

/** Sticky save row for builder steps. */
export function SaveBar({
  dirty,
  saving,
  saved,
  error,
  locked,
}: {
  dirty: boolean;
  saving: boolean;
  saved: boolean;
  error: string | null;
  locked?: boolean;
}) {
  return (
    <div className="sticky bottom-0 mt-10 -mx-4 border-t border-line bg-paper/95 px-4 py-4 backdrop-blur sm:-mx-0 sm:px-0">
      {error && (
        <div className="mb-3">
          <FormAlert>{error}</FormAlert>
        </div>
      )}
      <div className="flex items-center gap-4">
        <Button type="submit" disabled={!dirty || saving || locked}>
          {saving ? "Saving…" : "Save changes"}
        </Button>
        <span role="status" className="text-sm text-stone-500">
          {locked ? (
            "Editing is paused while the course is in review."
          ) : dirty ? (
            "You have unsaved changes."
          ) : saved ? (
            <span className="inline-flex items-center gap-1.5 text-success">
              <Check className="size-4" /> Saved
            </span>
          ) : null}
        </span>
      </div>
    </div>
  );
}
