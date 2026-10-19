import type { Metadata } from "next";
import { Suspense } from "react";
import { Browse } from "@/components/catalog/browse";
import { BrowseSkeleton } from "@/components/catalog/browse-skeleton";
import { getCategory } from "@/lib/catalog/queries";
import { readSearchParams } from "@/lib/catalog/search-url";

type Params = Promise<{ category: string }>;
type SearchParams = Promise<Record<string, string | string[] | undefined>>;

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { category: slug } = await params;
  const category = await getCategory(slug).catch(() => null);
  if (!category) return { title: "Courses" };

  return {
    title: `${category.name} courses`,
    description: category.description ?? `Learn ${category.name} from practising professionals on Erudsway.`,
    alternates: { canonical: `/courses/${category.slug}` },
  };
}

export default function CategoryPage({ params, searchParams }: { params: Params; searchParams: SearchParams }) {
  return (
    <Suspense fallback={<BrowseSkeleton />}>
      <Results params={params} searchParams={searchParams} />
    </Suspense>
  );
}

async function Results({ params, searchParams }: { params: Params; searchParams: SearchParams }) {
  const [{ category }, raw] = await Promise.all([params, searchParams]);
  return <Browse params={readSearchParams(raw)} categorySlug={category} />;
}
