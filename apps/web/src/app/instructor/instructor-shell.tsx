"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import { ExternalLink, MonitorPlay, ShieldAlert } from "lucide-react";
import { Logo } from "@/components/brand/logo";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { buttonVariants } from "@/components/ui/button";
import { useMe } from "@/lib/auth/session";
import { cn } from "@/lib/utils";

/**
 * Access gate for the whole instructor area. Pages always render (they show their own loading
 * states), so the route tree stays intact; once we know the visitor isn't an instructor we
 * redirect guests to log in or explain how to apply. The API enforces access either way.
 *
 * It deliberately doesn't call usePathname(): the course builder lives under a dynamic [id]
 * segment, where reading the URL during render would block prerendering of the whole area.
 */
export function InstructorGate({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { data: me, isPending } = useMe();

  useEffect(() => {
    if (!isPending && me === null) router.replace(`/login?next=${encodeURIComponent(window.location.pathname)}`);
  }, [isPending, me, router]);

  if (!isPending && me && !me.instructor) {
    return (
      <div className="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
        <ShieldAlert className="size-10 text-gold-600" strokeWidth={1.5} />
        <h1 className="text-h3 mt-4">This area is for approved instructors</h1>
        <p className="mt-2 text-sm text-stone-700">Apply to teach on Erudsway — once approved, you can build and publish courses here.</p>
        <Link href="/teach" className={buttonVariants({ variant: "outline", className: "mt-6" })}>
          Apply to teach
        </Link>
      </div>
    );
  }

  return <>{children}</>;
}

/** Sidebar chrome for the instructor dashboard pages (the course builder has its own). */
export function InstructorDashboardShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { data: me } = useMe();

  return (
    <div className="flex min-h-dvh">
      <nav aria-label="Instructor" className="dark sticky top-0 hidden h-dvh w-60 shrink-0 flex-col bg-navy-950 px-4 py-5 text-foreground md:flex">
        <Logo inverted />
        <p className="mt-8 mb-2 px-3 text-[0.65rem] font-bold tracking-[0.2em] text-white/40 uppercase">Instructor</p>
        <ul>
          <li>
            <Link
              href="/instructor"
              aria-current={pathname === "/instructor" ? "page" : undefined}
              className={cn(
                "flex items-center gap-3 rounded-md px-3 py-2.5 text-sm",
                pathname === "/instructor" ? "bg-white/10 font-semibold text-white" : "text-white/65 hover:bg-white/5 hover:text-white",
              )}
            >
              <MonitorPlay className={cn("size-4", pathname === "/instructor" && "text-gold-400")} />
              Courses
            </Link>
          </li>
        </ul>
        <p className="mt-6 px-3 text-xs leading-relaxed text-white/40">Performance, revenue and learner Q&amp;A arrive with checkout and reviews.</p>
        <div className="mt-auto space-y-4 border-t border-white/10 pt-4">
          <Link href="/" className="flex items-center gap-2 px-3 text-sm text-white/60 hover:text-white">
            <ExternalLink className="size-4" /> View site
          </Link>
          {me && (
            <div className="flex items-center gap-3 px-3">
              <InstructorAvatar name={me.name} className="size-9 text-xs" />
              <span className="min-w-0 text-sm">
                <span className="block truncate font-semibold text-white">{me.instructor?.display_name ?? me.name}</span>
                <span className="block truncate text-xs text-white/50">Instructor</span>
              </span>
            </div>
          )}
        </div>
      </nav>
      <main className="min-w-0 flex-1 bg-paper">{children}</main>
    </div>
  );
}
