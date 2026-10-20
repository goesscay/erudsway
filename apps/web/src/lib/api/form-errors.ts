import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { ApiRequestError } from "./client";

/**
 * Maps an API validation error onto react-hook-form fields.
 * Returns a message for the form-level alert when the error isn't tied to a known field.
 */
export function applyApiErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  knownFields: readonly Path<T>[],
): string | null {
  if (!(error instanceof ApiRequestError)) {
    return "Something went wrong. Please try again.";
  }

  let unmatched = false;
  for (const [field, messages] of Object.entries(error.fields)) {
    // "topics.0" → "topics"
    const root = field.split(".")[0] as Path<T>;
    if (knownFields.includes(root)) {
      setError(root, { type: "server", message: messages[0] });
    } else {
      unmatched = true;
    }
  }

  if (Object.keys(error.fields).length === 0 || unmatched) {
    return error.code === "TOO_MANY_REQUESTS"
      ? "Too many attempts. Please wait a minute and try again."
      : error.message;
  }
  return null;
}
