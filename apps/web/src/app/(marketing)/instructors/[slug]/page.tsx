import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { CourseCard } from "@/components/course/course-card";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { Skeleton } from "@/components/ui/skeleton";
import { getInstructor } from "@/lib/catalog/queries";
import { formatCount } from "@/lib/format";

type Params = Promise<{ slug: string }>;

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { slug } = await params;
  const instructor = await getInstructor(slug).catch(() => null);
  if (!instructor) return { title: "Instructor not found" };

  return {
    title: instructor.name,
    description: instructor.headline ?? `Courses by ${instructor.name} on Erudsway.`,
    alternates: { canonical: `/instructors/${instructor.slug}` },
  };
}

export default function InstructorPage({ params }: { params: Params }) {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-[1180px] space-y-4 px-6 py-14">
          <Skeleton className="size-24 rounded-full" />
          <Skeleton className="h-10 w-72" />
          <Skeleton className="h-4 w-96 max-w-full" />
        </div>
      }
    >
      <Profile params={params} />
    </Suspense>
  );
}

async function Profile({ params }: { params: Params }) {
  const { slug } = await params;
  const instructor = await getInstructor(slug);
  if (!instructor) notFound();

  const stats: [string, string][] = [
    [formatCount(instructor.stats.learners), "learners"],
    [String(instructor.stats.courses), instructor.stats.courses === 1 ? "course" : "courses"],
    ...(instructor.stats.reviews > 0
      ? ([
          [instructor.stats.rating.toFixed(1), "average rating"],
          [formatCount(instructor.stats.reviews), "reviews"],
        ] as [string, string][])
      : []),
  ];

  return (
    <>
      <section className="border-b border-line bg-white">
        <div className="mx-auto grid max-w-[1180px] gap-10 px-6 py-14 md:grid-cols-[1fr_auto]">
          <div>
            <p className="text-xs font-bold tracking-[0.2em] text-gold-600 uppercase">Instructor</p>
            <h1 className="mt-3 text-h1">{instructor.name}</h1>
            {instructor.headline && <p className="mt-2 text-lg text-stone-700">{instructor.headline}</p>}
            <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4">
              {stats.map(([n, l]) => (
                <div key={l}>
                  <dt className="text-xs text-stone-500">{l}</dt>
                  <dd className="tabular font-serif text-2xl font-semibold text-navy-900">{n}</dd>
                </div>
              ))}
            </dl>
            {instructor.bio && (
              <div className="mt-8 max-w-[65ch]">
                <h2 className="font-sans text-base font-bold text-ink">About me</h2>
                <p className="mt-2 leading-relaxed text-stone-700">{instructor.bio}</p>
              </div>
            )}
          </div>
          <InstructorAvatar
            name={instructor.name}
            className="order-first size-28 text-4xl md:order-none md:size-40 md:text-5xl"
          />
        </div>
      </section>

      <section className="mx-auto max-w-[1180px] px-6 py-14">
        <h2 className="text-h2">Courses by {instructor.name}</h2>
        {instructor.courses.length === 0 ? (
          <p className="mt-6 text-sm text-stone-500">No published courses yet.</p>
        ) : (
          <div className="mt-8 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
            {instructor.courses.map((c) => (
              <CourseCard key={c.id} course={c} />
            ))}
          </div>
        )}
      </section>
    </>
  );
}
