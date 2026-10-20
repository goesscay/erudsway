"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { ChevronDown, ExternalLink, LogOut, MonitorPlay, Settings, UserRound } from "lucide-react";
import { PasswordForm, ProfileForm } from "@/app/(marketing)/account/account-settings";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { useMe, useSession, type CurrentUser } from "@/lib/auth/session";
import { cn } from "@/lib/utils";

export function staffRoleLabel(me: CurrentUser) {
  if (me.roles.includes("super_admin")) return "Super admin";
  if (me.roles.includes("admin")) return "Administrator";
  return "Staff";
}

/** Top-right of the admin header: who is signed in, their profile, and log out. */
export function AdminAccountMenu({ compact = false }: { compact?: boolean }) {
  const { data: me, isPending } = useMe();
  const session = useSession();
  const router = useRouter();
  const [profileOpen, setProfileOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  if (isPending) return <Skeleton className={cn("h-10 rounded-full", compact ? "w-10" : "w-44")} />;
  if (!me) return null;

  const role = staffRoleLabel(me);

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger
          aria-label={`Account menu for ${me.name}`}
          className={cn(
            "flex items-center gap-2.5 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-gold-500 focus-visible:ring-offset-2",
            compact ? "" : "py-1 pr-2 pl-1 hover:bg-paper-2",
          )}
        >
          <InstructorAvatar name={me.name} className="size-9 text-xs" />
          {!compact && (
            <>
              <span className="hidden min-w-0 text-left sm:block">
                <span className="block max-w-40 truncate text-sm font-semibold text-ink">{me.name}</span>
                <span className="block text-xs text-stone-500">{role}</span>
              </span>
              <ChevronDown className="size-4 text-stone-500" aria-hidden />
            </>
          )}
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-64">
          <DropdownMenuGroup>
            <DropdownMenuLabel className="flex items-center gap-3 py-2.5">
              <InstructorAvatar name={me.name} className="size-11 text-sm" />
              <span className="min-w-0">
                <span className="block truncate font-semibold text-ink">{me.name}</span>
                <span className="block truncate text-xs font-normal text-stone-500">{me.email}</span>
                <span className="mt-0.5 block text-xs font-semibold text-gold-600">{role}</span>
              </span>
            </DropdownMenuLabel>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => setProfileOpen(true)}>
            <UserRound /> My profile
          </DropdownMenuItem>
          <DropdownMenuItem render={<Link href="/account" />}>
            <Settings /> Account settings
          </DropdownMenuItem>
          <DropdownMenuItem render={<Link href="/my-learning" />}>
            <MonitorPlay /> My learning
          </DropdownMenuItem>
          <DropdownMenuItem render={<Link href="/" />}>
            <ExternalLink /> View site
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            disabled={loggingOut}
            onClick={async () => {
              setLoggingOut(true);
              try {
                await session.logout();
                router.replace("/login");
              } finally {
                setLoggingOut(false);
              }
            }}
          >
            <LogOut /> {loggingOut ? "Logging out…" : "Log out"}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Sheet open={profileOpen} onOpenChange={setProfileOpen}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-md">
          <SheetHeader className="border-b border-line">
            <SheetTitle>My profile</SheetTitle>
            <SheetDescription>
              {me.email} · {role}
            </SheetDescription>
          </SheetHeader>
          <div className="space-y-8 px-4 pb-8">
            <section aria-labelledby="admin-profile-heading">
              <h3 id="admin-profile-heading" className="mb-3 font-sans text-base font-bold text-ink">
                Details
              </h3>
              <ProfileForm me={me} />
            </section>
            <section aria-labelledby="admin-password-heading" className="border-t border-line pt-6">
              <h3 id="admin-password-heading" className="mb-3 font-sans text-base font-bold text-ink">
                Password
              </h3>
              <PasswordForm />
            </section>
            <p className="text-xs text-stone-500">
              To change your email, ask another admin. Notification choices are in{" "}
              <Link href="/account#notifications" className="font-semibold text-navy-800 hover:underline">
                Account settings
              </Link>
              .
            </p>
          </div>
        </SheetContent>
      </Sheet>
    </>
  );
}
