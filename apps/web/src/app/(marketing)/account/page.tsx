import type { Metadata } from "next";
import { AccountSettings } from "./account-settings";

export const metadata: Metadata = { title: "Account settings", robots: { index: false } };

export default function AccountPage() {
  return <AccountSettings />;
}
