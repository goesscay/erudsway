"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ArrowRight, X } from "lucide-react";
import { CategoryIcon } from "@/components/help/category-icon";
import { categoryName, helpCategories, searchArticles, type HelpCategory } from "@/lib/help/content";
import { cn } from "@/lib/utils";

/** Topic cards plus the filterable article list. The hero search feeds in through a "help:search" event. */
export function KnowledgeBase() {
  const [category, setCategory] = useState<HelpCategory | "">("");
  const [query, setQuery] = useState("");
  const articles = searchArticles(query, category || undefined);

  useEffect(() => {
    const onSearch = (e: Event) => {
      setQuery(String((e as CustomEvent).detail ?? ""));
      setCategory("");
    };
    window.addEventListener("help:search", onSearch);
    return () => window.removeEventListener("help:search", onSearch);
  }, []);

  const choose = (value: HelpCategory | "") => {
    setCategory(value);
    setQuery("");
    document.getElementById("articles")?.scrollIntoView({ behavior: "smooth" });
  };

  return (
    <>
      <section aria-labelledby="topics-title" className="mx-auto max-w-[1340px] px-4 py-14 sm:px-6">
        <h2 id="topics-title" className="text-h2">
          Browse by topic
        </h2>
        <ul className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {helpCategories.map((c) => (
            <li key={c.value}>
              <button
                type="button"
                onClick={() => choose(c.value)}
                aria-pressed={category === c.value}
                className={cn(
                  "group flex h-full w-full flex-col items-start rounded-md border bg-white p-5 text-left transition hover:-translate-y-0.5 hover:border-gold-400 hover:shadow-md",
                  category === c.value ? "border-gold-400 ring-1 ring-gold-400" : "border-line",
                )}
              >
                <span className="grid size-11 place-items-center rounded-md bg-navy-100 text-navy-800">
                  <CategoryIcon category={c.value} className="size-5" />
                </span>
                <strong className="mt-4 text-ink">{c.name}</strong>
                <span className="mt-1 text-sm text-stone-600">{c.desc}</span>
                <span className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-gold-600">
                  Explore <ArrowRight className="size-4 transition group-hover:translate-x-0.5" aria-hidden />
                </span>
              </button>
            </li>
          ))}
        </ul>
      </section>

      <section
        id="articles"
        aria-labelledby="articles-title"
        className="scroll-mt-24 border-y border-line bg-paper-2/60"
      >
        <div className="mx-auto max-w-[1340px] px-4 py-14 sm:px-6">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <h2 id="articles-title" className="text-h2">
                Help articles
              </h2>
              <p className="mt-2 text-stone-600">Step-by-step answers to the questions learners ask most.</p>
            </div>
            {query && (
              <button
                type="button"
                onClick={() => setQuery("")}
                className="inline-flex items-center gap-1.5 rounded-full bg-navy-100 px-3 py-1 text-sm font-semibold text-navy-800"
              >
                Results for “{query}” <X className="size-3.5" aria-label="Clear search" />
              </button>
            )}
          </div>
          <div className="mt-6 flex flex-wrap gap-2" role="group" aria-label="Filter by topic">
            {[{ value: "" as const, name: "All topics" }, ...helpCategories].map((c) => (
              <button
                key={c.value || "all"}
                type="button"
                onClick={() => setCategory(c.value)}
                aria-pressed={category === c.value}
                className={cn(
                  "rounded-full border px-3.5 py-1.5 text-sm font-semibold transition",
                  category === c.value
                    ? "border-navy-900 bg-navy-900 text-white"
                    : "border-line bg-white text-stone-700 hover:border-navy-800",
                )}
              >
                {c.name}
              </button>
            ))}
          </div>
          {articles.length ? (
            <ul className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {articles.map((a) => (
                <li key={a.slug}>
                  <Link
                    href={`/help/${a.slug}`}
                    className="group flex h-full flex-col rounded-md border border-line bg-white p-5 transition hover:border-gold-400 hover:shadow-md"
                  >
                    <span className="text-xs font-bold tracking-wide text-gold-600 uppercase">
                      {categoryName(a.category)}
                    </span>
                    <strong className="mt-2 text-ink">{a.title}</strong>
                    <span className="mt-1 flex-1 text-sm text-stone-600">{a.summary}</span>
                    <span className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-navy-800">
                      Read guide <ArrowRight className="size-4 transition group-hover:translate-x-0.5" aria-hidden />
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-6 rounded-md border border-dashed border-line bg-white px-4 py-10 text-center text-stone-600">
              No articles match.{" "}
              <button
                type="button"
                className="font-semibold text-navy-800 underline underline-offset-2"
                onClick={() => {
                  setQuery("");
                  setCategory("");
                }}
              >
                Show all articles
              </button>{" "}
              or{" "}
              <Link href="/help/tickets/new" className="font-semibold text-navy-800 underline underline-offset-2">
                raise a ticket
              </Link>
              .
            </p>
          )}
        </div>
      </section>
    </>
  );
}
