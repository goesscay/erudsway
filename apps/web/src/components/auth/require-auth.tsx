"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import { ShieldAlert } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { hasPermission, isStaff, useMe, type CurrentUser } from "@/lib/auth/session";

/**
 * Client-side gate for signed-in areas. This only shapes the UI — the API enforces
 * every permission independently, so nothing sensitive relies on this check.
 */
export function RequireAuth({
  permission,
  staff,
  instructor,
  children,
}: {
  /** Require a specific permission (e.g. "review_instructor_applications"). */
  permission?: string;
  /** Require any staff permission. */
  staff?: boolean;
  /** Require an approved instructor. */
  instructor?: boolean;
  children: (user: CurrentUser) => React.ReactNode;
}) {
  const { data: me, isPending, isError } = useMe();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (!isPending && me === null) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [isPending, me, pathname, router]);

  if (isError) {
    return <Notice title="We couldn't load your account" body="Check your connection and refresh the page." />;
  }

  if (isPending || !me) {
    return (
      <div className="mx-auto max-w-4xl space-y-4 px-6 py-16" aria-busy>
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-4 w-96" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if ((permission && !hasPermission(me, permission)) || (staff && !isStaff(me))) {
    return <Notice title="You don't have access to this page" body="Ask an administrator if you think you should." />;
  }

  if (instructor && !me.instructor) {
    return (
      <Notice
        title="This area is for approved instructors"
        body="Apply to teach on Erudsway — once approved, you can build and publish courses here."
        href="/teach"
        cta="Apply to teach"
      />
    );
  }

  return <>{children(me)}</>;
}

function Notice({ title, body, href = "/", cta = "Back to home" }: { title: string; body: string; href?: string; cta?: string }) {
  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
      <ShieldAlert className="size-10 text-gold-600" strokeWidth={1.5} />
      <h1 className="text-h3 mt-4">{title}</h1>
      <p className="mt-2 text-sm text-stone-700">{body}</p>
      <Link href={href} className={buttonVariants({ variant: "outline", className: "mt-6" })}>
        {cta}
      </Link>
    </div>
  );
}
