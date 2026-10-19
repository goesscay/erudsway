import Link from "next/link";
import { notFound } from "next/navigation";
import { Check, ChevronLeft, ChevronRight, SlidersHorizontal, Star, X } from "lucide-react";
import { CourseRow } from "@/components/course/course-card";
import { getCategory, searchCourses } from "@/lib/catalog/queries";
import { browseHref, listParam, toggleListValue } from "@/lib/catalog/search-url";
import { levelLabel, type Category, type CourseSearchMeta, type CourseSearchParams } from "@/lib/catalog/types";
import { cn } from "@/lib/utils";
import { sortOptions } from "@/lib/catalog/sort-options";
import { SortSelect } from "./sort-select";

const durationLabel: Record<string, string> = {
  short: "Under 3 hours",
  medium: "3–6 hours",
  long: "6–17 hours",
  extra_long: "17+ hours",
};

/**
 * Server-rendered browse/search page. Filters are plain links, so the page works without
 * JavaScript and every filtered view has a shareable URL.
 */
export async function Browse({ params, categorySlug }: { params: CourseSearchParams; categorySlug?: string }) {
  const category = categorySlug ? await getCategory(categorySlug) : null;
  if (categorySlug && !category) notFound();

  const result = await searchCourses({ ...params, category: categorySlug, per_page: "12" });
  if (!result) notFound();

  const basePath = categorySlug ? `/courses/${categorySlug}` : "/courses";
  const href = (changes: Partial<Record<keyof CourseSearchParams, string | null>>) => browseHref(basePath, params, changes);
  const { data: courses, meta } = result;
  const activeFilters = activeFilterChips(params, href);

  return (
    <div className="mx-auto max-w-[1340px] px-6 py-10">
      <Header category={category} query={params.q} total={meta.total} />

      <div className="mt-8 grid gap-10 lg:grid-cols-[260px_1fr]">
        {/* Filters: always open on desktop, collapsible on small screens */}
        <details className="group lg:hidden" open={false}>
          <summary className="flex cursor-pointer list-none items-center gap-2 rounded-md border border-line bg-white px-4 py-3 text-sm font-semibold text-ink [&::-webkit-details-marker]:hidden">
            <SlidersHorizontal className="size-4" /> Filters
            {activeFilters.length > 0 && <span className="tabular text-gold-600">({activeFilters.length})</span>}
          </summary>
          <div className="mt-4">
            <Filters params={params} meta={meta} href={href} />
          </div>
        </details>
        <aside className="hidden lg:block" aria-label="Filters">
          <Filters params={params} meta={meta} href={href} />
        </aside>

        <section aria-label="Results" className="min-w-0">
          <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
            <p className="tabular text-sm text-stone-700">
              {meta.total === 1 ? "1 course" : `${meta.total.toLocaleString("en-IN")} courses`}
            </p>
            <SortSelect
              value={params.sort ?? ""}
              hrefs={Object.fromEntries(sortOptions.map((o) => [o.value, href({ sort: o.value || null })]))}
            />
          </div>

          {activeFilters.length > 0 && (
            <div className="mb-5 flex flex-wrap items-center gap-2">
              {activeFilters.map((f) => (
                <Link
                  key={f.label}
                  href={f.remove}
                  className="inline-flex items-center gap-1.5 rounded-full bg-navy-100 py-1 pr-2 pl-3 text-xs font-semibold text-navy-800 hover:bg-navy-100/70"
                >
                  {f.label} <X className="size-3.5" aria-label="Remove filter" />
                </Link>
              ))}
              <Link href={basePath + (params.q ? `?q=${encodeURIComponent(params.q)}` : "")} className="ml-1 text-xs font-semibold text-stone-500 hover:text-navy-900">
                Clear all
              </Link>
            </div>
          )}

          {courses.length === 0 ? (
            <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
              <p className="font-semibold text-ink">No courses match these filters</p>
              <p className="mt-1 text-sm text-stone-500">Try removing a filter or searching for a broader term.</p>
            </div>
          ) : (
            <div>
              {courses.map((c) => (
                <CourseRow key={c.id} course={c} />
              ))}
            </div>
          )}

          {meta.last_page > 1 && <Pagination meta={meta} href={href} />}
        </section>
      </div>
    </div>
  );
}

function Header({ category, query, total }: { category: Category | null; query?: string; total: number }) {
  if (category) {
    return (
      <header className="border-b border-line pb-8">
        {category.parent && (
          <nav aria-label="Breadcrumb" className="mb-3 text-sm">
            <Link href="/courses" className="text-stone-500 hover:text-navy-900">
              All courses
            </Link>
            <span className="mx-2 text-stone-400">/</span>
            <Link href={`/courses/${category.parent.slug}`} className="font-semibold text-navy-800 hover:underline">
              {category.parent.name}
            </Link>
          </nav>
        )}
        <h1 className="text-h1">{category.name} courses</h1>
        {category.description && <p className="mt-3 max-w-[60ch] text-stone-700">{category.description}</p>}
        {category.children && category.children.length > 0 && (
          <ul className="mt-6 flex flex-wrap gap-2">
            {category.children.map((s) => (
              <li key={s.slug}>
                <Link
                  href={`/courses/${s.slug}`}
                  className="inline-flex h-9 items-center rounded-full border border-line bg-white px-4 text-sm font-medium text-stone-700 hover:border-navy-900 hover:text-navy-900"
                >
                  {s.name}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </header>
    );
  }

  return (
    <header className="border-b border-line pb-8">
      <h1 className="text-h1">{query ? <>Results for &ldquo;{query}&rdquo;</> : "All courses"}</h1>
      <p className="mt-3 text-stone-700">
        {query
          ? `${total.toLocaleString("en-IN")} ${total === 1 ? "course matches" : "courses match"} your search.`
          : "GST, income tax, audit, company law and more — taught by practising professionals."}
      </p>
    </header>
  );
}

type HrefFn = (changes: Partial<Record<keyof CourseSearchParams, string | null>>) => string;

function Filters({ params, meta, href }: { params: CourseSearchParams; meta: CourseSearchMeta; href: HrefFn }) {
  const levels = listParam(params.level);
  const languages = listParam(params.language);
  const f = meta.facets;

  return (
    <div className="space-y-8">
      <FilterGroup title="Ratings">
        {f.ratings.map((r) => (
          <FilterLink
            key={r.value}
            href={href({ rating: params.rating === String(r.value) ? null : String(r.value) })}
            active={params.rating === String(r.value)}
            count={r.count}
            radio
          >
            <span className="inline-flex items-center gap-1">
              <Star className="size-3.5 fill-gold-500 text-gold-500" /> {r.value.toFixed(1)} &amp; up
            </span>
          </FilterLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Level">
        {f.levels
          .filter((l) => l.count > 0 || levels.includes(l.value))
          .map((l) => (
            <FilterLink key={l.value} href={href({ level: toggleListValue(levels, l.value) })} active={levels.includes(l.value)} count={l.count}>
              {levelLabel[l.value] ?? l.value}
            </FilterLink>
          ))}
      </FilterGroup>

      <FilterGroup title="Language">
        {f.languages.map((l) => (
          <FilterLink key={l.value} href={href({ language: toggleListValue(languages, l.value) })} active={languages.includes(l.value)} count={l.count}>
            {l.value}
          </FilterLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Price">
        {f.prices.map((p) => (
          <FilterLink
            key={p.value}
            href={href({ price: params.price === p.value ? null : p.value })}
            active={params.price === p.value}
            count={p.count}
            radio
          >
            {p.value === "free" ? "Free" : "Paid"}
          </FilterLink>
        ))}
      </FilterGroup>

      <FilterGroup title="Video duration">
        {f.durations.map((d) => (
          <FilterLink
            key={d.value}
            href={href({ duration: params.duration === d.value ? null : d.value })}
            active={params.duration === d.value}
            count={d.count}
            radio
          >
            {durationLabel[d.value]}
          </FilterLink>
        ))}
      </FilterGroup>
    </div>
  );
}

function FilterGroup({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <fieldset>
      <legend className="mb-3 font-sans text-sm font-bold text-ink">{title}</legend>
      <ul className="space-y-1">{children}</ul>
    </fieldset>
  );
}

function FilterLink({
  href,
  active,
  count,
  radio,
  children,
}: {
  href: string;
  active: boolean;
  count: number;
  radio?: boolean;
  children: React.ReactNode;
}) {
  const disabled = count === 0 && !active;
  return (
    <li>
      <Link
        href={href}
        scroll={false}
        aria-current={active ? "true" : undefined}
        aria-disabled={disabled || undefined}
        tabIndex={disabled ? -1 : undefined}
        className={cn(
          "flex items-center gap-2.5 rounded-sm py-1.5 text-sm",
          disabled ? "pointer-events-none text-stone-400" : "text-stone-700 hover:text-navy-900",
          active && "font-semibold text-navy-900",
        )}
      >
        <span
          className={cn(
            "flex size-4 shrink-0 items-center justify-center ring-1",
            radio ? "rounded-full" : "rounded-[3px]",
            active ? "bg-navy-900 ring-navy-900" : "ring-stone-500/50",
          )}
        >
          {active && (radio ? <span className="size-1.5 rounded-full bg-white" /> : <Check className="size-3 text-white" strokeWidth={3} />)}
        </span>
        <span className="flex-1">{children}</span>
        <span className="tabular text-xs text-stone-500">{count}</span>
      </Link>
    </li>
  );
}

function Pagination({ meta, href }: { meta: CourseSearchMeta; href: HrefFn }) {
  const { current_page: page, last_page: last } = meta;
  const pages = Array.from({ length: last }, (_, i) => i + 1).filter((p) => p === 1 || p === last || Math.abs(p - page) <= 1);

  return (
    <nav aria-label="Pagination" className="mt-10 flex items-center justify-center gap-1">
      <PageLink href={href({ page: String(page - 1) })} disabled={page <= 1} label="Previous page">
        <ChevronLeft className="size-4" />
      </PageLink>
      {pages.map((p, i) => (
        <span key={p} className="flex items-center">
          {i > 0 && p - pages[i - 1]! > 1 && <span className="px-1 text-stone-400">…</span>}
          <PageLink href={href({ page: p === 1 ? null : String(p) })} active={p === page} label={`Page ${p}`}>
            {p}
          </PageLink>
        </span>
      ))}
      <PageLink href={href({ page: String(page + 1) })} disabled={page >= last} label="Next page">
        <ChevronRight className="size-4" />
      </PageLink>
    </nav>
  );
}

function PageLink({
  href,
  active,
  disabled,
  label,
  children,
}: {
  href: string;
  active?: boolean;
  disabled?: boolean;
  label: string;
  children: React.ReactNode;
}) {
  if (disabled) {
    return (
      <span aria-hidden className="flex size-10 items-center justify-center rounded-md text-stone-300">
        {children}
      </span>
    );
  }
  return (
    <Link
      href={href}
      aria-label={label}
      aria-current={active ? "page" : undefined}
      className={cn(
        "tabular flex size-10 items-center justify-center rounded-md text-sm font-semibold",
        active ? "bg-navy-900 text-white" : "text-stone-700 hover:bg-paper-2",
      )}
    >
      {children}
    </Link>
  );
}

function activeFilterChips(params: CourseSearchParams, href: HrefFn): { label: string; remove: string }[] {
  const chips: { label: string; remove: string }[] = [];
  const levels = listParam(params.level);
  const languages = listParam(params.language);
  for (const l of levels) chips.push({ label: levelLabel[l] ?? l, remove: href({ level: toggleListValue(levels, l) }) });
  for (const l of languages) chips.push({ label: l, remove: href({ language: toggleListValue(languages, l) }) });
  if (params.rating) chips.push({ label: `${params.rating}★ & up`, remove: href({ rating: null }) });
  if (params.price) chips.push({ label: params.price === "free" ? "Free" : "Paid", remove: href({ price: null }) });
  if (params.duration) chips.push({ label: durationLabel[params.duration] ?? params.duration, remove: href({ duration: null }) });
  return chips;
}
