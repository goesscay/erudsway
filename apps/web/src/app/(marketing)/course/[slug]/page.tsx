import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import {
  AlarmClock,
  Award,
  Check,
  ChevronRight,
  FileText,
  Globe,
  Infinity as InfinityIcon,
  MonitorPlay,
  PlayCircle,
  ShieldCheck,
  Smartphone,
} from "lucide-react";
import { CourseBadge } from "@/components/course/course-badge";
import { CoursePreview, PreviewTrigger } from "@/components/course/course-preview";
import { CurriculumList } from "@/components/course/curriculum-list";
import { EditionPurchase } from "@/components/course/edition-purchase";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { Price } from "@/components/course/price";
import { PurchaseActions } from "@/components/course/purchase-actions";
import { Rating } from "@/components/course/rating";
import { CourseReviews } from "@/components/engagement/course-reviews";
import { Skeleton } from "@/components/ui/skeleton";
import { getCourse, searchCourses } from "@/lib/catalog/queries";
import { levelLabel, type CourseDetail } from "@/lib/catalog/types";
import { formatCount, formatDuration } from "@/lib/format";

type Params = Promise<{ slug: string }>;

/** Placeholder param for builds with no reachable catalogue; never fetched. */
const NO_COURSES = "__no-courses-yet";

/**
 * Prerender every live course at build time, so the page arrives complete — title,
 * curriculum and price in the first HTML, nothing swapped in later (fast LCP, no layout
 * shift). Courses published after the build are rendered on first visit and cached.
 * If the API isn't reachable during the build (first deploy, empty catalogue), a single
 * placeholder is returned — Cache Components requires at least one — which renders as 404.
 */
export async function generateStaticParams(): Promise<{ slug: string }[]> {
  const slugs: { slug: string }[] = [];
  for (let page = 1; page <= 20; page++) {
    const result = await searchCourses({ sort: "newest", per_page: "48", page: String(page) }).catch(() => null);
    if (!result) break;
    slugs.push(...result.data.map((c) => ({ slug: c.slug })));
    if (page >= result.meta.last_page) break;
  }
  return slugs.length > 0 ? slugs : [{ slug: NO_COURSES }];
}

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slug } = await params;
  const course = slug === NO_COURSES ? null : await getCourse(slug).catch(() => null);
  if (!course) return { title: "Course not found" };

  return {
    title: course.title,
    description: course.subtitle ?? undefined,
    alternates: { canonical: `/course/${course.slug}` },
    openGraph: {
      title: course.title,
      description: course.subtitle ?? undefined,
      type: "website",
      images: course.thumbnail_url ? [{ url: course.thumbnail_url }] : undefined,
    },
  };
}

export default function CoursePage({ params }: { params: Params }) {
  return (
    <Suspense fallback={<CourseSkeleton />}>
      <CourseView params={params} />
    </Suspense>
  );
}

function CourseSkeleton() {
  return (
    <section className="dark bg-navy-950">
      <div className="mx-auto max-w-[1340px] space-y-4 px-6 py-12">
        <Skeleton className="h-4 w-40" />
        <Skeleton className="h-10 w-2/3" />
        <Skeleton className="h-5 w-1/2" />
      </div>
    </section>
  );
}

async function CourseView({ params }: { params: Params }) {
  const { slug } = await params;
  if (slug === NO_COURSES) notFound();
  const course = await getCourse(slug);
  if (!course) notFound();

  const lectures = course.curriculum.flatMap((s) => s.lectures);
  const articles = lectures.filter((l) => l.kind === "article").length;
  const quizzes = lectures.filter((l) => l.kind === "quiz").length;
  const previewVideos = lectures.filter((l) => l.is_free_preview && l.kind === "video");
  const previews = lectures.filter((l) => l.is_free_preview).length;
  // Editions with an access period: show it when they all share one.
  const periods = [...new Set(course.editions.map((e) => e.access_days))];
  const accessDays = course.editions.length > 0 && periods.length === 1 ? periods[0] : null;
  const updated = new Date(course.updated_at).toLocaleDateString("en-IN", { month: "long", year: "numeric" });

  return (
    <CoursePreview courseTitle={course.title} lectures={previewVideos}>
      <CourseJsonLd course={course} />

      {/* Header band */}
      <section className="dark bg-navy-950 text-foreground">
        <div className="mx-auto grid max-w-[1340px] gap-10 px-6 py-12 lg:grid-cols-[1fr_380px]">
          <div className="max-w-3xl">
            <nav
              aria-label="Breadcrumb"
              className="mb-5 flex flex-wrap items-center gap-1.5 text-sm font-semibold text-gold-400"
            >
              <Link href="/courses" className="hover:underline">
                Courses
              </Link>
              {course.breadcrumb.map((b) => (
                <span key={b.slug} className="flex items-center gap-1.5">
                  <ChevronRight className="size-3.5 text-white/40" />
                  <Link href={`/courses/${b.slug}`} className="hover:underline">
                    {b.name}
                  </Link>
                </span>
              ))}
            </nav>
            <h1 className="text-h1">{course.title}</h1>
            {course.subtitle && <p className="mt-4 text-lg leading-relaxed text-white/80">{course.subtitle}</p>}
            <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
              {course.badge && <CourseBadge badge={course.badge} />}
              {course.rating_count > 0 && (
                <>
                  <Rating value={course.rating} size="md" />
                  <a href="#reviews" className="text-gold-200 underline underline-offset-2">
                    {formatCount(course.rating_count)} ratings
                  </a>
                </>
              )}
              <span className="text-white/80">{formatCount(course.enrollment_count)} learners</span>
            </div>
            <p className="mt-4 text-sm text-white/80">
              Created by{" "}
              {course.instructor.slug ? (
                <Link
                  href={`/instructors/${course.instructor.slug}`}
                  className="font-semibold text-gold-200 underline underline-offset-2"
                >
                  {course.instructor.name}
                </Link>
              ) : (
                <span className="font-semibold text-gold-200">{course.instructor.name}</span>
              )}
            </p>
            <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-white/70">
              <span className="inline-flex items-center gap-1.5">
                <AlarmClock className="size-4" /> Last updated {updated}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <Globe className="size-4" /> {course.language}
              </span>
              <span>{levelLabel[course.level]}</span>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto grid max-w-[1340px] gap-10 px-6 lg:grid-cols-[1fr_380px]">
        <div className="max-w-3xl space-y-12 py-12">
          {course.outcomes.length > 0 && (
            <section className="rounded-md border border-line bg-white p-7">
              <h2 className="text-h3">What you&rsquo;ll learn</h2>
              <ul className="mt-5 grid gap-x-8 gap-y-3 sm:grid-cols-2">
                {course.outcomes.map((o) => (
                  <li key={o} className="flex gap-3 text-sm leading-relaxed text-stone-700">
                    <Check className="mt-0.5 size-4 shrink-0 text-gold-600" strokeWidth={2.5} />
                    {o}
                  </li>
                ))}
              </ul>
            </section>
          )}

          <section>
            <h2 className="mb-4 text-h3">Course content</h2>
            <CurriculumList sections={course.curriculum} />
          </section>

          {course.requirements.length > 0 && (
            <section>
              <h2 className="mb-4 text-h3">Requirements</h2>
              <ul className="list-disc space-y-2 pl-5 text-sm text-stone-700 marker:text-gold-500">
                {course.requirements.map((r) => (
                  <li key={r}>{r}</li>
                ))}
              </ul>
            </section>
          )}

          {course.description && (
            <section>
              <h2 className="mb-4 text-h3">Description</h2>
              {/* Sanitised server-side (HTMLPurifier allowlist) when the instructor saves it. */}
              <div
                className="prose-content text-[0.95rem] leading-relaxed text-stone-700"
                dangerouslySetInnerHTML={{ __html: course.description }}
              />
            </section>
          )}

          {course.audience.length > 0 && (
            <section>
              <h2 className="mb-4 text-h3">Who this course is for</h2>
              <ul className="list-disc space-y-2 pl-5 text-sm text-stone-700 marker:text-gold-500">
                {course.audience.map((a) => (
                  <li key={a}>{a}</li>
                ))}
              </ul>
            </section>
          )}

          <section id="instructor">
            <h2 className="mb-5 text-h3">Your instructor</h2>
            <div className="flex gap-5">
              <InstructorAvatar name={course.instructor.name} className="size-20 text-2xl" />
              <div>
                {course.instructor.slug ? (
                  <Link
                    href={`/instructors/${course.instructor.slug}`}
                    className="text-lg font-bold text-navy-900 hover:underline"
                  >
                    {course.instructor.name}
                  </Link>
                ) : (
                  <p className="text-lg font-bold text-navy-900">{course.instructor.name}</p>
                )}
                {course.instructor.headline && <p className="text-sm text-stone-500">{course.instructor.headline}</p>}
                {course.instructor.bio && (
                  <p className="mt-3 text-sm leading-relaxed text-stone-700">{course.instructor.bio}</p>
                )}
              </div>
            </div>
          </section>

          <CourseReviews slug={course.slug} instructorName={course.instructor.name} />
        </div>

        {/* Purchase card */}
        <aside className="relative pb-12 lg:-mt-64 lg:pb-0">
          <div className="sticky top-28 overflow-hidden rounded-md bg-white shadow-[0_24px_60px_-28px_rgb(0_27_64/0.5)] ring-1 ring-line">
            <PreviewTrigger className="group relative block aspect-video w-full bg-paper-2 text-left">
              {course.thumbnail_url && (
                <Image src={course.thumbnail_url} alt="" fill sizes="380px" className="object-cover" preload />
              )}
              {previewVideos.length > 0 ? (
                <span className="absolute inset-0 flex flex-col items-center justify-end gap-3 bg-gradient-to-t from-navy-950/85 via-navy-950/20 to-transparent pb-4 text-white">
                  <span className="flex size-14 items-center justify-center rounded-full bg-white text-navy-900 shadow-lg transition-transform group-hover:scale-105">
                    <PlayCircle className="size-7" strokeWidth={1.5} />
                  </span>
                  <span className="text-sm font-bold">Preview this course</span>
                </span>
              ) : (
                previews > 0 && (
                  <span className="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-sm bg-navy-950/85 px-2.5 py-1 text-xs font-semibold text-white">
                    <PlayCircle className="size-3.5" /> {previews} free preview{" "}
                    {previews === 1 ? "lecture" : "lectures"}
                  </span>
                )
              )}
            </PreviewTrigger>
            <div className="space-y-4 p-6">
              {course.editions.length > 0 ? (
                <EditionPurchase slug={course.slug} editions={course.editions} />
              ) : (
                <>
                  <Price price={course.price} className="font-serif text-3xl font-semibold" />
                  <PurchaseActions slug={course.slug} free={!course.price} />
                </>
              )}
              {course.price && (
                <p className="flex items-center justify-center gap-1.5 text-center text-xs text-stone-500">
                  <ShieldCheck className="size-3.5" aria-hidden /> Secure payment · GST invoice ·{" "}
                  <Link href="/policies#refund" className="underline underline-offset-2">
                    Refund policy
                  </Link>
                </p>
              )}
              <div>
                <p className="mb-2 text-sm font-bold text-ink">This course includes</p>
                <ul className="space-y-2 text-sm text-stone-700">
                  <Includes icon={MonitorPlay}>{formatDuration(course.duration_seconds)} of on-demand content</Includes>
                  <Includes icon={PlayCircle}>{course.lecture_count} lectures</Includes>
                  {articles > 0 && (
                    <Includes icon={FileText}>
                      {articles === 1 ? "1 article" : `${articles} articles`} with downloadable resources
                    </Includes>
                  )}
                  {quizzes > 0 && (
                    <Includes icon={Check}>
                      {quizzes === 1 ? "1 practice quiz" : `${quizzes} practice quizzes`}
                    </Includes>
                  )}
                  <Includes icon={Smartphone}>Access on mobile and desktop</Includes>
                  {accessDays ? (
                    <Includes icon={InfinityIcon}>{accessDays} days of access</Includes>
                  ) : (
                    <Includes icon={InfinityIcon}>Full lifetime access</Includes>
                  )}
                  <Includes icon={Award}>Certificate of completion</Includes>
                </ul>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </CoursePreview>
  );
}

function Includes({ icon: Icon, children }: { icon: typeof Award; children: React.ReactNode }) {
  return (
    <li className="flex items-center gap-2.5">
      <Icon className="size-4 text-stone-500" strokeWidth={1.75} />
      {children}
    </li>
  );
}

/** schema.org Course markup for search engines. */
function CourseJsonLd({ course }: { course: CourseDetail }) {
  const data = {
    "@context": "https://schema.org",
    "@type": "Course",
    name: course.title,
    description: course.subtitle ?? course.title,
    inLanguage: course.language,
    provider: { "@type": "Organization", name: "Erudsway Educorp LLP", sameAs: process.env.NEXT_PUBLIC_SITE_URL },
    instructor: { "@type": "Person", name: course.instructor.name },
    ...(course.rating_count > 0 && {
      aggregateRating: { "@type": "AggregateRating", ratingValue: course.rating, ratingCount: course.rating_count },
    }),
    offers: {
      "@type": "Offer",
      category: course.price ? "Paid" : "Free",
      price: course.price ? (course.price.amount / 100).toFixed(2) : "0",
      priceCurrency: course.price?.currency ?? "INR",
    },
    hasCourseInstance: {
      "@type": "CourseInstance",
      courseMode: "Online",
      courseWorkload: `PT${Math.max(1, Math.round(course.duration_seconds / 3600))}H`,
    },
  };

  return (
    <script
      type="application/ld+json"
      // Escape "<" so course text can never close the script tag.
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }}
    />
  );
}
