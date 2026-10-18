import Link from "next/link";
import { Logo } from "@/components/brand/logo";

const columns = [
  {
    title: "Learn",
    links: ["Browse courses", "Development", "Finance & Accounting", "Design", "Data Science"],
  },
  { title: "Teach", links: ["Become an instructor", "Instructor guidelines", "Revenue & payouts", "Teaching help"] },
  { title: "Company", links: ["About Erudsway", "Careers", "Contact us", "Blog"] },
  { title: "Support", links: ["Help centre", "Refund policy", "Terms of use", "Privacy policy", "Grievance officer"] },
];

export function SiteFooter() {
  return (
    <footer className="dark mt-auto bg-navy-950 text-foreground">
      <div className="mx-auto grid max-w-[1340px] gap-10 px-6 pt-16 pb-10 md:grid-cols-[1.4fr_repeat(4,1fr)]">
        <div className="space-y-4">
          <Logo inverted />
          <p className="max-w-xs font-serif text-lg leading-snug text-white/80 italic">Knowledge that moves minds.</p>
        </div>
        {columns.map((col) => (
          <div key={col.title}>
            <h2 className="mb-4 font-sans text-xs font-bold tracking-[0.18em] text-gold-400 uppercase">{col.title}</h2>
            <ul className="space-y-2.5 text-sm text-white/70">
              {col.links.map((l) => (
                <li key={l}>
                  <Link href="#" className="hover:text-white">
                    {l}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <div className="border-t border-white/10">
        <div className="mx-auto flex max-w-[1340px] flex-wrap items-center justify-between gap-3 px-6 py-6 text-xs text-white/50">
          <p>© 2026 Erudsway Educorp LLP. All rights reserved.</p>
          <p>Prices in INR, inclusive of GST.</p>
        </div>
      </div>
    </footer>
  );
}
