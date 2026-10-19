import type { Metadata } from "next";
import { PurchaseHistory } from "./purchase-history";

export const metadata: Metadata = { title: "Purchase history", robots: { index: false } };

export default function PurchasesPage() {
  return <PurchaseHistory />;
}
