"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { LogOut, Settings, ShieldCheck, ShoppingCart, MonitorPlay, Presentation } from "lucide-react";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { buttonVariants } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import { isStaff, useMe, useSession } from "@/lib/auth/session";
import { cn } from "@/lib/utils";

export function AuthNav() {
  const { data: me, isPending } = useMe();
  const session = useSession();
  const router = useRouter();

  const cart = (
    <Link href="/cart" aria-label="Cart" className={cn(buttonVariants({ variant: "ghost", size: "icon" }), "text-stone-700")}>
      <ShoppingCart className="size-5" />
    </Link>
  );

  if (isPending) {
    return (
      <div className="flex items-center gap-2">
        {cart}
        <Skeleton className="h-10 w-40 rounded-md" />
      </div>
    );
  }

  if (!me) {
    return (
      <>
        <Link href="/teach" className="hidden px-3 py-2 text-stone-700 hover:text-navy-900 lg:block">
          Teach on Erudsway
        </Link>
        {cart}
        <Link href="/login" className={cn(buttonVariants({ variant: "outline" }), "ml-1")}>
          Log in
        </Link>
        <Link href="/register" className={cn(buttonVariants(), "hidden sm:inline-flex")}>
          Sign up
        </Link>
      </>
    );
  }

  const instructor = !!me.instructor;

  return (
    <>
      <Link
        href={instructor ? "/instructor" : "/teach"}
        className="hidden px-3 py-2 text-stone-700 hover:text-navy-900 lg:block"
      >
        {instructor ? "Instructor" : "Teach on Erudsway"}
      </Link>
      <Link href="/my-learning" className="hidden px-3 py-2 text-stone-700 hover:text-navy-900 lg:block">
        My learning
      </Link>
      {cart}
      <DropdownMenu>
        <DropdownMenuTrigger
          aria-label="Account menu"
          className="ml-1 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-gold-500 focus-visible:ring-offset-2"
        >
          <InstructorAvatar name={me.name} className="size-10 text-sm" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-64">
          <DropdownMenuGroup>
            <DropdownMenuLabel className="flex items-center gap-3 py-2.5">
              <InstructorAvatar name={me.name} className="size-11 text-sm" />
              <span className="min-w-0">
                <span className="block truncate font-semibold text-ink">{me.name}</span>
                <span className="block truncate text-xs font-normal text-stone-500">{me.email}</span>
              </span>
            </DropdownMenuLabel>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuItem render={<Link href="/my-learning" />}>
            <MonitorPlay /> My learning
          </DropdownMenuItem>
          <DropdownMenuItem render={<Link href={instructor ? "/instructor" : "/teach"} />}>
            <Presentation /> {instructor ? "Instructor dashboard" : "Teach on Erudsway"}
          </DropdownMenuItem>
          <DropdownMenuItem render={<Link href="/account" />}>
            <Settings /> Account settings
          </DropdownMenuItem>
          {isStaff(me) && (
            <DropdownMenuItem render={<Link href="/admin" />}>
              <ShieldCheck /> Admin console
            </DropdownMenuItem>
          )}
          <DropdownMenuSeparator />
          <DropdownMenuItem
            onClick={async () => {
              await session.logout();
              router.replace("/");
            }}
          >
            <LogOut /> Log out
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  );
}

/** Thin bar shown site-wide until the signed-in user verifies their email. */
export function VerifyEmailBanner() {
  const { data: me } = useMe();
  if (!me || me.email_verified) return null;

  return (
    <div className="border-b border-gold-200 bg-gold-50 px-6 py-2.5 text-center text-sm text-stone-700">
      Please verify <strong className="text-ink">{me.email}</strong> to purchase courses and apply to teach.{" "}
      <Link href="/verify-email" className="font-semibold text-navy-800 underline underline-offset-2">
        Resend link
      </Link>
    </div>
  );
}
