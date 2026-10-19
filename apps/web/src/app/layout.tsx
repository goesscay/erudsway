import type { Metadata } from "next";
import { Figtree, Fraunces } from "next/font/google";
import { Providers } from "./providers";
import "./globals.css";

const figtree = Figtree({
  variable: "--font-figtree",
  subsets: ["latin"],
  display: "swap",
});

const fraunces = Fraunces({
  variable: "--font-fraunces",
  subsets: ["latin"],
  // Optical sizing only: SOFT and WONK are pinned to their defaults in globals.css, so loading
  // those axes only added weight.
  axes: ["opsz"],
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "Erudsway — Knowledge That Moves Minds",
    template: "%s · Erudsway",
  },
  description:
    "Learn GST, income tax, TDS, audit, company law, FEMA and capital markets from practising CAs, CSs and advocates.",
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000"),
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en-IN" className={`${figtree.variable} ${fraunces.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
