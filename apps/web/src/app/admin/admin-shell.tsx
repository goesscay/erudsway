"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Suspense, useId, useState } from "react";
import {
  BookCheck,
  ChevronDown,
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
  PanelLeftClose,
  PanelLeftOpen,
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
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { hasPermission, isStaff, useMe, type CurrentUser } from "@/lib/auth/session";
import { useLocalPref } from "@/lib/use-local-pref";
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
  const [railPref, setRailPref] = useLocalPref("admin.nav.rail", "expanded");
  const rail = railPref === "collapsed";
  const sections = visible(me);

  return (
    <div className="flex min-h-dvh flex-col md:flex-row">
      <nav
        aria-label="Admin"
        className={cn(
          "dark sticky top-0 hidden h-dvh shrink-0 flex-col overflow-x-hidden overflow-y-auto bg-navy-950 py-5 text-foreground transition-[width] motion-reduce:transition-none md:flex",
          rail ? "w-16 px-2" : "w-64 px-4",
        )}
      >
        <div className={cn("flex items-center", rail ? "flex-col gap-3" : "justify-between")}>
          <Logo inverted markOnly={rail} />
          <button
            type="button"
            onClick={() => setRailPref(rail ? "expanded" : "collapsed")}
            aria-label={rail ? "Expand the menu" : "Collapse the menu"}
            aria-expanded={!rail}
            title={rail ? "Expand the menu" : "Collapse the menu"}
            className="rounded-md p-1.5 text-white/60 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-gold-400"
          >
            {rail ? <PanelLeftOpen className="size-5" /> : <PanelLeftClose className="size-5" />}
          </button>
        </div>
        <ActiveNavList sections={sections} rail={rail} />
        {me && (
          <div className={cn("mt-auto space-y-4 border-t border-white/10 pt-4", rail && "flex flex-col items-center")}>
            <Link
              href="/"
              aria-label={rail ? "View site" : undefined}
              title={rail ? "View site" : undefined}
              className="flex items-center gap-2 px-3 text-sm text-white/60 hover:text-white"
            >
              <ExternalLink className="size-4" /> {!rail && "View site"}
            </Link>
            <div
              className={cn("flex items-center gap-3", rail ? "justify-center" : "px-3")}
              title={rail ? me.name : undefined}
            >
              <InstructorAvatar name={me.name} className="size-9 text-xs" />
              {!rail && (
                <span className="min-w-0 text-sm">
                  <span className="block truncate font-semibold text-white">{me.name}</span>
                  <span className="block truncate text-xs text-white/50">
                    {me.roles.includes("super_admin")
                      ? "Super admin"
                      : me.roles.includes("admin")
                        ? "Administrator"
                        : "Staff"}
                  </span>
                </span>
              )}
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
function ActiveNavList(props: { sections: ReturnType<typeof visible>; onNavigate?: () => void; rail?: boolean }) {
  return (
    <Suspense fallback={<NavList {...props} pathname="" />}>
      <PathAwareNavList {...props} />
    </Suspense>
  );
}

function PathAwareNavList(props: { sections: ReturnType<typeof visible>; onNavigate?: () => void; rail?: boolean }) {
  return <NavList {...props} pathname={usePathname()} />;
}

/**
 * The first group (Overview) is always open. The others start folded and open one at a time:
 * opening a group folds whichever was open. The open group is remembered.
 */
function NavList({
  sections,
  pathname,
  onNavigate,
  rail = false,
}: {
  sections: ReturnType<typeof visible>;
  pathname: string;
  onNavigate?: () => void;
  rail?: boolean;
}) {
  const idBase = useId();
  const [openGroup, setOpenGroup] = useLocalPref("admin.nav.open", "");
  const isActive = (href: string) => (href === "/admin" ? pathname === href : pathname.startsWith(href));
  const toggle = (title: string) => setOpenGroup(openGroup === title ? "" : title);

  return (
    <div className={cn("mt-6", rail ? "space-y-3" : "space-y-4")}>
      {sections.map((g, index) => {
        const pinned = index === 0;
        const open = pinned || rail || openGroup === g.title;
        const listId = `${idBase}-${g.title.toLowerCase().replace(/[^a-z]+/g, "-")}`;
        return (
          <div key={g.title} className={cn(rail && index > 0 && "border-t border-white/10 pt-3")}>
            {!rail &&
              (pinned ? (
                <p className="mb-1.5 px-3 text-[0.65rem] font-bold tracking-[0.2em] text-white/40 uppercase">
                  {g.title}
                </p>
              ) : (
                <button
                  type="button"
                  onClick={() => toggle(g.title)}
                  aria-expanded={open}
                  aria-controls={listId}
                  className="mb-1.5 flex w-full items-center justify-between rounded-sm px-3 py-0.5 text-[0.65rem] font-bold tracking-[0.2em] text-white/40 uppercase hover:text-white/70 focus-visible:outline-2 focus-visible:outline-gold-400"
                >
                  {g.title}
                  <ChevronDown
                    className={cn("size-3.5 transition-transform motion-reduce:transition-none", !open && "-rotate-90")}
                    aria-hidden
                  />
                </button>
              ))}
            {open && (
              <ul id={listId} className="space-y-0.5" aria-label={rail ? g.title : undefined}>
                {g.items.map(({ href, label, icon: Icon }) => {
                  const active = isActive(href);
                  const link = (
                    <Link
                      href={href}
                      onClick={onNavigate}
                      aria-current={active ? "page" : undefined}
                      aria-label={rail ? label : undefined}
                      className={cn(
                        "flex items-center rounded-md text-sm",
                        rail ? "justify-center p-2.5" : "gap-3 px-3 py-2",
                        active
                          ? "bg-white/10 font-semibold text-white"
                          : "text-white/65 hover:bg-white/5 hover:text-white",
                      )}
                    />
                  );
                  return (
                    <li key={href}>
                      {rail ? (
                        <Tooltip>
                          <TooltipTrigger render={link}>
                            <Icon className={cn("size-4", active && "text-gold-400")} />
                          </TooltipTrigger>
                          <TooltipContent side="right">{label}</TooltipContent>
                        </Tooltip>
                      ) : (
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
                      )}
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        );
      })}
    </div>
  );
}
