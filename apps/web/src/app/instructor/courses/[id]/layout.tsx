import { Suspense } from "react";
import { Logo } from "@/components/brand/logo";
import { BuilderHeader, BuilderHeaderFallback, BuilderNav, BuilderNavFallback, StepSkeleton } from "./builder-shell";

/**
 * The course id and current step come from the URL, which only exists at request time, so
 * everything that reads it streams in behind its own Suspense boundary.
 */
export default function CourseBuilderLayout({ children }: LayoutProps<"/instructor/courses/[id]">) {
  return (
    <div className="flex min-h-dvh flex-col bg-paper">
      <Suspense fallback={<BuilderHeaderFallback />}>
        <BuilderHeader />
      </Suspense>

      <div className="mx-auto grid w-full max-w-6xl flex-1 gap-10 px-4 py-8 sm:px-6 lg:grid-cols-[220px_1fr]">
        <nav aria-label="Course builder steps" className="min-w-0">
          <Suspense fallback={<BuilderNavFallback />}>
            <BuilderNav />
          </Suspense>
          <div className="mt-8 hidden lg:block">
            <Logo className="opacity-60 grayscale" />
          </div>
        </nav>

        <main className="min-w-0">
          <Suspense fallback={<StepSkeleton />}>{children}</Suspense>
        </main>
      </div>
    </div>
  );
}
