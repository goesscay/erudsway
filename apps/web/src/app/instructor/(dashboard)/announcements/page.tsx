import type { Metadata } from "next";
import { AnnouncementsView } from "./announcements-view";

export const metadata: Metadata = { title: "Announcements" };

export default function InstructorAnnouncementsPage() {
  return <AnnouncementsView />;
}
