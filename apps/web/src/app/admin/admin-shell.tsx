"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Suspense, useState } from "react";
import {
  BookCheck,
  ChartColumn,
  ClipboardCheck,
  ExternalLink,
  FileClock,
  FolderTree,
  PackageCheck,
  LibraryBig,
  LayoutDashboard,
  Landmark,
  Menu,
  MessageSquareWarning,
  ReceiptText,
  RotateCcw,
  Settings,
  SquareChartGantt,
  TicketPercent,
  Users,
} from "lucide-react";
import { Logo } from "@/components/brand/logo";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { hasPermission, isStaff, useMe, type CurrentUser } from "@/lib/auth/session";
import { cn } from "@/lib/utils";

type Item = { href: string; label: string; icon: typeof Users; permission: string | null };

/** `permission: null` means any staff member. */
const groups: { title: string; items: Item[] }[] = [
  {
    title: "Overview",
    items: [
      { href: "/admin", label: "Dashboard", icon: LayoutDashboard, permission: null },
      { href: "/admin/reports", label: "Reports", icon: SquareChartGantt, permission: "view_reports" },
    ],
  },
  {
    title: "Catalogue and people",
    items: [
      {
        href: "/admin/instructor-applications",
        label: "Instructor applications",
        icon: ClipboardCheck,
        permission: "review_instructor_applications",
      },
      { href: "/admin/courses", label: "Courses", icon: LibraryBig, permission: "manage_courses" },
      { href: "/admin/categories", label: "Categories", icon: FolderTree, permission: "manage_categories" },
      { href: "/admin/fulfilment", label: "Fulfilment", icon: PackageCheck, permission: "manage_fulfilment" },
      { href: "/admin/course-reviews", label: "Course reviews", icon: BookCheck, permission: "moderate_courses" },
      { href: "/admin/reviews", label: "Learner reviews", icon: MessageSquareWarning, permission: "moderate_courses" },
      { href: "/admin/users", label: "Users", icon: Users, permission: "manage_users" },
    ],
  },
  {
    title: "Money",
    items: [
      { href: "/admin/orders", label: "Orders", icon: ReceiptText, permission: "manage_finance" },
      { href: "/admin/refunds", label: "Refunds", icon: RotateCcw, permission: "manage_finance" },
      { href: "/admin/coupons", label: "Coupons", icon: TicketPercent, permission: "manage_finance" },
      { href: "/admin/payouts", label: "Payouts", icon: Landmark, permission: "manage_finance" },
      { href: "/admin/finance", label: "Finance", icon: ChartColumn, permission: "manage_finance" },
    ],
  },
  {
    title: "System",
    items: [
      { href: "/admin/settings", label: "Settings", icon: Settings, permission: "manage_settings" },
      { href: "/admin/audit", label: "Audit log", icon: FileClock, permission: "view_audit_logs" },
    ],
  },
];

function visible(me: CurrentUser | null | undefined) {
  return groups
    .map((g) => ({
      ...g,
      items: g.items.filter((i) => !me || (i.permission === null ? isStaff(me) : hasPermission(me, i.permission))),
    }))
    .filter((g) => g.items.length > 0);
}

/**
 * Chrome for the admin area. Always renders its children: each admin page carries its own
 * permission gate (and the API enforces everything), so the shell only adapts its navigation.
 */
export function AdminShell({ children }: { children: React.ReactNode }) {
  const { data: me } = useMe();
  const [open, setOpen] = useState(false);
  const sections = visible(me);

  return (
    <div className="flex min-h-dvh flex-col md:flex-row">
      <nav
        aria-label="Admin"
        className="dark sticky top-0 hidden h-dvh w-64 shrink-0 flex-col overflow-y-auto bg-navy-950 px-4 py-5 text-foreground md:flex"
      >
        <Logo inverted />
        <ActiveNavList sections={sections} />
        {me && (
          <div className="mt-auto space-y-4 border-t border-white/10 pt-4">
            <Link href="/" className="flex items-center gap-2 px-3 text-sm text-white/60 hover:text-white">
              <ExternalLink className="size-4" /> View site
            </Link>
            <div className="flex items-center gap-3 px-3">
              <InstructorAvatar name={me.name} className="size-9 text-xs" />
              <span className="min-w-0 text-sm">
                <span className="block truncate font-semibold text-white">{me.name}</span>
                <span className="block truncate text-xs text-white/50">
                  {me.roles.includes("admin") ? "Administrator" : "Staff"}
                </span>
              </span>
            </div>
          </div>
        )}
      </nav>

      {/* Phones and small tablets: a bar with the menu in a sheet. */}
      <header className="dark sticky top-0 z-20 flex h-14 items-center gap-3 bg-navy-950 px-4 text-foreground md:hidden">
        <button
          type="button"
          onClick={() => setOpen(true)}
          aria-label="Open admin menu"
          className="-ml-1 rounded-md p-1.5 text-white/80 hover:bg-white/10"
        >
          <Menu className="size-5" />
        </button>
        <Logo inverted className="scale-90" />
      </header>
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetContent side="left" className="dark w-72 overflow-y-auto bg-navy-950 p-4 text-foreground">
          <SheetHeader className="p-0">
            <SheetTitle className="sr-only">Admin menu</SheetTitle>
            <Logo inverted />
          </SheetHeader>
          <ActiveNavList sections={sections} onNavigate={() => setOpen(false)} />
          <Link href="/" className="mt-6 flex items-center gap-2 px-3 text-sm text-white/60 hover:text-white">
            <ExternalLink className="size-4" /> View site
          </Link>
        </SheetContent>
      </Sheet>

      <main className="min-w-0 flex-1 bg-paper">{children}</main>
    </div>
  );
}

/**
 * Reading the URL must happen inside Suspense: admin pages with dynamic segments (like
 * /admin/users/[id]) would otherwise block prerendering. The fallback is the same list
 * with nothing highlighted.
 */
function ActiveNavList(props: { sections: ReturnType<typeof visible>; onNavigate?: () => void }) {
  return (
    <Suspense fallback={<NavList {...props} pathname="" />}>
      <PathAwareNavList {...props} />
    </Suspense>
  );
}

function PathAwareNavList(props: { sections: ReturnType<typeof visible>; onNavigate?: () => void }) {
  return <NavList {...props} pathname={usePathname()} />;
}

function NavList({
  sections,
  pathname,
  onNavigate,
}: {
  sections: ReturnType<typeof visible>;
  pathname: string;
  onNavigate?: () => void;
}) {
  return (
    <div className="mt-6 space-y-5">
      {sections.map((g) => (
        <div key={g.title}>
          <p className="mb-1.5 px-3 text-[0.65rem] font-bold tracking-[0.2em] text-white/40 uppercase">{g.title}</p>
          <ul className="space-y-0.5">
            {g.items.map(({ href, label, icon: Icon }) => {
              const active = href === "/admin" ? pathname === href : pathname.startsWith(href);
              return (
                <li key={href}>
                  <Link
                    href={href}
                    onClick={onNavigate}
                    aria-current={active ? "page" : undefined}
                    className={cn(
                      "flex items-center gap-3 rounded-md px-3 py-2 text-sm",
                      active
                        ? "bg-white/10 font-semibold text-white"
                        : "text-white/65 hover:bg-white/5 hover:text-white",
                    )}
                  >
                    <Icon className={cn("size-4", active && "text-gold-400")} />
                    {label}
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      ))}
    </div>
  );
}
