/**
 * Phase 0 design-review data only. Every screen built on this file is replaced
 * by real API data in Phases 2–4; names below are fictional.
 * Photos: Unsplash (credits kept next to each image for attribution).
 */

export interface Photo {
  src: string;
  alt: string;
  credit: string;
  creditUrl: string;
}

const u = (id: string, w = 1200) =>
  `https://images.unsplash.com/${id}?auto=format&fit=crop&w=${w}&q=80`;

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

export interface Instructor {
  name: string;
  slug: string;
  headline: string;
  students: number;
  courses: number;
  rating: number;
}

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

export interface Course {
  slug: string;
  title: string;
  subtitle: string;
  category: string;
  level: "Beginner" | "Intermediate" | "Advanced" | "All levels";
  language: string;
  instructor: Instructor;
  rating: number;
  ratingCount: number;
  students: number;
  seconds: number;
  lectures: number;
  pricePaise: number;
  listPricePaise: number;
  badge?: "Bestseller" | "New" | "Highest rated";
  updated: string;
  image: Photo;
  outcomes: string[];
  requirements: string[];
  curriculum: Section[];
}

const instructors: Record<string, Instructor> = {
  ananya: {
    name: "Ananya Raghavan",
    slug: "ananya-raghavan",
    headline: "Senior Software Engineer · 12 years building web platforms",
    students: 184230,
    courses: 6,
    rating: 4.7,
  },
  vikram: {
    name: "Vikram Sethi, CA",
    slug: "vikram-sethi",
    headline: "Chartered Accountant · Corporate finance trainer",
    students: 92310,
    courses: 4,
    rating: 4.8,
  },
  meera: {
    name: "Meera Kulkarni",
    slug: "meera-kulkarni",
    headline: "Brand designer · Former design lead at a Bengaluru studio",
    students: 51870,
    courses: 3,
    rating: 4.6,
  },
  arjun: {
    name: "Arjun Menon",
    slug: "arjun-menon",
    headline: "Data scientist · Python and analytics educator",
    students: 128400,
    courses: 5,
    rating: 4.7,
  },
};

const photo = (id: string, alt: string, credit: string, handle: string): Photo => ({
  src: u(id, 800),
  alt,
  credit,
  creditUrl: `https://unsplash.com/@${handle}`,
});

const fullStackCurriculum: Section[] = [
  {
    title: "Getting oriented",
    lectures: [
      { title: "How this course is organised", seconds: 312, preview: true, kind: "video" },
      { title: "Setting up Node, VS Code and Git", seconds: 905, preview: true, kind: "video" },
      { title: "The project we will build", seconds: 428, kind: "video" },
      { title: "Course resources and source code", seconds: 120, kind: "article" },
    ],
  },
  {
    title: "Modern JavaScript, properly",
    lectures: [
      { title: "Values, references and immutability", seconds: 1104, kind: "video" },
      { title: "Async/await without the confusion", seconds: 1322, kind: "video" },
      { title: "Modules and tooling", seconds: 846, kind: "video" },
      { title: "Checkpoint quiz", seconds: 300, kind: "quiz" },
    ],
  },
  {
    title: "React from first principles",
    lectures: [
      { title: "Thinking in components", seconds: 980, kind: "video" },
      { title: "State, props and data flow", seconds: 1415, kind: "video" },
      { title: "Effects and when not to use them", seconds: 1210, kind: "video" },
      { title: "Forms with validation", seconds: 1530, kind: "video" },
      { title: "Checkpoint quiz", seconds: 300, kind: "quiz" },
    ],
  },
  {
    title: "Building the API",
    lectures: [
      { title: "Designing REST endpoints", seconds: 1290, kind: "video" },
      { title: "Authentication and sessions", seconds: 1705, kind: "video" },
      { title: "PostgreSQL schema and migrations", seconds: 1620, kind: "video" },
    ],
  },
  {
    title: "Shipping to production",
    lectures: [
      { title: "Deploying to a VPS with Nginx", seconds: 1840, kind: "video" },
      { title: "Monitoring and backups", seconds: 960, kind: "video" },
      { title: "Where to go next", seconds: 410, kind: "video" },
    ],
  },
];

export const courses: Course[] = [
  {
    slug: "complete-full-stack-web-development",
    title: "The Complete Full-Stack Web Developer Programme",
    subtitle:
      "JavaScript, React, Node and PostgreSQL — build and deploy a production-grade application from an empty folder.",
    category: "Development",
    level: "All levels",
    language: "English",
    instructor: instructors.ananya!,
    rating: 4.7,
    ratingCount: 18342,
    students: 96410,
    seconds: 61 * 3600 + 1800,
    lectures: 412,
    pricePaise: 49900,
    listPricePaise: 349900,
    badge: "Bestseller",
    updated: "September 2026",
    image: photo("photo-1619410283995-43d9134e7656", "Code editor showing React source", "Juanjo Jaramillo", "juanjodev02"),
    outcomes: [
      "Build a complete web application with React on the front end and a REST API behind it",
      "Model data in PostgreSQL with migrations, indexes and constraints",
      "Implement secure authentication with sessions and CSRF protection",
      "Deploy to a Linux VPS behind Nginx with HTTPS",
      "Write tests that catch real bugs, not just raise coverage numbers",
      "Read and reason about unfamiliar codebases with confidence",
    ],
    requirements: [
      "A computer with internet access — Windows, macOS or Linux",
      "No prior programming experience needed; we start from the basics",
    ],
    curriculum: fullStackCurriculum,
  },
  {
    slug: "financial-accounting-for-managers",
    title: "Financial Accounting & Analysis for Managers",
    subtitle: "Read balance sheets, P&L and cash flow statements the way analysts and CFOs do.",
    category: "Finance & Accounting",
    level: "Beginner",
    language: "English",
    instructor: instructors.vikram!,
    rating: 4.8,
    ratingCount: 7420,
    students: 38120,
    seconds: 14 * 3600,
    lectures: 96,
    pricePaise: 44900,
    listPricePaise: 249900,
    badge: "Highest rated",
    updated: "August 2026",
    image: photo("photo-1611974789855-9c2a0a7236a3", "Stock market chart on a dark screen", "Maxim Hopman", "nampoh"),
    outcomes: [],
    requirements: [],
    curriculum: [],
  },
  {
    slug: "brand-identity-design",
    title: "Brand Identity Design: From Brief to Guidelines",
    subtitle: "Logo systems, typography and colour — the complete process studios use for real clients.",
    category: "Design",
    level: "Intermediate",
    language: "English",
    instructor: instructors.meera!,
    rating: 4.6,
    ratingCount: 3110,
    students: 14980,
    seconds: 9 * 3600 + 1800,
    lectures: 64,
    pricePaise: 39900,
    listPricePaise: 199900,
    updated: "October 2026",
    image: photo("photo-1716471330463-f475b00f0506", "Designer's desk with colour swatches", "Andy Brown", "basallt"),
    outcomes: [],
    requirements: [],
    curriculum: [],
  },
  {
    slug: "python-for-data-analysis",
    title: "Python for Data Analysis with Pandas",
    subtitle: "Clean, analyse and visualise real Indian datasets — from CSV to insight.",
    category: "Data Science",
    level: "Beginner",
    language: "Hindi",
    instructor: instructors.arjun!,
    rating: 4.7,
    ratingCount: 9230,
    students: 51200,
    seconds: 22 * 3600,
    lectures: 148,
    pricePaise: 44900,
    listPricePaise: 299900,
    badge: "Bestseller",
    updated: "September 2026",
    image: photo("photo-1542831371-29b0f74f9713", "Lines of code on a monitor", "Florian Olivo", "florianolv"),
    outcomes: [],
    requirements: [],
    curriculum: [],
  },
  {
    slug: "gst-and-taxation-practical",
    title: "GST in Practice: Returns, Input Credit and Compliance",
    subtitle: "A hands-on walkthrough of GST filings for small businesses and practitioners.",
    category: "Finance & Accounting",
    level: "Intermediate",
    language: "English",
    instructor: instructors.vikram!,
    rating: 4.7,
    ratingCount: 2210,
    students: 11730,
    seconds: 8 * 3600,
    lectures: 52,
    pricePaise: 39900,
    listPricePaise: 179900,
    badge: "New",
    updated: "October 2026",
    image: photo("photo-1711097383282-28097ae16b1d", "Person reviewing financial documents at a laptop", "Jakub Żerdzicki", "jakubzerdzicki"),
    outcomes: [],
    requirements: [],
    curriculum: [],
  },
  {
    slug: "ui-design-in-figma",
    title: "UI Design in Figma: Interfaces That Ship",
    subtitle: "Design systems, auto layout and developer hand-off for product teams.",
    category: "Design",
    level: "All levels",
    language: "English",
    instructor: instructors.meera!,
    rating: 4.5,
    ratingCount: 1840,
    students: 9050,
    seconds: 11 * 3600,
    lectures: 77,
    pricePaise: 39900,
    listPricePaise: 199900,
    updated: "July 2026",
    image: photo("photo-1626785774573-4b799315345d", "Design software on a desktop and tablet", "Emily Bernal", "emilybernal"),
    outcomes: [],
    requirements: [],
    curriculum: [],
  },
  {
    slug: "javascript-interview-preparation",
    title: "JavaScript Interview Preparation: 150 Real Questions",
    subtitle: "Closures, the event loop and system design rounds — explained, then practised.",
    category: "Development",
    level: "Intermediate",
    language: "English",
    instructor: instructors.ananya!,
    rating: 4.8,
    ratingCount: 5630,
    students: 30440,
    seconds: 16 * 3600,
    lectures: 160,
    pricePaise: 44900,
    listPricePaise: 249900,
    updated: "September 2026",
    image: photo("photo-1461749280684-dccba630e2f6", "Monitor showing source code", "Ilya Pavlov", "ilyapavlov"),
    outcomes: [],
    requirements: [],
    curriculum: [],
  },
  {
    slug: "stock-market-fundamentals",
    title: "Stock Market Fundamentals for Indian Investors",
    subtitle: "How NSE and BSE work, reading charts, and building a long-term portfolio responsibly.",
    category: "Finance & Accounting",
    level: "Beginner",
    language: "Hindi",
    instructor: instructors.vikram!,
    rating: 4.6,
    ratingCount: 4120,
    students: 27650,
    seconds: 10 * 3600,
    lectures: 70,
    pricePaise: 39900,
    listPricePaise: 199900,
    updated: "June 2026",
    image: photo("photo-1560221328-12fe60f83ab8", "Monitor displaying stock charts", "Nicholas Cappello", "bash__profile"),
    outcomes: [],
    requirements: [],
    curriculum: [],
  },
];

export const categories = [
  "Development",
  "Business",
  "Finance & Accounting",
  "IT & Software",
  "Design",
  "Data Science",
  "Marketing",
  "Personal Development",
  "Teaching & Academics",
];

export function getCourse(slug: string): Course | undefined {
  return courses.find((c) => c.slug === slug);
}
