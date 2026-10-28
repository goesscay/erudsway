import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  Calculator,
  FileSpreadsheet,
  Info,
  Mail,
  MessageCircle,
  Phone,
  Presentation,
  ShieldCheck,
  Ticket,
  type LucideIcon,
} from "lucide-react";
import { SectionHeading } from "@/components/brand/section-heading";
import { HelpSearch } from "@/components/help/help-search";
import { KnowledgeBase } from "@/components/help/knowledge-base";
import { SupportActions } from "@/components/help/support-actions";
import { PHONE, SUPPORT_EMAIL, WHATSAPP_HREF } from "@/lib/contact";
import { helpFaqs } from "@/lib/help/content";

export const metadata: Metadata = {
  title: "Help Centre — answers and support",
  description:
    "Answers about your Erudsway account, courses, the GST Simulator, payments, refunds and certificates — or raise a support ticket with our team.",
};

const guides: {
  icon: LucideIcon;
  eyebrow: string;
  title: string;
  text: string;
  steps: string[];
  href: string;
  cta: string;
}[] = [
  {
    icon: Calculator,
    eyebrow: "Available now",
    title: "GST Simulator",
    text: "Your own practice GSTIN on a GST portal simulator, free for 7 days.",
    steps: [
      "Start the free trial from the GST Simulator page.",
      "Enter the period’s sales invoices in GSTR-1.",
      "Act on supplier invoices in IMS and review GSTR-2B.",
      "Prepare GSTR-3B and pay with a practice challan.",
      "Choose a 3- or 12-month plan to keep your login and work.",
    ],
    href: "/help/gstr1-to-gstr3b",
    cta: "Read the GST guide",
  },
  {
    icon: FileSpreadsheet,
    eyebrow: "Coming soon",
    title: "TDS Simulator",
    text: "The TDS Simulator isn’t open yet. Until it is, TDS courses cover the full workflow.",
    steps: [
      "Open your TDS course from My Learning.",
      "Work through deduction, challan and statement lessons.",
      "Practise with the working files attached to each lesson.",
      "We’ll announce the simulator on the website when it opens.",
    ],
    href: "/help/tds-learning",
    cta: "Read the TDS guide",
  },
];

const promises = [
  {
    icon: Ticket,
    title: "One ticket per issue",
    text: "Each ticket gets its own reference, and you can follow it under My tickets.",
  },
  {
    icon: ShieldCheck,
    title: "Keep secrets out",
    text: "Never include passwords, OTPs, card numbers, UPI PINs or GST portal credentials.",
  },
  {
    icon: Mail,
    title: "Replies by email",
    text: "We email you when our team replies, and you can answer on the ticket.",
  },
];

export default function HelpPage() {
  return (
    <>
      {/* Hero */}
      <section className="dark relative overflow-hidden bg-navy-950 text-foreground" aria-labelledby="help-title">
        <div
          aria-hidden
          className="pointer-events-none absolute -top-48 -right-40 size-[700px] rounded-full border border-gold-400/15 shadow-[0_0_0_90px_rgba(255,255,255,0.018),0_0_0_190px_rgba(255,255,255,0.012)]"
        />
        <div className="relative mx-auto max-w-[1340px] px-4 py-14 sm:px-6 lg:py-20">
          <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">Erudsway Help Centre</p>
          <h1 id="help-title" className="text-[2.5rem] leading-[1.05] tracking-[-0.03em] sm:text-[3.4rem]">
            How can we <span className="text-gold-400">help?</span>
          </h1>
          <p className="mt-5 mb-8 max-w-[56ch] text-lg leading-relaxed text-white/80">
            Quick answers and step-by-step guides for your account, courses, the GST Simulator, payments and
            certificates.
          </p>
          <HelpSearch />
        </div>
      </section>

      <KnowledgeBase />

      {/* Simulator guides */}
      <section aria-labelledby="guides-title" className="mx-auto max-w-[1340px] px-4 py-14 sm:px-6">
        <SectionHeading eyebrow="Practical learning" title="Simulator guides">
          A suggested path through each simulator, so you know what to practise first.
        </SectionHeading>
        <h2 id="guides-title" className="sr-only">
          Simulator guides
        </h2>
        <div className="mt-8 grid gap-6 lg:grid-cols-2">
          {guides.map((g) => (
            <article key={g.title} className="flex flex-col rounded-md border border-line bg-white p-6">
              <div className="flex items-center gap-3">
                <span className="grid size-11 place-items-center rounded-md bg-navy-100 text-navy-800">
                  <g.icon className="size-5" aria-hidden />
                </span>
                <div>
                  <p className="text-xs font-bold tracking-wide text-gold-600 uppercase">{g.eyebrow}</p>
                  <h3 className="text-h3">{g.title}</h3>
                </div>
              </div>
              <p className="mt-3 text-stone-600">{g.text}</p>
              <ol className="mt-5 flex-1 space-y-3">
                {g.steps.map((s, i) => (
                  <li key={s} className="flex gap-3 text-sm text-stone-800">
                    <span className="grid size-6 shrink-0 place-items-center rounded-full bg-navy-900 text-xs font-bold text-white">
                      {i + 1}
                    </span>
                    <span className="pt-0.5">{s}</span>
                  </li>
                ))}
              </ol>
              <Link
                href={g.href}
                className="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-navy-800 hover:underline"
              >
                {g.cta} <ArrowRight className="size-4" aria-hidden />
              </Link>
            </article>
          ))}
        </div>
        <p className="mt-6 flex gap-2 rounded-md bg-gold-50 px-4 py-3 text-sm text-stone-800">
          <Info className="mt-0.5 size-4 shrink-0 text-gold-600" aria-hidden />
          <span>
            <strong>Training environment.</strong> Erudsway simulators are for practice. Nothing you do in them files a
            return, pays tax or reaches a government portal.
          </span>
        </p>
      </section>

      {/* FAQs */}
      <section id="faqs" aria-labelledby="faqs-title" className="scroll-mt-24 border-y border-line bg-paper-2/60">
        <div className="mx-auto grid max-w-[1340px] gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_2fr]">
          <div>
            <h2 id="faqs-title" className="text-h2">
              Frequently asked questions
            </h2>
            <p className="mt-3 text-stone-600">
              Short answers to common questions. Can’t find yours? Raise a ticket below.
            </p>
          </div>
          <div className="divide-y divide-line rounded-md border border-line bg-white">
            {helpFaqs.map(([q, a]) => (
              <details key={q} className="group px-5 py-4">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                  {q}
                  <span aria-hidden className="text-xl leading-none text-gold-600 transition group-open:rotate-45">
                    +
                  </span>
                </summary>
                <p className="mt-2 text-sm leading-relaxed text-stone-700">{a}</p>
              </details>
            ))}
          </div>
        </div>
      </section>

      {/* Support */}
      <section id="support" aria-labelledby="support-title" className="scroll-mt-24">
        <div className="mx-auto grid max-w-[1340px] gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2">
          <div>
            <SectionHeading eyebrow="Personal support" title="Can’t find the answer? Raise a ticket.">
              Tell us what happened, which course or simulator screen it affects and what you’ve already tried. Attach
              screenshots if they help.
            </SectionHeading>
            <h2 id="support-title" className="sr-only">
              Raise a support ticket
            </h2>
            <div className="mt-8">
              <SupportActions />
            </div>
          </div>
          <ul className="grid gap-4">
            {promises.map((p) => (
              <li key={p.title} className="flex gap-4 rounded-md border border-line bg-white p-5">
                <p.icon className="mt-0.5 size-5 shrink-0 text-gold-600" aria-hidden />
                <div>
                  <p className="font-semibold text-ink">{p.title}</p>
                  <p className="mt-1 text-sm text-stone-600">{p.text}</p>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* More ways */}
      <section aria-labelledby="more-title" className="border-t border-line bg-paper-2/60">
        <div className="mx-auto max-w-[1340px] px-4 py-14 sm:px-6">
          <h2 id="more-title" className="text-h2">
            More ways to get help
          </h2>
          <ul className="mt-6 grid gap-4 md:grid-cols-3">
            <li className="rounded-md border border-line bg-white p-5">
              <Mail className="size-5 text-gold-600" aria-hidden />
              <p className="mt-3 font-semibold text-ink">Email</p>
              <a href={`mailto:${SUPPORT_EMAIL}`} className="mt-1 block text-sm text-navy-800 hover:underline">
                {SUPPORT_EMAIL}
              </a>
            </li>
            <li className="rounded-md border border-line bg-white p-5">
              <MessageCircle className="size-5 text-gold-600" aria-hidden />
              <p className="mt-3 font-semibold text-ink">Phone or WhatsApp</p>
              <p className="mt-1 flex flex-wrap gap-x-3 text-sm">
                <a
                  href={`tel:${PHONE.replace(/\s/g, "")}`}
                  className="inline-flex items-center gap-1 text-navy-800 hover:underline"
                >
                  <Phone className="size-3.5" aria-hidden /> {PHONE}
                </a>
                <a
                  href={WHATSAPP_HREF}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-navy-800 hover:underline"
                >
                  WhatsApp
                </a>
              </p>
            </li>
            <li className="rounded-md border border-line bg-white p-5">
              <Presentation className="size-5 text-gold-600" aria-hidden />
              <p className="mt-3 font-semibold text-ink">Instructors</p>
              <p className="mt-1 text-sm text-stone-600">
                Questions about teaching, course review or payouts?{" "}
                <Link href="/teach" className="font-semibold text-navy-800 hover:underline">
                  Teach on Erudsway
                </Link>
              </p>
            </li>
          </ul>
        </div>
      </section>
    </>
  );
}
