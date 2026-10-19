"use client";

import { useParams, useRouter } from "next/navigation";
import { useEffect } from "react";

/** /instructor/courses/{id} opens the curriculum step. */
export default function CourseBuilderIndex() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();

  useEffect(() => {
    router.replace(`/instructor/courses/${id}/curriculum`);
  }, [id, router]);

  return null;
}
