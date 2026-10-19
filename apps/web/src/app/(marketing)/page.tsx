import Image from "next/image";
import Link from "next/link";
import { connection } from "next/server";
import { Suspense } from "react";
import { ArrowRight, Award, FileCheck2, IndianRupee, RotateCcw } from "lucide-react";
import { PhotoCredit } from "@/components/brand/photo-credit";
import { SectionHeading } from "@/components/brand/section-heading";
import { CourseCard } from "@/components/course/course-card";
import { buttonVariants } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import { getCategories, searchCourses } from "@/lib/catalog/queries";
import { photos } from "@/lib/demo-data";
import { cn } from "@/lib/utils";

export default function HomePage() {
  return (
    <>
      {/* Hero */}
      <section className="relative overflow-hidden border-b border-line bg-paper">
        <svg
          aria-hidden
          viewBox="0 0 600 600"
          className="pointer-events-none absolute -top-40 -left-40 h-[620px] w-[620px] text-gold-200"
        >
          <path
            d="M560 40C380 40 300 140 360 230s-40 170-170 170S20 470 40 560"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.25"
          />
        </svg>

        <div className="relative mx-auto grid max-w-[1340px] items-center gap-12 px-6 py-16 lg:grid-cols-[1.05fr_1fr] lg:py-24">
          <div>
            <p className="mb-5 text-xs font-bold tracking-[0.2em] text-gold-600 uppercase">
              Taught by practising CAs, CSs and advocates
            </p>
            <h1 className="max-w-[15ch] text-[2.6rem] leading-[1.05] tracking-[-0.02em] sm:text-display">
              Tax and compliance, learned from practice.
            </h1>
            <p className="mt-6 max-w-[48ch] text-lg leading-relaxed text-stone-700">
              GST, income tax, TDS, audit, company law, FEMA and more — explained with real cases, portal walkthroughs and
              working files by professionals who file, audit and argue every week.
            </p>
            <div className="mt-9 flex flex-wrap items-center gap-3">
              <Link href="/courses" className={buttonVariants({ size: "lg" })}>
                Explore courses
              </Link>
              <Link href="/teach" className={cn(buttonVariants({ size: "lg", variant: "outline" }))}>
                Teach on Erudsway
              </Link>
            </div>
            <Suspense fallback={<Skeleton className="mt-12 h-[74px] max-w-md" />}>
              <HeroStats />
            </Suspense>
          </div>

          <div className="relative">
            <div className="relative aspect-[4/3.4] overflow-hidden rounded-md">
              <Image
                src={photos.hero.src}
                alt={photos.hero.alt}
                fill
                preload
                sizes="(min-width: 1024px) 620px, 100vw"
                className="object-cover"
              />
            </div>
            <div className="absolute -bottom-6 left-4 w-[290px] rounded-md bg-white p-4 shadow-[0_18px_40px_-18px_rgb(0_27_64/0.45)] ring-1 ring-line sm:-left-8">
              <p className="text-[0.7rem] font-semibold tracking-wide text-stone-500 uppercase">Continue learning</p>
              <p className="mt-1 line-clamp-1 text-sm font-bold text-ink">GST Practitioner Course</p>
              <p className="text-xs text-stone-500">Lecture 14 · Filing GSTR-3B</p>
              <Progress value={62} className="mt-3 h-1.5" aria-label="62% complete" />
              <p className="tabular mt-1.5 text-right text-[0.7rem] text-stone-500">62% complete</p>
            </div>
            <PhotoCredit photo={photos.hero} className="mt-9 text-right" />
          </div>
        </div>
      </section>

      {/* Assurance strip */}
      <section className="border-b border-line bg-white">
        <ul className="mx-auto grid max-w-[1340px] gap-y-4 px-6 py-5 text-sm text-stone-700 sm:grid-cols-2 lg:grid-cols-4">
          {(
            [
              [IndianRupee, "Pay in rupees — UPI, cards, netbanking"],
              [FileCheck2, "GST invoice with every purchase"],
              [RotateCcw, "30-day refund on every course"],
              [Award, "Certificate of completion"],
            ] as const
          ).map(([Icon, text]) => (
            <li key={text} className="flex items-center gap-3">
              <Icon className="size-[18px] text-gold-600" strokeWidth={1.75} />
              {text}
            </li>
          ))}
        </ul>
      </section>

      {/* Popular */}
      <section className="mx-auto max-w-[1340px] px-6 py-16">
        <div className="flex flex-wrap items-end justify-between gap-6">
          <SectionHeading eyebrow="Most enrolled" title="Courses professionals finish — and recommend">
            Ranked by enrolments and reviews.
          </SectionHeading>
          <Link href="/courses?sort=popular" className="group inline-flex items-center gap-1.5 text-sm font-semibold text-navy-900">
            View all courses
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
          </Link>
        </div>
        <Suspense fallback={<CardGridSkeleton />}>
          <CourseGrid sort="popular" />
        </Suspense>
      </section>

      {/* Topics */}
      <section className="border-y border-line bg-paper-2/60">
        <div className="mx-auto max-w-[1340px] px-6 py-16">
          <SectionHeading eyebrow="Browse by area" title="Where would you like to begin?">
            Ten practice areas, every course reviewed by our team before it goes live.
          </SectionHeading>
          <Suspense fallback={<Skeleton className="mt-10 h-80 w-full" />}>
            <CategoryGrid />
          </Suspense>
        </div>
      </section>

      {/* New */}
      <section className="mx-auto max-w-[1340px] px-6 py-16">
        <SectionHeading eyebrow="Fresh this month" title="New and recently updated" />
        <Suspense fallback={<CardGridSkeleton />}>
          <CourseGrid sort="newest" />
        </Suspense>
      </section>

      {/* Teach */}
      <section className="dark bg-navy-900 text-foreground">
        <div className="mx-auto grid max-w-[1340px] items-center gap-12 px-6 py-20 lg:grid-cols-2">
          <div className="relative aspect-[4/3] overflow-hidden rounded-md">
            <Image src={photos.teach.src} alt={photos.teach.alt} fill sizes="(min-width: 1024px) 620px, 100vw" className="object-cover" />
          </div>
          <div>
            <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">For practitioners</p>
            <h2 className="text-h1 max-w-[16ch]">Teach what you practise. We handle the rest.</h2>
            <p className="mt-5 max-w-[50ch] leading-relaxed text-white/75">
              Chartered accountants, company secretaries, cost accountants and advocates publish courses on Erudsway. We take
              care of payments, GST invoicing, hosting and learner support — you focus on teaching and earn on every enrolment.
            </p>
            <ol className="mt-8 space-y-4">
              {[
                ["Apply", "Tell us about your practice. Our team reviews every application."],
                ["Build", "Plan your curriculum and upload lessons in our course builder."],
                ["Earn", "Get monthly payouts with a clear statement of every sale."],
              ].map(([t, d], i) => (
                <li key={t} className="flex gap-4">
                  <span className="tabular mt-0.5 font-serif text-lg text-gold-400">0{i + 1}</span>
                  <span>
                    <span className="block font-semibold text-white">{t}</span>
                    <span className="text-sm text-white/65">{d}</span>
                  </span>
                </li>
              ))}
            </ol>
            <Link href="/teach" className={cn(buttonVariants({ size: "lg" }), "mt-10")}>
              Start teaching today
            </Link>
            <PhotoCredit photo={photos.teach} className="mt-6 text-white/40" />
          </div>
        </div>
      </section>
    </>
  );
}

async function HeroStats() {
  await connection();
  const [all, categories] = await Promise.all([
    searchCourses({ per_page: "1" }).catch(() => null),
    getCategories().catch(() => []),
  ]);
  if (!all) return null;

  const languages = all.meta.facets.languages.length;
  const stats: [string, string][] = [
    [String(all.meta.total), "courses"],
    [String(categories.length), "practice areas"],
    [String(languages), languages === 1 ? "language" : "languages"],
  ];

  return (
    <dl className="mt-12 grid max-w-md grid-cols-3 divide-x divide-line border-y border-line py-4">
      {stats.map(([n, l]) => (
        <div key={l} className="px-4 first:pl-0">
          <dt className="sr-only">{l}</dt>
          <dd className="tabular font-serif text-xl font-semibold whitespace-nowrap text-navy-900 sm:text-2xl">{n}</dd>
          <dd className="text-xs text-stone-500">{l}</dd>
        </div>
      ))}
    </dl>
  );
}

async function CourseGrid({ sort }: { sort: "popular" | "newest" }) {
  await connection();
  const result = await searchCourses({ sort, per_page: "4" });
  const courses = result?.data ?? [];

  if (courses.length === 0) {
    return <p className="mt-10 text-sm text-stone-500">Courses will appear here as soon as they are published.</p>;
  }

  return (
    <div className="mt-10 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
      {courses.map((c) => (
        <CourseCard key={c.id} course={c} />
      ))}
    </div>
  );
}

async function CategoryGrid() {
  await connection();
  const categories = await getCategories();

  return (
    <ul className="mt-10 grid gap-px overflow-hidden rounded-md border border-line bg-line sm:grid-cols-2 lg:grid-cols-5">
      {categories.map((c) => (
        <li key={c.slug} className="bg-paper">
          <Link href={`/courses/${c.slug}`} className="group flex h-full flex-col p-5 hover:bg-white">
            <span className="font-serif text-xl font-medium text-navy-900 group-hover:underline group-hover:decoration-gold-500 group-hover:underline-offset-4">
              {c.name}
            </span>
            {c.description && <span className="mt-2 text-sm leading-relaxed text-stone-700">{c.description}</span>}
            {c.children && c.children.length > 0 && (
              <span className="mt-auto pt-4 text-xs text-stone-500">{c.children.map((s) => s.name).join(" · ")}</span>
            )}
          </Link>
        </li>
      ))}
    </ul>
  );
}

function CardGridSkeleton() {
  return (
    <div className="mt-10 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="space-y-3">
          <Skeleton className="aspect-[16/9] w-full" />
          <Skeleton className="h-4 w-4/5" />
          <Skeleton className="h-3 w-2/5" />
        </div>
      ))}
    </div>
  );
}
