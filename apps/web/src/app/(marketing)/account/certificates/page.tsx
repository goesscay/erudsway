import type { Metadata } from "next";
import { MyCertificates } from "./my-certificates";

export const metadata: Metadata = { title: "My certificates", robots: { index: false } };

export default function CertificatesPage() {
  return <MyCertificates />;
}
