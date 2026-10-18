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
  FileDown,
  Globe,
  Infinity as InfinityIcon,
  MonitorPlay,
  Play,
  RotateCcw,
  Smartphone,
  Users,
} from "lucide-react";
import { PhotoCredit } from "@/components/brand/photo-credit";
import { CourseBadge } from "@/components/course/course-badge";
import { CurriculumList } from "@/components/course/curriculum-list";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { Rating } from "@/components/course/rating";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { courses, getCourse } from "@/lib/demo-data";
import { formatCount, formatDuration, formatPaise } from "@/lib/format";

type Params = Promise<{ slug: string }>;

export function generateStaticParams() {
  return courses.map((c) => ({ slug: c.slug }));
}

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slug } = await params;
  const course = getCourse(slug);
  return course ? { title: course.title, description: course.subtitle } : {};
}

const reviewSplit = [74, 19, 5, 1, 1];

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
  const course = getCourse(slug);
  if (!course) notFound();

  // Phase 0 mock: courses without their own syllabus borrow the flagship one.
  const flagship = courses[0]!;
  const curriculum = course.curriculum.length ? course.curriculum : flagship.curriculum;
  const outcomes = course.outcomes.length ? course.outcomes : flagship.outcomes;
  const requirements = course.requirements.length ? course.requirements : flagship.requirements;
  const discount = Math.round((1 - course.pricePaise / course.listPricePaise) * 100);

  return (
    <>
      {/* Header band */}
      <section className="dark bg-navy-950 text-foreground">
        <div className="mx-auto grid max-w-[1340px] gap-10 px-6 py-12 lg:grid-cols-[1fr_380px]">
          <div className="max-w-3xl">
            <nav aria-label="Breadcrumb" className="mb-5 flex items-center gap-1.5 text-sm font-semibold text-gold-400">
              <Link href="/courses" className="hover:underline">
                Courses
              </Link>
              <ChevronRight className="size-3.5 text-white/40" />
              <Link href="/courses" className="hover:underline">
                {course.category}
              </Link>
            </nav>
            <h1 className="text-h1">{course.title}</h1>
            <p className="mt-4 text-lg leading-relaxed text-white/80">{course.subtitle}</p>
            <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
              {course.badge && <CourseBadge badge={course.badge} />}
              <Rating value={course.rating} size="md" />
              <a href="#reviews" className="text-gold-200 underline underline-offset-2">
                {formatCount(course.ratingCount)} ratings
              </a>
              <span className="text-white/80">{formatCount(course.students)} learners</span>
            </div>
            <p className="mt-4 text-sm text-white/80">
              Created by{" "}
              <a href="#instructor" className="font-semibold text-gold-200 underline underline-offset-2">
                {course.instructor.name}
              </a>
            </p>
            <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-white/70">
              <span className="inline-flex items-center gap-1.5">
                <AlarmClock className="size-4" /> Last updated {course.updated}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <Globe className="size-4" /> {course.language}
              </span>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto grid max-w-[1340px] gap-10 px-6 lg:grid-cols-[1fr_380px]">
        {/* Main column */}
        <div className="max-w-3xl space-y-12 py-12">
          <section className="rounded-md border border-line bg-white p-7">
            <h2 className="text-h3">What you&rsquo;ll learn</h2>
            <ul className="mt-5 grid gap-x-8 gap-y-3 sm:grid-cols-2">
              {outcomes.map((o) => (
                <li key={o} className="flex gap-3 text-sm leading-relaxed text-stone-700">
                  <Check className="mt-0.5 size-4 shrink-0 text-gold-600" strokeWidth={2.5} />
                  {o}
                </li>
              ))}
            </ul>
          </section>

          <section>
            <h2 className="text-h3 mb-4">Course content</h2>
            <CurriculumList sections={curriculum} />
          </section>

          <section>
            <h2 className="text-h3 mb-4">Requirements</h2>
            <ul className="list-disc space-y-2 pl-5 text-sm text-stone-700 marker:text-gold-500">
              {requirements.map((r) => (
                <li key={r}>{r}</li>
              ))}
            </ul>
          </section>

          <section id="instructor">
            <h2 className="text-h3 mb-5">Your instructor</h2>
            <div className="flex gap-5">
              <InstructorAvatar name={course.instructor.name} className="size-20 text-2xl" />
              <div>
                <p className="text-lg font-bold text-navy-900">{course.instructor.name}</p>
                <p className="text-sm text-stone-500">{course.instructor.headline}</p>
                <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-stone-700">
                  <li>
                    <Rating value={course.instructor.rating} />
                  </li>
                  <li className="inline-flex items-center gap-1.5">
                    <Users className="size-4 text-stone-500" /> {formatCount(course.instructor.students)} learners
                  </li>
                  <li className="inline-flex items-center gap-1.5">
                    <MonitorPlay className="size-4 text-stone-500" /> {course.instructor.courses} courses
                  </li>
                </ul>
              </div>
            </div>
          </section>

          <section id="reviews">
            <h2 className="text-h3 mb-5">Learner feedback</h2>
            <div className="grid items-center gap-8 sm:grid-cols-[auto_1fr]">
              <div className="text-center">
                <p className="tabular font-serif text-6xl font-semibold text-gold-600">{course.rating.toFixed(1)}</p>
                <Rating value={course.rating} className="justify-center [&>span:first-child]:hidden" />
                <p className="mt-1 text-xs font-semibold text-gold-600">Course rating</p>
              </div>
              <ul className="space-y-2">
                {reviewSplit.map((pct, i) => (
                  <li key={i} className="flex items-center gap-3 text-sm">
                    <span className="h-2 flex-1 overflow-hidden rounded-full bg-paper-2">
                      <span className="block h-full bg-stone-500/70" style={{ width: `${pct}%` }} />
                    </span>
                    <span className="tabular w-14 text-stone-700">{5 - i} stars</span>
                    <span className="tabular w-10 text-right text-stone-500">{pct}%</span>
                  </li>
                ))}
              </ul>
            </div>
          </section>
        </div>

        {/* Purchase card */}
        <aside className="relative lg:-mt-64">
          <div className="sticky top-28 overflow-hidden rounded-md bg-white shadow-[0_24px_60px_-28px_rgb(0_27_64/0.5)] ring-1 ring-line">
            <button type="button" className="group relative block aspect-video w-full" aria-label="Preview this course">
              <Image src={course.image.src} alt={course.image.alt} fill sizes="380px" className="object-cover" priority />
              <span className="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-navy-950/20 to-transparent" />
              <span className="absolute top-1/2 left-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow-lg transition-transform group-hover:scale-105">
                <Play className="ml-1 size-6 fill-navy-900 text-navy-900" />
              </span>
              <span className="absolute inset-x-0 bottom-4 text-center text-sm font-bold text-white">
                Preview this course
              </span>
            </button>
            <div className="space-y-4 p-6">
              <div className="flex items-baseline gap-3">
                <span className="tabular font-serif text-3xl font-semibold text-ink">{formatPaise(course.pricePaise)}</span>
                <span className="tabular text-stone-500 line-through">{formatPaise(course.listPricePaise)}</span>
                <span className="text-sm font-semibold text-success">{discount}% off</span>
              </div>
              <div className="space-y-2.5">
                <Button size="lg" className="w-full">
                  Add to cart
                </Button>
                <Button size="lg" variant="outline" className="w-full">
                  Buy now
                </Button>
              </div>
              <p className="flex items-center justify-center gap-1.5 text-center text-xs text-stone-500">
                <RotateCcw className="size-3.5" /> 30-day money-back guarantee
              </p>
              <div>
                <p className="mb-2 text-sm font-bold text-ink">This course includes</p>
                <ul className="space-y-2 text-sm text-stone-700">
                  {[
                    [MonitorPlay, `${formatDuration(course.seconds)} of on-demand video`],
                    [FileDown, "38 downloadable resources"],
                    [Smartphone, "Access on mobile and desktop"],
                    [InfinityIcon, "Full lifetime access"],
                    [Award, "Certificate of completion"],
                  ].map(([I, t]) => {
                    const Icon = I as typeof Award;
                    return (
                      <li key={t as string} className="flex items-center gap-2.5">
                        <Icon className="size-4 text-stone-500" strokeWidth={1.75} />
                        {t as string}
                      </li>
                    );
                  })}
                </ul>
              </div>
              <div className="flex justify-between border-t border-line pt-4 text-sm font-semibold text-navy-900">
                <button type="button" className="underline-offset-2 hover:underline">
                  Share
                </button>
                <button type="button" className="underline-offset-2 hover:underline">
                  Apply coupon
                </button>
              </div>
            </div>
          </div>
          <PhotoCredit photo={course.image} className="mt-3 text-center" />
        </aside>
      </div>
    </>
  );
}
