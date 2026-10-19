import type { MetadataRoute } from "next";
import { connection } from "next/server";
import { getCategories, searchCourses } from "@/lib/catalog/queries";
import type { CourseCard } from "@/lib/catalog/types";

const SITE = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";
const MAX_PAGES = 40; // 48 × 40 = 1,920 courses; split with generateSitemaps when the catalogue outgrows this

/** Built at request time (and then cached) so the build never depends on the API. */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  await connection();

  const courses: CourseCard[] = [];
  for (let page = 1; page <= MAX_PAGES; page++) {
    const result = await searchCourses({ sort: "newest", per_page: "48", page: String(page) }).catch(() => null);
    if (!result) break;
    courses.push(...result.data);
    if (page >= result.meta.last_page) break;
  }
  const categories = await getCategories().catch(() => []);
  const instructorSlugs = [...new Set(courses.map((c) => c.instructor.slug).filter((s): s is string => !!s))];

  return [
    { url: SITE, changeFrequency: "daily", priority: 1 },
    { url: `${SITE}/courses`, changeFrequency: "daily", priority: 0.9 },
    { url: `${SITE}/teach`, changeFrequency: "monthly", priority: 0.5 },
    ...categories.flatMap((c) => [
      { url: `${SITE}/courses/${c.slug}`, changeFrequency: "weekly" as const, priority: 0.8 },
      ...(c.children ?? []).map((s) => ({ url: `${SITE}/courses/${s.slug}`, changeFrequency: "weekly" as const, priority: 0.7 })),
    ]),
    ...courses.map((c) => ({
      url: `${SITE}/course/${c.slug}`,
      lastModified: new Date(c.updated_at),
      changeFrequency: "weekly" as const,
      priority: 0.8,
    })),
    ...instructorSlugs.map((s) => ({ url: `${SITE}/instructors/${s}`, changeFrequency: "monthly" as const, priority: 0.5 })),
  ];
}
