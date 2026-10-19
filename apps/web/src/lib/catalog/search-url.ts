import { searchParamKeys, type CourseSearchParams } from "./types";

/** Picks the known, single-valued search parameters out of Next's searchParams object. */
export function readSearchParams(raw: Record<string, string | string[] | undefined>): CourseSearchParams {
  const params: CourseSearchParams = {};
  for (const key of searchParamKeys) {
    const value = raw[key];
    const v = Array.isArray(value) ? value[0] : value;
    if (v) params[key] = v.slice(0, 120);
  }
  return params;
}

/** Comma-separated multi-value params (level, language). */
export function listParam(value: string | undefined): string[] {
  return value ? value.split(",").filter(Boolean) : [];
}

/**
 * Builds a browse URL from the current params plus changes. Any filter change resets
 * pagination; pass `null` to remove a key.
 */
export function browseHref(
  basePath: string,
  current: CourseSearchParams,
  changes: Partial<Record<keyof CourseSearchParams, string | null>>,
): string {
  const next = new URLSearchParams();
  const merged: Record<string, string | null | undefined> = { ...current, ...changes };
  if (!("page" in changes)) delete merged.page;
  delete merged.category; // carried by the path on category pages

  for (const key of searchParamKeys) {
    const value = merged[key];
    if (value) next.set(key, value);
  }
  const qs = next.toString();
  return qs ? `${basePath}?${qs}` : basePath;
}

/** Toggle one value inside a comma-separated list param. */
export function toggleListValue(list: string[], value: string): string | null {
  const nextList = list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
  return nextList.length ? nextList.join(",") : null;
}
