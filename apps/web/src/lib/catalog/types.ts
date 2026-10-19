import type { components } from "@erudsway/api-client";

export type CourseCard = components["schemas"]["CourseCardResource"];
export type CourseDetail = components["schemas"]["CourseDetailResource"];
export type Category = components["schemas"]["CategoryResource"];

export type CourseLevel = "beginner" | "intermediate" | "advanced" | "all";

export const levelLabel: Record<string, string> = {
  beginner: "Beginner",
  intermediate: "Intermediate",
  advanced: "Advanced",
  all: "All levels",
};

export const badgeLabel: Record<string, string> = {
  bestseller: "Bestseller",
  highest_rated: "Highest rated",
  new: "New",
};

interface FacetOption<T = string> {
  value: T;
  count: number;
  label?: string;
}

/**
 * Shape of `meta` on GET /courses. Declared here because the generated OpenAPI types
 * describe pagination and facets only loosely (the search engine returns them generically).
 */
export interface CourseSearchMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  facets: {
    levels: FacetOption<CourseLevel>[];
    languages: FacetOption[];
    ratings: FacetOption<number>[];
    durations: FacetOption<"short" | "medium" | "long" | "extra_long">[];
    prices: FacetOption<"free" | "paid">[];
    total: number;
  };
}

export interface CourseSearchResult {
  data: CourseCard[];
  meta: CourseSearchMeta;
}

export interface InstructorPage {
  slug: string;
  name: string;
  headline: string | null;
  bio: string | null;
  stats: { learners: number; reviews: number; rating: number; courses: number };
  courses: CourseCard[];
}

/** Query parameters accepted by GET /courses (all optional, all strings). */
export interface CourseSearchParams {
  q?: string;
  category?: string;
  level?: string;
  language?: string;
  rating?: string;
  price?: string;
  duration?: string;
  sort?: string;
  page?: string;
  per_page?: string;
}

export const searchParamKeys = [
  "q",
  "category",
  "level",
  "language",
  "rating",
  "price",
  "duration",
  "sort",
  "page",
  "per_page",
] as const satisfies readonly (keyof CourseSearchParams)[];
