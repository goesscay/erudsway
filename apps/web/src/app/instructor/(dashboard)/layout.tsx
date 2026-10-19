import { InstructorDashboardShell } from "../instructor-shell";

export default function InstructorDashboardLayout({ children }: { children: React.ReactNode }) {
  return <InstructorDashboardShell>{children}</InstructorDashboardShell>;
}
