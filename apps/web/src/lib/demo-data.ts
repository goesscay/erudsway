import type { CourseCard } from "@/lib/catalog/types";

/**
 * Design-review data only. The catalogue and learning pages read the API; this file keeps
 * the marketing photos and the design-system sheet's sample cards. Names are fictional.
 * Photos: Unsplash, credited where shown.
 */

export interface Photo {
  src: string;
  alt: string;
  credit: string;
  creditUrl: string;
}

const u = (id: string, w = 1200) => `https://images.unsplash.com/${id}?auto=format&fit=crop&w=${w}&q=80`;

export const photos = {
  hero: {
    src: u("photo-1675664532731-c8caede9ef6b", 1600),
    alt: "A student working on a laptop outdoors",
    credit: "Sanket Mishra",
    creditUrl: "https://unsplash.com/@sanketgraphy",
  },
  teach: {
    src: u("photo-1593697820826-2e76c9720a99", 1400),
    alt: "A laptop and microphone set up for recording a lesson",
    credit: "Soundtrap",
    creditUrl: "https://unsplash.com/@soundtrap",
  },
} satisfies Record<string, Photo>;

const card = (c: Partial<CourseCard> & Pick<CourseCard, "slug" | "title">): CourseCard => ({
  id: c.slug,
  subtitle: null,
  thumbnail_url: null,
  instructor: { name: "CA Vikram Sethi", slug: null },
  category: { slug: "gst", name: "GST" },
  level: "all",
  language: "English",
  rating: 4.7,
  rating_count: 1200,
  enrollment_count: 9000,
  duration_seconds: 5 * 3600,
  lecture_count: 32,
  price: { amount: 49900, currency: "INR" },
  badge: null,
  updated_at: "2026-10-01T00:00:00Z",
  ...c,
});

/** Sample cards for the design-system sheet. */
export const sampleCards: CourseCard[] = [
  card({
    slug: "gst-practitioner-course",
    title: "GST Practitioner Course: Registration to Annual Return",
    thumbnail_url: u("photo-1711097383282-28097ae16b1d", 800),
    badge: "bestseller",
    rating: 4.8,
    rating_count: 8196,
  }),
  card({
    slug: "tds-tcs-compliance",
    title: "TDS & TCS Compliance: Deduction, Returns and Certificates",
    thumbnail_url: u("photo-1554224154-26032ffc0d07", 800),
    instructor: { name: "CA Nandini Iyer", slug: null },
    badge: "highest_rated",
  }),
  card({
    slug: "gst-notices-and-appeals",
    title: "GST Notices and Appeals: Drafting Replies That Work",
    thumbnail_url: u("photo-1767972159871-b9f5d320be2b", 800),
    instructor: { name: "Adv. Farah Qureshi", slug: null },
    level: "advanced",
    price: { amount: 79900, currency: "INR" },
    badge: "new",
  }),
  card({
    slug: "e-invoicing-e-way-bills",
    title: "E-invoicing and E-way Bills Made Simple",
    thumbnail_url: u("photo-1626266061368-46a8f578ddd6", 800),
    language: "Hindi",
    level: "beginner",
    price: null,
  }),
];
