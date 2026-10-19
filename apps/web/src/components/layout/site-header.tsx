import { Suspense } from "react";
import { Search } from "lucide-react";
import { Logo } from "@/components/brand/logo";
import { AuthNav, VerifyEmailBanner } from "./auth-nav";
import { CategoryNav } from "./category-nav";

export function SiteHeader() {
  return (
    <>
      <VerifyEmailBanner />
      <header className="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
        <div className="mx-auto flex h-[72px] max-w-[1340px] items-center gap-6 px-6">
          <Logo />

          <form action="/courses" role="search" className="relative hidden flex-1 md:block">
            <label htmlFor="site-search" className="sr-only">
              Search courses
            </label>
            <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-stone-500" />
            <input
              id="site-search"
              name="q"
              type="search"
              placeholder="Search GST, TDS, Form 3CD, ROC filings…"
              className="h-11 w-full rounded-full border border-input bg-paper pr-4 pl-11 text-sm placeholder:text-stone-500 focus:border-navy-900 focus:bg-white focus:outline-none"
            />
          </form>

          <nav className="ml-auto flex items-center gap-1 text-sm font-medium md:ml-0">
            <AuthNav />
          </nav>
        </div>

        <nav aria-label="Categories" className="hidden min-h-[41px] border-t border-line/70 lg:block">
          <Suspense>
            <CategoryNav />
          </Suspense>
        </nav>
      </header>
    </>
  );
}
