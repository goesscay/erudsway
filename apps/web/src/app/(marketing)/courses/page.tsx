import type { Metadata } from "next";
import { Suspense } from "react";
import { Browse } from "@/components/catalog/browse";
import { BrowseSkeleton } from "@/components/catalog/browse-skeleton";
import { readSearchParams } from "@/lib/catalog/search-url";

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

export async function generateMetadata({ searchParams }: { searchParams: SearchParams }): Promise<Metadata> {
  const { q } = readSearchParams(await searchParams);
  return q
    ? { title: `Courses on “${q}”`, robots: { index: false, follow: true } }
    : {
        title: "All courses",
        description: "Browse GST, income tax, TDS, audit, company law, FEMA and capital markets courses on Erudsway.",
        alternates: { canonical: "/courses" },
      };
}

export default function CoursesPage({ searchParams }: { searchParams: SearchParams }) {
  return (
    <Suspense fallback={<BrowseSkeleton />}>
      <Results searchParams={searchParams} />
    </Suspense>
  );
}

async function Results({ searchParams }: { searchParams: SearchParams }) {
  return <Browse params={readSearchParams(await searchParams)} />;
}
