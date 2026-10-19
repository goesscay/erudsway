import type { Metadata } from "next";
import { PayoutDetails } from "./payout-details";

export const metadata: Metadata = { title: "Payout details" };

export default function PayoutDetailsPage() {
  return <PayoutDetails />;
}
