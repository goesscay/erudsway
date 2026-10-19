import type { CourseCard } from "@/lib/catalog/types";

/**
 * Design-review data only. The catalogue pages read the API; this file still feeds the
 * learning-player and course-builder mock-ups (replaced in Phases 3–4) and the design-system
 * sheet. Names are fictional. Photos: Unsplash, credited where shown.
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

export interface Lecture {
  title: string;
  seconds: number;
  preview?: boolean;
  kind: "video" | "article" | "quiz";
}

export interface Section {
  title: string;
  lectures: Lecture[];
}

export interface DemoCourse {
  slug: string;
  title: string;
  subtitle: string;
  instructorName: string;
  rating: number;
  ratingCount: number;
  students: number;
  seconds: number;
  image: Photo;
  curriculum: Section[];
}

const v = (title: string, seconds: number, preview = false): Lecture => ({ title, seconds, preview, kind: "video" });

const gstCurriculum: Section[] = [
  {
    title: "Getting oriented",
    lectures: [
      v("How this course is organised", 312, true),
      v("GST in one picture: supply, tax and credit", 905, true),
      { title: "Working files and checklists", seconds: 240, kind: "article" },
    ],
  },
  {
    title: "Registration",
    lectures: [
      v("Who must register, and when", 864),
      v("Registering on the GST portal, step by step", 1322),
      v("Amendments, cancellation and revocation", 846),
      { title: "Registration quiz", seconds: 300, kind: "quiz" },
    ],
  },
  {
    title: "Supply, valuation and place of supply",
    lectures: [
      v("What counts as a supply", 980),
      v("Valuation rules with examples", 1215),
      v("Place of supply for goods and services", 1410),
      v("Reverse charge mechanism", 1030),
    ],
  },
  {
    title: "Invoices and monthly returns",
    lectures: [
      v("Tax invoices, credit notes and debit notes", 1290),
      v("Filing GSTR-1", 1705),
      v("Filing GSTR-3B", 1620),
      { title: "Monthly returns quiz", seconds: 300, kind: "quiz" },
    ],
  },
  {
    title: "Input tax credit",
    lectures: [
      v("Conditions to claim ITC", 1120),
      v("Blocked credits under Section 17(5)", 960),
      v("Reconciling with GSTR-2B", 1840),
    ],
  },
  {
    title: "Year end",
    lectures: [v("GSTR-9: the annual return", 1530), v("Compliance calendar and checklist", 410)],
  },
];

export const courses: DemoCourse[] = [
  {
    slug: "gst-practitioner-course",
    title: "GST Practitioner Course: Registration to Annual Return",
    subtitle:
      "The complete monthly and annual GST cycle — registrations, invoices, GSTR-1, GSTR-3B, ITC and GSTR-9 — as a practising CA runs it.",
    instructorName: "CA Vikram Sethi",
    rating: 4.8,
    ratingCount: 8196,
    students: 48210,
    seconds: gstCurriculum.flatMap((s) => s.lectures).reduce((n, l) => n + l.seconds, 0),
    image: {
      src: u("photo-1711097383282-28097ae16b1d", 960),
      alt: "Person reviewing financial documents at a laptop",
      credit: "Jakub Żerdzicki",
      creditUrl: "https://unsplash.com/@jakubzerdzicki",
    },
    curriculum: gstCurriculum,
  },
];

export function getCourse(slug: string): DemoCourse | undefined {
  return courses.find((c) => c.slug === slug);
}

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
