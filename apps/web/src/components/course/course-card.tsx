import Image from "next/image";
import Link from "next/link";
import type { Course } from "@/lib/demo-data";
import { formatDuration, formatPaise } from "@/lib/format";
import { CourseBadge } from "./course-badge";
import { Rating } from "./rating";

export function CourseCard({ course }: { course: Course }) {
  return (
    <Link href={`/course/${course.slug}`} className="group block outline-none">
      <div className="relative aspect-[16/9] overflow-hidden rounded-sm bg-paper-2 ring-1 ring-line">
        <Image
          src={course.image.src}
          alt={course.image.alt}
          fill
          sizes="(min-width: 1024px) 280px, (min-width: 640px) 45vw, 90vw"
          className="object-cover transition-[filter,transform] duration-300 ease-out group-hover:scale-[1.02] group-hover:brightness-90"
        />
      </div>
      <div className="mt-3 space-y-1">
        <h3 className="line-clamp-2 font-sans text-[0.95rem] leading-snug font-bold text-ink group-hover:text-navy-800 group-focus-visible:underline">
          {course.title}
        </h3>
        <p className="truncate text-xs text-muted-foreground">{course.instructor.name}</p>
        <Rating value={course.rating} count={course.ratingCount} />
        <p className="text-xs text-muted-foreground">
          {formatDuration(course.seconds)} total · {course.lectures} lectures · {course.level}
        </p>
        <div className="flex items-baseline gap-2 pt-0.5">
          <span className="tabular font-bold text-ink">{formatPaise(course.pricePaise)}</span>
          <span className="tabular text-sm text-muted-foreground line-through">
            {formatPaise(course.listPricePaise)}
          </span>
        </div>
        {course.badge && <CourseBadge badge={course.badge} />}
      </div>
    </Link>
  );
}
