import Image from "next/image";
import Link from "next/link";
import { levelLabel, type CourseCard as CourseCardData } from "@/lib/catalog/types";
import { formatDuration, plural } from "@/lib/format";
import { cn } from "@/lib/utils";
import { CourseBadge } from "./course-badge";
import { Price } from "./price";
import { Rating } from "./rating";

function Thumb({ course, sizes, className }: { course: CourseCardData; sizes: string; className?: string }) {
  return (
    <div className={cn("relative aspect-[16/9] overflow-hidden rounded-sm bg-paper-2 ring-1 ring-line", className)}>
      {course.thumbnail_url && (
        <Image
          src={course.thumbnail_url}
          alt=""
          fill
          sizes={sizes}
          className="object-cover transition-[filter,transform] duration-300 ease-out group-hover:scale-[1.02] group-hover:brightness-90"
        />
      )}
    </div>
  );
}

function Meta({ course }: { course: CourseCardData }) {
  return (
    <p className="text-xs text-muted-foreground">
      {formatDuration(course.duration_seconds)} total · {plural(course.lecture_count, "lecture")} · {levelLabel[course.level] ?? course.level}
      {course.language !== "English" && <> · {course.language}</>}
    </p>
  );
}

/** Vertical card for grids and rows. */
export function CourseCard({ course }: { course: CourseCardData }) {
  return (
    <Link href={`/course/${course.slug}`} className="group block outline-none">
      <Thumb course={course} sizes="(min-width: 1024px) 300px, (min-width: 640px) 45vw, 90vw" />
      <div className="mt-3 space-y-1">
        <h3 className="line-clamp-2 font-sans text-[0.95rem] leading-snug font-bold text-ink group-hover:text-navy-800 group-focus-visible:underline">
          {course.title}
        </h3>
        <p className="truncate text-xs text-muted-foreground">{course.instructor.name}</p>
        {course.rating_count > 0 && <Rating value={course.rating} count={course.rating_count} />}
        <Meta course={course} />
        <div className="pt-0.5">
          <Price price={course.price} />
        </div>
        {course.badge && <CourseBadge badge={course.badge} />}
      </div>
    </Link>
  );
}

/** Horizontal row for search results. */
export function CourseRow({ course }: { course: CourseCardData }) {
  return (
    <Link
      href={`/course/${course.slug}`}
      className="group grid grid-cols-[120px_1fr] gap-4 border-b border-line py-5 outline-none first:pt-0 sm:grid-cols-[240px_1fr_auto] sm:gap-6"
    >
      <Thumb course={course} sizes="(min-width: 640px) 240px, 120px" />
      <div className="min-w-0 space-y-1.5">
        <h3 className="font-sans text-base leading-snug font-bold text-ink group-hover:text-navy-800 group-focus-visible:underline">
          {course.title}
        </h3>
        {course.subtitle && <p className="line-clamp-2 hidden text-sm text-stone-700 sm:block">{course.subtitle}</p>}
        <p className="text-xs text-stone-500">{course.instructor.name}</p>
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          {course.rating_count > 0 && <Rating value={course.rating} count={course.rating_count} />}
          {course.badge && <CourseBadge badge={course.badge} />}
        </div>
        <Meta course={course} />
        <Price price={course.price} className="sm:hidden" />
      </div>
      <Price price={course.price} className="hidden text-lg sm:block" />
    </Link>
  );
}
