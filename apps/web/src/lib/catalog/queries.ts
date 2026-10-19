import { cacheLife, cacheTag } from "next/cache";
import type { Category, CourseDetail, CourseSearchParams, CourseSearchResult, InstructorPage } from "./types";

/** Server-side base URL: an internal address in production if set, else the public one. */
const API_BASE = `${process.env.API_INTERNAL_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1`;

class CatalogError extends Error {}

/**
 * GET from the public catalogue API. Returns null on 404 so pages can call notFound().
 * Other failures throw, which surfaces the segment's error boundary and is never cached.
 */
async function get<T>(path: string, query?: Record<string, string | undefined>): Promise<T | null> {
  const url = new URL(`${API_BASE}${path}`);
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== undefined && value !== "") url.searchParams.set(key, value);
  }

  const res = await fetch(url, { headers: { Accept: "application/json" } });
  if (res.status === 404) return null;
  if (!res.ok) throw new CatalogError(`Catalogue request failed: ${res.status} ${url.pathname}`);

  return (await res.json()) as T;
}

// Every function below is cached at runtime for a few minutes and tagged so a future
// "course published" webhook can call revalidateTag("catalog").

export async function getCategories(): Promise<Category[]> {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return (await get<{ data: Category[] }>("/categories"))?.data ?? [];
}

export async function getCategory(slug: string): Promise<Category | null> {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return (await get<{ data: Category }>(`/categories/${encodeURIComponent(slug)}`))?.data ?? null;
}

export async function searchCourses(params: CourseSearchParams): Promise<CourseSearchResult | null> {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return get<CourseSearchResult>("/courses", { ...params });
}

export async function getCourse(slug: string): Promise<CourseDetail | null> {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `course:${slug}`);
  return (await get<{ data: CourseDetail }>(`/courses/${encodeURIComponent(slug)}`))?.data ?? null;
}

export async function getInstructor(slug: string): Promise<InstructorPage | null> {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return (await get<{ data: InstructorPage }>(`/instructors/${encodeURIComponent(slug)}`))?.data ?? null;
}
