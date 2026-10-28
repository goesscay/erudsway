import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  ArrowUpRight,
  BookOpenCheck,
  Briefcase,
  CircleCheck,
  FileText,
  GraduationCap,
  LibraryBig,
  Lightbulb,
  ListChecks,
  Receipt,
  Scale,
  Sparkles,
  Store,
  TrendingUp,
  Workflow,
} from "lucide-react";
import { SectionHeading } from "@/components/brand/section-heading";
import { buttonVariants } from "@/components/ui/button";
import { getCategories } from "@/lib/catalog/queries";
import { cn } from "@/lib/utils";

export const metadata: Metadata = {
  title: "About Erudsway — our story, vision and mission",
  description:
    "Erudsway Educorp LLP builds practical, career-focused learning in taxation, accounting, audit, corporate law and business compliance, guided by experienced professionals.",
};

const highlights = ["Practice-focused learning", "Experienced professionals", "Technology-enabled future"];

const flow = [
  ["01", "Understand the concept", "Build a strong foundation"],
  ["02", "Explore a practical case", "Connect theory with real situations"],
  ["03", "Apply with confidence", "Practise, review, improve"],
] as const;

const beliefs = [
  {
    icon: Lightbulb,
    title: "Clarity before complexity",
    text: "Explain the concept, its purpose and when it applies before moving into advanced procedures.",
  },
  {
    icon: FileText,
    title: "Cases, not just slides",
    text: "Use practical scenarios, workings, documents and illustrations that resemble professional assignments.",
  },
  {
    icon: TrendingUp,
    title: "Progress through practice",
    text: "Help learners gain confidence through deliberate practice, verification and continuous improvement.",
  },
];

const mission = [
  "Bridge academic concepts and practical work.",
  "Connect learners with capable professionals.",
  "Make learning accessible and understandable.",
  "Offer structured cases, workings and resources.",
  "Develop practical simulations and digital tools.",
  "Support ongoing professional development.",
];

const practice = [
  {
    icon: Receipt,
    title: "GST practical learning",
    text: "Scenarios spanning invoices, ITC, IMS, GSTR-1, GSTR-2B, GSTR-3B, reconciliations and annual returns.",
  },
  {
    icon: ListChecks,
    title: "TDS workflow learning",
    text: "Planned practice for deduction, challans, statement preparation, validation, corrections and reconciliation.",
  },
  {
    icon: BookOpenCheck,
    title: "Supportive learning resources",
    text: "Worked examples, checklists, explanatory videos and practice material, where included in a course.",
  },
];

const gstFlow = ["Business case", "Sales / Purchase", "Documents", "Return mapping", "Reconciliation", "Review"];

const audiences = [
  {
    icon: GraduationCap,
    title: "Students & graduates",
    text: "Build a practical foundation alongside academic study and early career preparation.",
  },
  {
    icon: Briefcase,
    title: "Accountants & practitioners",
    text: "Strengthen applied understanding of taxation, reporting and everyday compliance.",
  },
  {
    icon: Store,
    title: "Business owners",
    text: "Develop awareness of essential business, tax and regulatory processes.",
  },
  {
    icon: Scale,
    title: "Experienced professionals",
    text: "Refresh knowledge, explore new areas and contribute their subject expertise.",
  },
];

export default async function AboutPage() {
  const areas = (await getCategories()).length || 10;
  const stats = [
    ["750+", "Learners"],
    ["25+", "Contributors"],
    [String(areas), "Course areas"],
  ] as const;

  return (
    <>
      {/* Hero */}
      <section className="dark relative overflow-hidden bg-navy-950 text-foreground" aria-labelledby="about-title">
        <div
          aria-hidden
          className="pointer-events-none absolute -top-48 -right-40 size-[700px] rounded-full border border-gold-400/15 shadow-[0_0_0_90px_rgba(255,255,255,0.018),0_0_0_190px_rgba(255,255,255,0.012)]"
        />
        <div className="relative mx-auto grid max-w-[1340px] items-center gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:py-20">
          <div>
            <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">
              About Erudsway · Our story
            </p>
            <h1
              id="about-title"
              className="max-w-[16ch] text-[2.5rem] leading-[1.05] tracking-[-0.03em] sm:text-[3.4rem]"
            >
              Knowledge that <span className="text-gold-400">moves minds.</span>
            </h1>
            <p className="mt-5 max-w-[56ch] text-lg leading-relaxed text-white/80">
              Empowering learners through practical, career-focused courses in Taxation, Accounting, Audit, Corporate
              Law and Business Compliance, guided by experienced professionals.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                href="#story"
                className={buttonVariants({ size: "lg", className: "bg-gold-400 text-navy-950 hover:bg-gold-200" })}
              >
                Discover our story <ArrowRight />
              </Link>
              <Link
                href="/courses"
                className={buttonVariants({
                  size: "lg",
                  variant: "outline",
                  className: "border-white/30 bg-transparent text-white hover:bg-white/10",
                })}
              >
                Explore courses <ArrowUpRight />
              </Link>
            </div>
            <ul className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/75">
              {highlights.map((h) => (
                <li key={h} className="flex items-center gap-2">
                  <Sparkles className="size-4 text-gold-400" aria-hidden />
                  {h}
                </li>
              ))}
            </ul>
          </div>

          {/* "From knowing to doing" */}
          <figure className="relative mx-auto w-full max-w-[500px]" aria-label="How learning works at Erudsway">
            <div
              aria-hidden
              className="absolute inset-x-6 -inset-y-3 -rotate-6 rounded-[2rem] border border-dashed border-gold-400/30"
            />
            <div className="relative rounded-3xl border border-gold-200/20 bg-gradient-to-br from-navy-800 to-navy-950 p-7 shadow-2xl">
              <p className="text-[0.7rem] font-bold tracking-[0.16em] text-gold-400 uppercase">
                Practical learning at Erudsway
              </p>
              <p className="mt-4 mb-6 font-serif text-[1.9rem] leading-tight text-white">
                From knowing
                <br />
                to doing.
              </p>
              <ol className="space-y-2.5">
                {flow.map(([n, title, sub]) => (
                  <li
                    key={n}
                    className="flex items-center gap-4 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3.5"
                  >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gold-400/15 text-xs font-bold text-gold-200">
                      {n}
                    </span>
                    <span>
                      <span className="block text-sm font-semibold text-white">{title}</span>
                      <span className="block text-xs text-white/55">{sub}</span>
                    </span>
                  </li>
                ))}
              </ol>
              <p className="mt-6 text-xs text-white/50">
                Thoughtful teaching · Practical resources · Continuous learning
              </p>
            </div>
            <figcaption className="absolute -right-2 -bottom-5 rotate-3 rounded-2xl bg-white px-5 py-3.5 text-navy-900 shadow-xl">
              <span className="block font-serif text-xl">Learn → Apply</span>
              <span className="block text-xs text-stone-500">Professional learning, reimagined.</span>
            </figcaption>
          </figure>
        </div>
      </section>

      {/* Figures */}
      <section aria-label="Erudsway in numbers" className="border-b border-line bg-white">
        <dl className="mx-auto grid max-w-[1340px] grid-cols-3 divide-x divide-line px-4 py-7 sm:px-6">
          {stats.map(([value, label]) => (
            <div key={label} className="px-3 first:pl-0 sm:px-8">
              <dt className="sr-only">{label}</dt>
              <dd className="tabular font-serif text-3xl text-navy-900 sm:text-[2.1rem]">{value}</dd>
              <dd className="mt-1 text-xs text-stone-500 sm:text-sm">{label}</dd>
            </div>
          ))}
        </dl>
      </section>

      {/* Our identity */}
      <section id="story" className="scroll-mt-28" aria-labelledby="story-title">
        <div className="mx-auto max-w-[1340px] px-4 py-16 sm:px-6 lg:py-20">
          <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
            <div>
              <div className="mb-3 flex items-center gap-3">
                <span className="rule-gold" aria-hidden />
                <span className="text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">Our identity</span>
              </div>
              <h2 id="story-title" className="text-h2">
                We believe learning should lead to <span className="text-gold-600">doing.</span>
              </h2>
              <blockquote className="mt-8 rounded-2xl border-l-4 border-gold-400 bg-paper-2/70 p-7">
                <p className="font-serif text-xl leading-snug text-navy-900 italic sm:text-2xl">
                  “Knowledge becomes truly valuable when learners know how to apply it.”
                </p>
                <footer className="mt-4 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                  The philosophy behind Erudsway
                </footer>
              </blockquote>
            </div>
            <div className="space-y-4 text-[0.97rem] leading-[1.9] text-stone-700 lg:pt-4">
              <p>
                <strong className="text-navy-900">Erudsway Educorp LLP</strong> is building a professional learning and
                knowledge-sharing ecosystem designed to bridge the gap between theoretical education and practical
                application.
              </p>
              <p>
                We bring together practitioners, educators and subject specialists to make accounting, tax, audit,
                regulatory compliance and business-related topics easier to understand and apply.
              </p>
              <p>
                Our vision extends beyond a library of video lessons. We are developing a learning experience that
                combines{" "}
                <strong className="text-navy-900">
                  structured courses, real-world illustrations, working files, case studies, assessments and
                  practice-oriented digital tools
                </strong>
                .
              </p>
              <p>
                Erudsway seeks to serve students, accountants, professional aspirants, practising consultants,
                entrepreneurs and working professionals through accessible, professionally relevant learning.
              </p>
              <p>
                As the platform grows, we aim to broaden opportunities for expert contributors and support Tamil and
                English learning experiences wherever suitable.
              </p>
            </div>
          </div>

          <ul className="mt-12 grid gap-4 md:grid-cols-3">
            {beliefs.map(({ icon: Icon, title, text }) => (
              <li key={title} className="rounded-2xl border border-line bg-white p-6">
                <span className="flex size-11 items-center justify-center rounded-xl bg-gold-50 text-gold-600">
                  <Icon className="size-5" aria-hidden />
                </span>
                <h3 className="mt-5 font-sans text-base font-bold text-navy-900">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-stone-700">{text}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Vision & mission */}
      <section id="vision" className="scroll-mt-28 border-y border-line bg-paper-2/50" aria-labelledby="vision-title">
        <div className="mx-auto max-w-[1340px] px-4 py-16 sm:px-6 lg:py-20">
          <div className="mb-3 flex items-center gap-3">
            <span className="rule-gold" aria-hidden />
            <span className="text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">Our direction</span>
          </div>
          <h2 id="vision-title" className="text-h2">
            Purpose that shapes everything we build.
          </h2>
          <div className="mt-9 grid gap-5 lg:grid-cols-2">
            <article className="dark relative overflow-hidden rounded-3xl bg-navy-950 p-8 text-foreground sm:p-10">
              <p className="text-xs font-bold tracking-[0.18em] text-gold-400 uppercase">Our vision</p>
              <h3 className="mt-5 font-serif text-[1.75rem] leading-snug font-medium text-white">
                A trusted home for applied professional learning.
              </h3>
              <p className="mt-5 leading-relaxed text-white/70">
                To build a professional learning ecosystem where knowledge becomes practical skill, confidence and
                meaningful opportunity — connecting learners, educators, practitioners and institutions.
              </p>
              <Sparkles aria-hidden className="absolute -right-6 -bottom-8 size-44 text-gold-400/10" />
            </article>
            <article className="rounded-3xl border border-line bg-white p-8 sm:p-10">
              <p className="text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">Our mission</p>
              <h3 className="mt-5 font-serif text-[1.75rem] leading-snug font-medium text-navy-900">
                Bring real professional work into the learning experience.
              </h3>
              <ul className="mt-5 space-y-2.5 text-sm text-stone-700">
                {mission.map((m) => (
                  <li key={m} className="flex gap-2.5">
                    <CircleCheck className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
                    {m}
                  </li>
                ))}
              </ul>
            </article>
          </div>
        </div>
      </section>

      {/* Practical learning */}
      <section id="practice" className="scroll-mt-28" aria-labelledby="practice-title">
        <div className="mx-auto grid max-w-[1340px] items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:py-20">
          <div>
            <div className="mb-3 flex items-center gap-3">
              <span className="rule-gold" aria-hidden />
              <span className="text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">
                Beyond watching lessons
              </span>
            </div>
            <h2 id="practice-title" className="text-h2">
              Practical learning, brought to life.
            </h2>
            <p className="mt-3 max-w-[60ch] text-[0.95rem] leading-relaxed text-stone-700">
              Erudsway is developing interactive learning experiences where participants can explore processes from
              different perspectives and practise in an educational environment.
            </p>
            <ul className="mt-7 space-y-5">
              {practice.map(({ icon: Icon, title, text }) => (
                <li key={title} className="flex gap-4">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-600">
                    <Icon className="size-5" aria-hidden />
                  </span>
                  <span>
                    <span className="block font-semibold text-navy-900">{title}</span>
                    <span className="block text-sm leading-relaxed text-stone-700">{text}</span>
                  </span>
                </li>
              ))}
            </ul>
            <Link
              href="/gst-simulator"
              className="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-navy-800 hover:underline"
            >
              Try the GST simulator free for 7 days <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>

          <figure className="rounded-3xl border border-line bg-white p-6 shadow-[0_20px_50px_rgba(10,40,80,0.07)] sm:p-7">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <span className="text-xs font-bold tracking-[0.14em] text-navy-900 uppercase">
                Erudsway practice framework
              </span>
              <span className="rounded-full bg-gold-50 px-3 py-1 text-[0.7rem] font-semibold text-gold-600">
                Illustrative workflow
              </span>
            </div>
            <p className="mt-4 mb-4 font-semibold text-navy-900">GST: from business transaction to return</p>
            <ol className="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
              {gstFlow.map((step, i) => (
                <li
                  key={step}
                  className="rounded-xl border border-line bg-paper px-3 py-4 text-center text-xs font-semibold text-navy-800"
                >
                  <span className="mb-1.5 block font-serif text-lg text-gold-600">{i + 1}</span>
                  {step}
                </li>
              ))}
            </ol>
            <figcaption className="mt-4 space-y-3 text-xs leading-relaxed text-stone-600">
              <span className="flex items-center gap-2">
                <Workflow className="size-4 text-gold-600" aria-hidden />
                Learn both the procedure and the reasoning behind each step.
              </span>
              <span className="block border-t border-line pt-3">
                <strong className="text-navy-900">Built for education:</strong> the illustration describes the intended
                learning method. Availability of individual modules and tools may change as development and testing
                progress.
              </span>
            </figcaption>
          </figure>
        </div>
      </section>

      {/* Who we serve */}
      <section className="border-y border-line bg-paper-2/50" aria-label="Who we serve">
        <div className="mx-auto max-w-[1340px] px-4 py-16 sm:px-6 lg:py-20">
          <SectionHeading eyebrow="Who we serve" title="For every stage of the professional journey." />
          <ul className="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {audiences.map(({ icon: Icon, title, text }) => (
              <li key={title} className="rounded-2xl border border-line bg-white p-6">
                <Icon className="size-6 text-gold-600" aria-hidden />
                <h3 className="mt-4 font-sans text-base font-bold text-navy-900">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-stone-700">{text}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Teach and collaborate */}
      <section className="mx-auto max-w-[1340px] px-4 py-16 sm:px-6" aria-labelledby="collab-title">
        <div className="dark relative grid items-center gap-8 overflow-hidden rounded-3xl bg-gradient-to-br from-navy-800 to-navy-950 p-8 text-foreground sm:p-12 lg:grid-cols-[1.5fr_auto]">
          <div>
            <p className="text-xs font-bold tracking-[0.18em] text-gold-400 uppercase">Teach and collaborate</p>
            <h2
              id="collab-title"
              className="mt-4 font-serif text-[2rem] leading-tight font-medium text-white sm:text-[2.4rem]"
            >
              Your expertise can open new possibilities for others.
            </h2>
            <p className="mt-4 max-w-[62ch] text-sm leading-relaxed text-white/70">
              Erudsway welcomes practising professionals and subject experts who want to share useful, practice-oriented
              knowledge. Course proposals undergo review, and content scope and commercial terms are agreed separately.
            </p>
          </div>
          <Link
            href="/teach"
            className={cn(
              buttonVariants({ size: "lg", className: "bg-gold-400 text-navy-950 hover:bg-gold-200" }),
              "relative justify-self-start lg:justify-self-end",
            )}
          >
            <LibraryBig /> Become a contributor
          </Link>
          <Sparkles
            aria-hidden
            className="pointer-events-none absolute -top-10 right-20 size-48 text-gold-400/[0.07]"
          />
        </div>
      </section>
    </>
  );
}
