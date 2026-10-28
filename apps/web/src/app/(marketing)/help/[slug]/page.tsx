import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, ArrowRight, ChevronRight, Lightbulb } from "lucide-react";
import { CategoryIcon } from "@/components/help/category-icon";
import { SupportActions } from "@/components/help/support-actions";
import { categoryName, findArticle, helpArticles } from "@/lib/help/content";

export function generateStaticParams() {
  return helpArticles.map((a) => ({ slug: a.slug }));
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const article = findArticle((await params).slug);
  return article ? { title: `${article.title} — Help Centre`, description: article.summary } : {};
}

export default async function HelpArticlePage({ params }: { params: Promise<{ slug: string }> }) {
  const article = findArticle((await params).slug);
  if (!article) notFound();
  const related = helpArticles.filter((a) => a.category === article.category && a.slug !== article.slug).slice(0, 4);

  return (
    <div className="mx-auto max-w-[1100px] px-4 py-10 sm:px-6">
      <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1 text-sm text-stone-500">
        <Link href="/" className="hover:text-navy-800">
          Home
        </Link>
        <ChevronRight className="size-3.5" aria-hidden />
        <Link href="/help" className="hover:text-navy-800">
          Help Centre
        </Link>
        <ChevronRight className="size-3.5" aria-hidden />
        <span className="text-stone-700">{categoryName(article.category)}</span>
      </nav>

      <div className="mt-6 grid gap-10 lg:grid-cols-[1fr_300px]">
        <article>
          <p className="inline-flex items-center gap-2 text-xs font-bold tracking-wide text-gold-600 uppercase">
            <CategoryIcon category={article.category} className="size-4" />
            {categoryName(article.category)}
          </p>
          <h1 className="mt-2 text-h1">{article.title}</h1>
          <p className="mt-3 text-lg text-stone-600">{article.summary}</p>

          <h2 className="mt-8 text-h3">Steps</h2>
          <ol className="mt-4 space-y-4">
            {article.steps.map((s, i) => (
              <li key={s} className="flex gap-4">
                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-navy-900 text-sm font-bold text-white">
                  {i + 1}
                </span>
                <span className="pt-0.5 leading-relaxed text-stone-800">{s}</span>
              </li>
            ))}
          </ol>

          {article.tip && (
            <p className="mt-8 flex gap-3 rounded-md bg-gold-50 px-4 py-3 text-sm text-stone-800">
              <Lightbulb className="mt-0.5 size-4 shrink-0 text-gold-600" aria-hidden />
              {article.tip}
            </p>
          )}

          {article.links && (
            <div className="mt-6 flex flex-wrap gap-3">
              {article.links.map(([label, href]) => (
                <Link
                  key={href}
                  href={href}
                  className="inline-flex items-center gap-1 rounded-md border border-line px-3 py-2 text-sm font-semibold text-navy-800 hover:border-navy-800"
                >
                  {label} <ArrowRight className="size-4" aria-hidden />
                </Link>
              ))}
            </div>
          )}

          <section className="mt-12 rounded-md border border-line bg-paper-2/60 p-6" aria-labelledby="still-title">
            <h2 id="still-title" className="text-h3">
              Still need help?
            </h2>
            <p className="mt-2 mb-5 text-sm text-stone-600">
              Raise a ticket and our support team will reply by email. Never include passwords, OTPs or card details.
            </p>
            <SupportActions />
          </section>
        </article>

        <aside className="space-y-4">
          {related.length > 0 && (
            <div className="rounded-md border border-line bg-white p-5">
              <h2 className="font-semibold text-ink">Related articles</h2>
              <ul className="mt-3 space-y-3 text-sm">
                {related.map((a) => (
                  <li key={a.slug}>
                    <Link href={`/help/${a.slug}`} className="text-navy-800 hover:underline">
                      {a.title}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
          <Link
            href="/help"
            className="inline-flex items-center gap-1 text-sm font-semibold text-navy-800 hover:underline"
          >
            <ArrowLeft className="size-4" aria-hidden /> Back to Help Centre
          </Link>
        </aside>
      </div>
    </div>
  );
}
