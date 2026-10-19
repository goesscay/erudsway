"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { BookCheck, ClipboardCheck, ExternalLink, ReceiptText, RotateCcw, TicketPercent, Users } from "lucide-react";
import { Logo } from "@/components/brand/logo";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { hasPermission, useMe } from "@/lib/auth/session";
import { cn } from "@/lib/utils";

const nav = [
  {
    href: "/admin/instructor-applications",
    label: "Instructor applications",
    icon: ClipboardCheck,
    permission: "review_instructor_applications",
  },
  { href: "/admin/course-reviews", label: "Course reviews", icon: BookCheck, permission: "moderate_courses" },
  { href: "/admin/users", label: "Users", icon: Users, permission: "manage_users" },
  { href: "/admin/orders", label: "Orders", icon: ReceiptText, permission: "manage_finance" },
  { href: "/admin/refunds", label: "Refunds", icon: RotateCcw, permission: "manage_finance" },
  { href: "/admin/coupons", label: "Coupons", icon: TicketPercent, permission: "manage_finance" },
];

/**
 * Chrome for the admin area. Always renders its children: each admin page carries its own
 * permission gate (and the API enforces everything), so the shell only adapts its navigation.
 */
export function AdminShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { data: me } = useMe();

  return (
    <div className="flex min-h-dvh">
      <nav
        aria-label="Admin"
        className="dark sticky top-0 hidden h-dvh w-64 shrink-0 flex-col bg-navy-950 px-4 py-5 text-foreground md:flex"
      >
        <Logo inverted />
        <p className="mt-8 mb-2 px-3 text-[0.65rem] font-bold tracking-[0.2em] text-white/40 uppercase">
          Admin console
        </p>
        <ul className="space-y-0.5">
          {nav
            .filter((item) => !me || hasPermission(me, item.permission))
            .map(({ href, label, icon: Icon }) => {
              const active = pathname.startsWith(href);
              return (
                <li key={href}>
                  <Link
                    href={href}
                    aria-current={active ? "page" : undefined}
                    className={cn(
                      "flex items-center gap-3 rounded-md px-3 py-2.5 text-sm",
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
      <main className="min-w-0 flex-1 bg-paper">{children}</main>
    </div>
  );
}
