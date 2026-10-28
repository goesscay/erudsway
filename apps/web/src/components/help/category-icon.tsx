import {
  Award,
  BookOpen,
  Calculator,
  CreditCard,
  FileSpreadsheet,
  Headset,
  Presentation,
  UserRound,
} from "lucide-react";
import type { HelpCategory } from "@/lib/help/content";

const icons: Record<HelpCategory, typeof UserRound> = {
  account: UserRound,
  courses: BookOpen,
  gst_simulator: Calculator,
  tds_simulator: FileSpreadsheet,
  payments: CreditCard,
  certificates: Award,
  faculty: Presentation,
  technical: Headset,
};

export function CategoryIcon({ category, className }: { category: HelpCategory; className?: string }) {
  const Icon = icons[category];
  return <Icon className={className} aria-hidden />;
}
