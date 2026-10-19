"use client";

import Link from "next/link";
import { Heart } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { CourseCard } from "@/components/course/course-card";
import { FormAlert } from "@/components/forms/field";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useWishlist, useWishlistToggle } from "@/lib/commerce/api";

export function WishlistView() {
  return (
    <RequireAuth>
      {() => (
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <h1 className="text-h1">Wishlist</h1>
          <Saved />
        </div>
      )}
    </RequireAuth>
  );
}

function Saved() {
  const list = useWishlist(true);
  const toggle = useWishlistToggle();

  if (list.isPending) return <Skeleton className="mt-8 h-56 w-full" />;
  if (list.isError) return <div className="mt-8"><FormAlert>We couldn&rsquo;t load your wishlist.</FormAlert></div>;
  if (list.data.length === 0) {
    return (
      <div className="mt-8 rounded-md border border-line bg-white px-6 py-16 text-center">
        <Heart className="mx-auto size-10 text-gold-600" strokeWidth={1.5} />
        <p className="mt-4 font-semibold text-ink">Nothing saved yet</p>
        <p className="mt-1 text-sm text-stone-500">Tap the heart on a course page to keep it for later.</p>
        <Link href="/courses" className={buttonVariants({ className: "mt-6" })}>
          Browse courses
        </Link>
      </div>
    );
  }

  return (
    <ul className="mt-8 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
      {list.data.map((course) => (
        <li key={course.id}>
          <CourseCard course={course} />
          <button
            type="button"
            onClick={() => toggle.mutate({ slug: course.slug, saved: true })}
            className="mt-2 text-xs font-semibold text-navy-800 hover:underline"
          >
            Remove from wishlist
          </button>
        </li>
      ))}
    </ul>
  );
}
