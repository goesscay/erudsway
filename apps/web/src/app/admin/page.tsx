"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { hasPermission, useMe } from "@/lib/auth/session";

/** Sends staff to the first section they can use. */
export default function AdminHome() {
  const { data: me } = useMe();
  const router = useRouter();

  useEffect(() => {
    if (!me) return;
    router.replace(
      hasPermission(me, "moderate_courses")
        ? "/admin/course-reviews"
        : hasPermission(me, "review_instructor_applications")
          ? "/admin/instructor-applications"
          : hasPermission(me, "manage_users")
            ? "/admin/users"
            : "/admin/orders",
    );
  }, [me, router]);

  return null;
}
