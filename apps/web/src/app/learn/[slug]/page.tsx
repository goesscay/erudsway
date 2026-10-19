import type { Metadata } from "next";
import { Suspense } from "react";
import { CoursePlayer, PlayerSkeleton } from "@/components/player/course-player";

export const metadata: Metadata = { title: "Learning", robots: { index: false } };

/**
 * The course player. Everything here is per learner (access, progress, signed playback
 * URLs), so it renders on the client behind a Suspense boundary for the URL data.
 */
export default function LearnPage() {
  return (
    <Suspense fallback={<PlayerSkeleton />}>
      <CoursePlayer />
    </Suspense>
  );
}
