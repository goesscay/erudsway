import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import {
  ArrowRight,
  BadgeCheck,
  ChevronDown,
  CircleCheck,
  EyeOff,
  FileText,
  IndianRupee,
  Megaphone,
  MicOff,
  MonitorPlay,
  Presentation,
  ShieldCheck,
  Sparkles,
} from "lucide-react";
import { SectionHeading } from "@/components/brand/section-heading";
import { Skeleton } from "@/components/ui/skeleton";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ApplySection } from "./apply-section";

export const metadata: Metadata = {
  title: "Teach on Erudsway",
  description:
    "Turn real tax, audit, finance or compliance work into practical Tamil courses. You bring the professional insight; Erudsway produces, hosts and markets the course. Free to apply, no camera needed.",
};

const assurances = [
  { icon: IndianRupee, text: "Free to apply" },
  { icon: MicOff, text: "No camera required" },
  { icon: EyeOff, text: "Private identity option" },
];

const model = [
  { step: "You bring", title: "Real professional work", detail: "Workings, procedures, portal steps, cases" },
  { step: "We build", title: "A polished practical course", detail: "Editing, structure, branding, hosting" },
  { step: "Together", title: "Learning with wider reach", detail: "Marketing, learner access, updates" },
];

const split = {
  you: [
    "Professional knowledge and experience",
    "Voice, slides, portal walk-through or screen recording",
    "Excel workings, checklists and templates",
    "Anonymised documents, cases and notes",
  ],
  us: [
    "Editing, course structure and visual branding",
    "Hosting, learner access and technical support",
    "Payments, GST invoices and course administration",
    "Marketing, promotions and learner support",
  ],
};

const steps = [
  ["Share your idea", "Your basic profile and a practical topic. No documents at this stage."],
  ["Private review", "If shortlisted, we check credentials, experience and a sample explanation — privately."],
  ["Agree and record", "Syllabus, scope, resources, rights, terms and timeline — then recording begins."],
] as const;

const formats = [
  {
    icon: FileText,
    name: "Content pack",
    detail: "PPT, notes, workings, checklists and cases — we develop the final production.",
  },
  {
    icon: Presentation,
    name: "Practical recording",
    detail: "Your voice over slides, the portal, Excel or documents — we edit.",
  },
  {
    icon: MonitorPlay,
    name: "Complete course",
    detail: "Structured recorded modules with resources, supplied by you.",
  },
  {
    icon: BadgeCheck,
    name: "Publish-ready course",
    detail: "Professionally edited content that meets our specification.",
  },
];

const earning = [
  "Fixed project fee",
  "Performance-linked share",
  "Outright content acquisition",
  "Hybrid arrangement",
  "Specialist package",
];

/** Topics in demand; the label tells contributors how often the course would need updating. */
const topics: { name: string; tag: "High demand" | "Specialist" | "Evergreen" | "Regular updates" }[] = [
  { name: "Income-tax Act 2025 Practical", tag: "High demand" },
  { name: "Income-tax Rules 2026", tag: "Specialist" },
  { name: "Tax Planning", tag: "Evergreen" },
  { name: "Capital Gains", tag: "Regular updates" },
  { name: "NRI Taxation", tag: "High demand" },
  { name: "Income-tax Appeals", tag: "Specialist" },
  { name: "GST Appeals", tag: "Evergreen" },
  { name: "GST Reconciliation", tag: "Regular updates" },
  { name: "TDS / TRACES", tag: "High demand" },
  { name: "Company Incorporation", tag: "Specialist" },
  { name: "ROC Filings", tag: "Evergreen" },
  { name: "NGO Taxation", tag: "Regular updates" },
  { name: "FCRA", tag: "High demand" },
  { name: "CSR-1", tag: "Specialist" },
  { name: "Factory Setup", tag: "Evergreen" },
  { name: "Labour Codes", tag: "Regular updates" },
  { name: "Forensic Audit", tag: "High demand" },
  { name: "Internal Audit", tag: "Specialist" },
  { name: "Financial Planning", tag: "Evergreen" },
  { name: "Project Finance", tag: "Regular updates" },
  { name: "Valuation", tag: "High demand" },
  { name: "FEMA", tag: "Specialist" },
  { name: "Excel / Power BI", tag: "Evergreen" },
];

const tagStyle: Record<(typeof topics)[number]["tag"], string> = {
  "High demand": "bg-gold-50 text-gold-600 ring-gold-200",
  Specialist: "bg-navy-100 text-navy-800 ring-navy-900/10",
  Evergreen: "bg-[#e7f1ec] text-success ring-[#cfe3d8]",
  "Regular updates": "bg-paper-2 text-stone-700 ring-line",
};

const lessonFlow = [
  "Concept",
  "When it applies",
  "Law, rule or form",
  "Documents",
  "Practical working",
  "Portal or procedure",
  "Common mistakes",
  "Checklist",
  "Practical case",
  "Downloadable resource",
];

const checks = [
  ["Practical value", "Can learners perform the work?"],
  ["Accuracy", "Is the law and procedure current?"],
  ["Portal", "Does the demonstration match today’s process?"],
  ["Materials", "Are workings, checklists and templates useful?"],
  ["Confidentiality", "Has client data been removed or anonymised?"],
  ["Originality", "Is the content original or properly licensed?"],
] as const;

const faqs = [
  [
    "Do I need to appear on camera?",
    "No. Voice with slides, screen or portal recordings, workings and document walk-throughs are all welcome.",
  ],
  [
    "Will my name or contact details appear publicly?",
    "Only if you want them to. A course can be published as Erudsway Verified Practical Content; we verify you privately, and no phone number, email, address or firm details are shown unless both sides agree.",
  ],
  [
    "How am I paid?",
    "By a fixed fee, a performance-linked share, outright acquisition, a hybrid or a specialist package — agreed in writing after we review the scope. Sales and earnings aren’t guaranteed.",
  ],
  [
    "Can I teach more than one subject?",
    "Yes. Approved contributors can build a series, update existing courses, lead advanced sessions or develop corporate training.",
  ],
  [
    "When do you need my documents?",
    "Only after shortlisting. Credentials, experience, originality and identity are then checked privately — never at the first step.",
  ],
  [
    "Can I reserve a topic?",
    "Selected high-demand topics can be reserved for an approved contributor for a limited development period, on agreed terms.",
  ],
] as const;

export default function TeachPage() {
  return (
    <>
      {/* Hero: the promise, the three assurances and the model at a glance */}
      <section className="dark bg-navy-950 text-foreground" aria-labelledby="teach-title">
        <div className="mx-auto grid max-w-[1340px] items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:py-16">
          <div>
            <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">
              For practising professionals and subject experts
            </p>
            <h1
              id="teach-title"
              className="max-w-[18ch] text-[2.3rem] leading-[1.08] tracking-[-0.02em] sm:text-[3rem]"
            >
              Your expertise. A bigger classroom.
            </h1>
            <p className="mt-5 max-w-[54ch] text-lg leading-relaxed text-white/80">
              Turn real tax, audit, finance or compliance work into practical Tamil courses. You bring the professional
              insight; Erudsway shapes, produces, hosts and markets the course.
            </p>
            <div className="mt-7 flex flex-wrap gap-3">
              <Link
                href="#apply"
                className={buttonVariants({ size: "lg", className: "bg-gold-400 text-navy-950 hover:bg-gold-200" })}
              >
                Share your course idea
              </Link>
              <Link
                href="#topics"
                className={buttonVariants({
                  size: "lg",
                  variant: "outline",
                  className: "border-white/30 bg-transparent text-white hover:bg-white/10",
                })}
              >
                See topics we need
              </Link>
            </div>
            <ul className="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80" aria-label="Before you apply">
              {assurances.map(({ icon: Icon, text }) => (
                <li key={text} className="flex items-center gap-2">
                  <Icon className="size-4 text-gold-400" aria-hidden />
                  {text}
                </li>
              ))}
            </ul>
            <p className="mt-6 text-sm text-white/60">
              25+ authors have joined · 25+ colleges in our outreach pipeline
            </p>
          </div>

          <ol className="grid gap-3" aria-label="How the Erudsway model works">
            {model.map((m, i) => (
              <li key={m.step} className="flex items-start gap-4 rounded-md bg-white/[0.06] p-4 ring-1 ring-white/10">
                <span
                  className="tabular flex size-9 shrink-0 items-center justify-center rounded-full bg-gold-400 font-serif text-lg text-navy-950"
                  aria-hidden
                >
                  {i + 1}
                </span>
                <span>
                  <span className="block text-xs font-bold tracking-[0.14em] text-gold-400 uppercase">{m.step}</span>
                  <span className="mt-0.5 block font-semibold text-white">{m.title}</span>
                  <span className="block text-sm text-white/70">{m.detail}</span>
                </span>
              </li>
            ))}
            <li className="px-1 text-sm text-white/60">
              Your knowledge stays at the centre. We build the digital experience around it.
            </li>
          </ol>
        </div>
      </section>

      {/* Division of work */}
      <section className="border-b border-line bg-white" aria-label="What you bring and what Erudsway does">
        <div className="mx-auto max-w-[1340px] px-4 py-12 sm:px-6">
          <SectionHeading eyebrow="A practical collaboration" title="You know the subject. We build the course." />
          <div className="mt-8 grid gap-4 md:grid-cols-2">
            {(
              [
                ["You bring the substance", split.you, "bg-paper"],
                ["We make it accessible", split.us, "bg-navy-100/40"],
              ] as const
            ).map(([title, items, bg]) => (
              <div key={title} className={cn("rounded-md border border-line p-5", bg)}>
                <h3 className="font-sans text-base font-bold text-ink">{title}</h3>
                <ul className="mt-3 space-y-2 text-sm text-stone-700">
                  {items.map((item) => (
                    <li key={item} className="flex gap-2.5">
                      <CircleCheck className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
                      {item}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Steps + formats */}
      <section
        className="mx-auto grid max-w-[1340px] gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2"
        aria-label="How it works"
      >
        <div>
          <SectionHeading eyebrow="How it works" title="From idea to approved course" />
          <ol className="mt-6 space-y-4">
            {steps.map(([title, detail], i) => (
              <li key={title} className="flex gap-4">
                <span
                  className="tabular flex size-8 shrink-0 items-center justify-center rounded-full border border-gold-400 font-serif text-gold-600"
                  aria-hidden
                >
                  {i + 1}
                </span>
                <span>
                  <span className="block font-semibold text-ink">{title}</span>
                  <span className="block text-sm leading-relaxed text-stone-700">{detail}</span>
                </span>
              </li>
            ))}
          </ol>
          <p className="mt-6 flex items-start gap-2 text-sm text-stone-500">
            <ShieldCheck className="mt-0.5 size-4 shrink-0" aria-hidden />
            Verification details are for internal onboarding only and are never shown publicly.
          </p>
        </div>
        <div>
          <SectionHeading eyebrow="Work the way you prefer" title="Choose your content format" />
          <ul className="mt-6 grid gap-3 sm:grid-cols-2">
            {formats.map(({ icon: Icon, name, detail }) => (
              <li key={name} className="rounded-md border border-line bg-white p-4">
                <Icon className="size-5 text-gold-600" aria-hidden />
                <span className="mt-2 block font-semibold text-ink">{name}</span>
                <span className="mt-1 block text-sm leading-relaxed text-stone-700">{detail}</span>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Earning */}
      <section className="border-y border-line bg-paper-2/60" aria-labelledby="earn-title">
        <div className="mx-auto grid max-w-[1340px] gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_1.2fr] lg:items-center">
          <div>
            <p className="mb-3 text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">Transparent terms</p>
            <h2 id="earn-title" className="text-h2">
              Agree how you earn
            </h2>
            <p className="mt-3 text-[0.95rem] leading-relaxed text-stone-700">
              An extra way to earn from knowledge you already use. One topic can grow into a series — beginner and
              advanced modules, workbooks, updates, masterclasses and corporate training.
            </p>
          </div>
          <div>
            <ul className="flex flex-wrap gap-2" aria-label="Commercial models">
              {earning.map((e) => (
                <li
                  key={e}
                  className="rounded-full bg-white px-3.5 py-1.5 text-sm font-medium text-navy-900 ring-1 ring-line"
                >
                  {e}
                </li>
              ))}
            </ul>
            <p className="mt-4 text-xs leading-relaxed text-stone-500">
              Models are discussed after we review the course fit and scope. Sales and earnings are not guaranteed;
              prices, shares, refunds, taxes and payment timing are set out in a written agreement.
            </p>
          </div>
        </div>
      </section>

      {/* Topics */}
      <section
        id="topics"
        className="mx-auto max-w-[1340px] scroll-mt-28 px-4 py-12 sm:px-6"
        aria-labelledby="topics-title"
      >
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div className="max-w-2xl">
            <p className="mb-3 text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">Choose a starting point</p>
            <h2 id="topics-title" className="text-h2">
              Topics we&rsquo;re actively looking for
            </h2>
            <p className="mt-3 text-[0.95rem] leading-relaxed text-stone-700">
              Pick one and it&rsquo;s added to your application.
            </p>
          </div>
          <Link
            href="#apply"
            className="inline-flex items-center gap-1.5 text-sm font-semibold text-navy-900 hover:underline"
          >
            Propose your own topic <ArrowRight className="size-4" aria-hidden />
          </Link>
        </div>
        <ul className="mt-7 grid grid-cols-2 gap-2 lg:grid-cols-4">
          {topics.map((t) => (
            <li key={t.name}>
              <Link
                href={`/teach?topic=${encodeURIComponent(t.name)}#apply`}
                className="group flex h-full items-center justify-between gap-2 rounded-md border border-line bg-white px-3 py-2.5 transition-colors hover:border-navy-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-900 sm:gap-3 sm:px-4 sm:py-3"
              >
                <span>
                  <span className="block text-sm font-semibold text-ink">{t.name}</span>
                  <span
                    className={cn(
                      "mt-1 inline-flex h-5 items-center rounded-sm px-1.5 text-[0.68rem] font-bold tracking-wide ring-1 ring-inset",
                      tagStyle[t.tag],
                    )}
                  >
                    {t.tag}
                  </span>
                </span>
                <span className="text-xs font-semibold whitespace-nowrap text-navy-800 group-hover:underline">
                  <span className="hidden sm:inline">I can teach this</span>
                  <ArrowRight className="size-4 sm:hidden" aria-hidden />
                  <span className="sr-only sm:hidden">I can teach this</span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </section>

      {/* Standard + FAQ */}
      <section className="border-t border-line bg-white" aria-label="Quality standard and questions">
        <div className="mx-auto grid max-w-[1340px] gap-12 px-4 py-12 sm:px-6 lg:grid-cols-2">
          <div>
            <SectionHeading eyebrow="Quality standard" title="Every course is reviewed before it goes live" />
            <ul className="mt-6 grid gap-x-6 gap-y-3 sm:grid-cols-2">
              {checks.map(([name, q]) => (
                <li key={name} className="flex gap-2.5 text-sm">
                  <BadgeCheck className="mt-0.5 size-4 shrink-0 text-gold-600" aria-hidden />
                  <span>
                    <span className="font-semibold text-ink">{name}</span>
                    <span className="block text-stone-700">{q}</span>
                  </span>
                </li>
              ))}
            </ul>
            <h3 className="mt-8 flex items-center gap-2 font-sans text-sm font-bold text-ink">
              <Sparkles className="size-4 text-gold-600" aria-hidden /> Every lesson follows a practical flow
            </h3>
            <ol className="mt-3 flex flex-wrap gap-1.5 text-xs text-stone-700">
              {lessonFlow.map((s, i) => (
                <li key={s} className="rounded-sm bg-paper-2 px-2 py-1">
                  <span className="tabular text-stone-500">{i + 1}.</span> {s}
                </li>
              ))}
            </ol>
          </div>
          <div>
            <SectionHeading eyebrow="FAQ" title="Questions professionals often ask" />
            <div className="mt-6 divide-y divide-line border-y border-line">
              {faqs.map(([q, a]) => (
                <details key={q} className="group py-1">
                  <summary className="flex cursor-pointer list-none items-center justify-between gap-4 rounded-sm py-3 font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-900 [&::-webkit-details-marker]:hidden">
                    {q}
                    <ChevronDown
                      className="size-4 shrink-0 text-stone-500 transition-transform group-open:rotate-180 motion-reduce:transition-none"
                      aria-hidden
                    />
                  </summary>
                  <p className="pb-4 text-sm leading-relaxed text-stone-700">{a}</p>
                </details>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Apply */}
      <section id="apply" className="scroll-mt-28 border-t border-line bg-paper" aria-label="Share your course idea">
        <div className="mx-auto max-w-3xl px-4 py-14 sm:px-6">
          <div className="mb-8 flex items-start gap-3 rounded-md border border-line bg-white p-4 text-sm text-stone-700">
            <Megaphone className="mt-0.5 size-4 shrink-0 text-gold-600" aria-hidden />
            <p>
              A short idea is enough to begin. We don&rsquo;t need your PAN, Aadhaar, membership certificate, bank
              details or full CV at this stage. Prefer email?{" "}
              <a href="mailto:support@erudsway.com" className="font-semibold text-navy-800 underline">
                support@erudsway.com
              </a>
            </p>
          </div>
          <Suspense fallback={<Skeleton className="h-72 w-full" />}>
            <ApplySection />
          </Suspense>
        </div>
      </section>

      <p className="mx-auto max-w-[1340px] px-4 pb-10 text-xs leading-relaxed text-stone-500 sm:px-6">
        Course approval, fees, content rights and update responsibilities are agreed individually. College and
        government-related opportunities are a pipeline, not confirmed work. Erudsway Educorp LLP provides professional
        training and is not a government authority.
      </p>
    </>
  );
}
