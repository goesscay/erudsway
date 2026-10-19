import type { Metadata } from "next";
import { InstructorGate } from "./instructor-shell";

export const metadata: Metadata = { title: { default: "Instructor", template: "%s · Erudsway Instructor" }, robots: { index: false } };

export default function InstructorLayout({ children }: LayoutProps<"/instructor">) {
  return <InstructorGate>{children}</InstructorGate>;
}
