import Image from "next/image";
import Link from "next/link";
import { ArrowRight, Award, IndianRupee, RotateCcw, Smartphone } from "lucide-react";
import { PhotoCredit } from "@/components/brand/photo-credit";
import { SectionHeading } from "@/components/brand/section-heading";
import { CourseCard } from "@/components/course/course-card";
import { buttonVariants } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { courses, photos } from "@/lib/demo-data";
import { cn } from "@/lib/utils";

const topics = [
  { name: "Web Development", count: 412 },
  { name: "Accounting & GST", count: 168 },
  { name: "Data Analysis", count: 236 },
  { name: "Graphic Design", count: 142 },
  { name: "Stock Market", count: 97 },
  { name: "Spoken English", count: 121 },
  { name: "Excel & Power BI", count: 189 },
  { name: "Digital Marketing", count: 154 },
];

export default function HomePage() {
  const popular = courses.slice(0, 4);
  const fresh = courses.slice(4, 8);

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
              Taught by people who do the work
            </p>
            <h1 className="max-w-[14ch] text-[2.6rem] leading-[1.05] tracking-[-0.02em] sm:text-display">Skills that move your career forward.</h1>
            <p className="mt-6 max-w-[46ch] text-lg leading-relaxed text-stone-700">
              Video courses in development, finance, design and data — from engineers, chartered accountants and
              designers practising across India. Learn at your pace, keep lifetime access.
            </p>
            <div className="mt-9 flex flex-wrap items-center gap-3">
              <Link href="/courses" className={buttonVariants({ size: "lg" })}>
                Explore courses
              </Link>
              <Link href="/teach" className={cn(buttonVariants({ size: "lg", variant: "outline" }))}>
                Teach on Erudsway
              </Link>
            </div>
            <dl className="mt-12 grid max-w-md grid-cols-3 divide-x divide-line border-y border-line py-4">
              {[
                ["1,500+", "courses"],
                ["2.4 lakh", "learners"],
                ["4.7", "average rating"],
              ].map(([n, l]) => (
                <div key={l} className="px-4 first:pl-0">
                  <dt className="sr-only">{l}</dt>
                  <dd className="tabular font-serif text-xl font-semibold whitespace-nowrap text-navy-900 sm:text-2xl">{n}</dd>
                  <dd className="text-xs text-stone-500">{l}</dd>
                </div>
              ))}
            </dl>
          </div>

          <div className="relative">
            <div className="relative aspect-[4/3.4] overflow-hidden rounded-md">
              <Image
                src={photos.hero.src}
                alt={photos.hero.alt}
                fill
                priority
                sizes="(min-width: 1024px) 620px, 100vw"
                className="object-cover"
              />
            </div>
            {/* Product glimpse: what "continue learning" looks like */}
            <div className="absolute -bottom-6 left-4 w-[290px] rounded-md bg-white p-4 shadow-[0_18px_40px_-18px_rgb(0_27_64/0.45)] ring-1 ring-line sm:-left-8">
              <p className="text-[0.7rem] font-semibold tracking-wide text-stone-500 uppercase">Continue learning</p>
              <p className="mt-1 line-clamp-1 text-sm font-bold text-ink">React from first principles</p>
              <p className="text-xs text-stone-500">Lecture 14 · State, props and data flow</p>
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
          {[
            [IndianRupee, "Pay in rupees — UPI, cards, netbanking"],
            [RotateCcw, "30-day refund on every course"],
            [Award, "Certificate of completion"],
            [Smartphone, "Learn on desktop or phone"],
          ].map(([Icon, text]) => {
            const I = Icon as typeof Award;
            return (
              <li key={text as string} className="flex items-center gap-3">
                <I className="size-[18px] text-gold-600" strokeWidth={1.75} />
                {text as string}
              </li>
            );
          })}
        </ul>
      </section>

      {/* Popular */}
      <section className="mx-auto max-w-[1340px] px-6 py-16">
        <div className="flex flex-wrap items-end justify-between gap-6">
          <SectionHeading eyebrow="Most enrolled" title="Courses learners finish — and recommend">
            Ranked by completion and reviews, not just sales.
          </SectionHeading>
          <Link href="/courses" className="group inline-flex items-center gap-1.5 text-sm font-semibold text-navy-900">
            View all courses
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
          </Link>
        </div>
        <div className="mt-10 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
          {popular.map((c) => (
            <CourseCard key={c.slug} course={c} />
          ))}
        </div>
      </section>

      {/* Topics */}
      <section className="border-y border-line bg-paper-2/60">
        <div className="mx-auto grid max-w-[1340px] gap-10 px-6 py-16 lg:grid-cols-[0.8fr_1.2fr]">
          <SectionHeading eyebrow="Browse by topic" title="Where would you like to begin?">
            From your first line of code to filing GST returns, every topic is curated and reviewed by our team
            before it goes live.
          </SectionHeading>
          <ul className="grid grid-cols-1 border-t border-line sm:grid-cols-2">
            {topics.map((t) => (
              <li key={t.name} className="border-b border-line sm:odd:border-r sm:odd:pr-6 sm:even:pl-6">
                <Link
                  href={`/courses?q=${encodeURIComponent(t.name)}`}
                  className="group flex items-baseline justify-between py-4"
                >
                  <span className="font-serif text-xl font-medium text-navy-900 group-hover:underline group-hover:decoration-gold-500 group-hover:underline-offset-4">
                    {t.name}
                  </span>
                  <span className="tabular text-xs text-stone-500">{t.count} courses</span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* New */}
      <section className="mx-auto max-w-[1340px] px-6 py-16">
        <SectionHeading eyebrow="Fresh this month" title="New and recently updated" />
        <div className="mt-10 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
          {fresh.map((c) => (
            <CourseCard key={c.slug} course={c} />
          ))}
        </div>
      </section>

      {/* Teach */}
      <section className="dark bg-navy-900 text-foreground">
        <div className="mx-auto grid max-w-[1340px] items-center gap-12 px-6 py-20 lg:grid-cols-2">
          <div className="relative aspect-[4/3] overflow-hidden rounded-md">
            <Image src={photos.teach.src} alt={photos.teach.alt} fill sizes="(min-width: 1024px) 620px, 100vw" className="object-cover" />
          </div>
          <div>
            <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">For instructors</p>
            <h2 className="text-h1 max-w-[16ch]">Teach what you know. We handle the rest.</h2>
            <p className="mt-5 max-w-[50ch] leading-relaxed text-white/75">
              Publish video courses to learners across India. We take care of payments, hosting, GST invoicing and
              support — you focus on teaching and earn on every enrolment.
            </p>
            <ol className="mt-8 space-y-4">
              {[
                ["Apply", "Tell us about your expertise. Our team reviews every application."],
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
