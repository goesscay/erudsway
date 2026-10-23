import Link from "next/link";
import { Logo } from "@/components/brand/logo";

const columns: { title: string; links: [string, string][] }[] = [
  {
    title: "Learn",
    links: [
      ["All courses", "/courses"],
      ["GST Simulator — free trial", "/gst-simulator"],
      ["GST", "/courses/gst"],
      ["Income Tax", "/courses/income-tax"],
      ["Audit & Assurance", "/courses/audit-assurance"],
      ["ROC & Company Law", "/courses/roc-company-law"],
    ],
  },
  {
    title: "Teach",
    links: [
      ["Become an instructor", "/teach"],
      ["Instructor guidelines", "/teach"],
      ["Revenue & payouts", "/teach"],
    ],
  },
  {
    title: "Company",
    links: [
      ["About Erudsway", "/about"],
      ["Contact us", "/contact"],
    ],
  },
  {
    title: "Support",
    links: [
      ["Help centre", "/help"],
      ["Terms & Conditions", "/policies#terms"],
      ["Privacy policy", "/policies#privacy"],
      ["Refund & cancellation", "/policies#refund"],
      ["Grievances", "/policies#grievance"],
    ],
  },
];

export function SiteFooter() {
  return (
    <footer className="dark mt-auto bg-navy-950 text-foreground">
      <div className="mx-auto grid max-w-[1340px] gap-10 px-6 pt-16 pb-10 md:grid-cols-[1.4fr_repeat(4,1fr)]">
        <div className="space-y-4">
          <Logo inverted />
          <p className="max-w-xs font-serif text-lg leading-snug text-white/80 italic">Knowledge that moves minds.</p>
          <p className="max-w-xs text-sm text-white/55">
            Tax, audit and compliance courses taught by practising professionals.
          </p>
        </div>
        {columns.map((col) => (
          <div key={col.title}>
            <h2 className="mb-4 font-sans text-xs font-bold tracking-[0.18em] text-gold-400 uppercase">{col.title}</h2>
            <ul className="space-y-2.5 text-sm text-white/70">
              {col.links.map(([label, href]) => (
                <li key={label}>
                  <Link href={href} className="hover:text-white">
                    {label}
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
          <p>Prices in INR, inclusive of GST. Courses are for education and are not professional advice.</p>
        </div>
      </div>
    </footer>
  );
}
