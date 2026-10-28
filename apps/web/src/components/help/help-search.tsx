"use client";

import Link from "next/link";
import { useId, useState } from "react";
import { ArrowRight, Search } from "lucide-react";
import { categoryName, popularSearches, searchArticles } from "@/lib/help/content";

/** The hero search: suggestions appear as you type and open the article. */
export function HelpSearch() {
  const [query, setQuery] = useState("");
  const listId = useId();
  const hits = query.trim() ? searchArticles(query).slice(0, 6) : [];

  return (
    <div className="max-w-2xl">
      <form
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          document.getElementById("articles")?.scrollIntoView({ behavior: "smooth" });
          window.dispatchEvent(new CustomEvent("help:search", { detail: query }));
        }}
        className="relative"
      >
        <label htmlFor={`${listId}-q`} className="sr-only">
          Search the Help Centre
        </label>
        <Search
          className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-stone-500"
          aria-hidden
        />
        <input
          id={`${listId}-q`}
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search, e.g. “GST trial” or “invoice”"
          autoComplete="off"
          aria-controls={listId}
          className="h-14 w-full rounded-md border border-white/20 bg-white pr-28 pl-12 text-base text-ink shadow-lg outline-none placeholder:text-stone-500 focus-visible:ring-2 focus-visible:ring-gold-400"
        />
        <button
          type="submit"
          className="absolute top-1/2 right-2 h-10 -translate-y-1/2 rounded-md bg-gold-400 px-4 text-sm font-bold text-navy-950 hover:bg-gold-200"
        >
          Search
        </button>
        {query.trim() && (
          <div
            id={listId}
            className="absolute inset-x-0 top-full z-20 mt-2 overflow-hidden rounded-md border border-line bg-white text-ink shadow-xl"
          >
            {hits.length ? (
              <ul>
                {hits.map((a) => (
                  <li key={a.slug}>
                    <Link
                      href={`/help/${a.slug}`}
                      className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-paper-2 focus-visible:bg-paper-2"
                    >
                      <span className="min-w-0">
                        <span className="block truncate font-semibold">{a.title}</span>
                        <span className="text-xs text-stone-500">{categoryName(a.category)}</span>
                      </span>
                      <ArrowRight className="size-4 shrink-0 text-stone-400" aria-hidden />
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="px-4 py-3 text-sm text-stone-600">
                No matching article.{" "}
                <Link href="/help/tickets/new" className="font-semibold text-navy-800 underline underline-offset-2">
                  Raise a ticket
                </Link>{" "}
                and we’ll help.
              </p>
            )}
          </div>
        )}
      </form>
      <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
        <span className="text-white/70">Popular:</span>
        {popularSearches.map((p) => (
          <button
            key={p}
            type="button"
            onClick={() => setQuery(p)}
            className="rounded-full border border-white/20 px-3 py-1 text-white/90 hover:border-gold-400 hover:text-gold-200"
          >
            {p}
          </button>
        ))}
      </div>
    </div>
  );
}
