import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowLeftRight,
  BadgeIndianRupee,
  BookOpenCheck,
  Briefcase,
  Check,
  ChevronDown,
  CreditCard,
  FileCheck2,
  FileSpreadsheet,
  GraduationCap,
  Inbox,
  Landmark,
  Receipt,
  ShieldCheck,
  Sparkles,
  Store,
  Timer,
} from "lucide-react";
import { SectionHeading } from "@/components/brand/section-heading";
import { getCourse, getSimulatorInfo } from "@/lib/catalog/queries";
import { formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";
import { SimulatorAccessPanel } from "./access-panel";
import { PlanActions } from "./plan-actions";

export const metadata: Metadata = {
  title: "GST Simulator — practise GST returns free for 7 days",
  description:
    "Your own practice GSTIN on a GST portal simulator: file GSTR-1 and GSTR-3B, act on IMS, reconcile ITC with GSTR-2B and pay challans with realistic practice data. Start a free 7-day trial — no card needed.",
};

const assurances = [
  { icon: Timer, text: "7 days free" },
  { icon: CreditCard, text: "No card needed" },
  { icon: ShieldCheck, text: "Practice data only — nothing reaches the real portal" },
];

const features = [
  {
    icon: FileCheck2,
    title: "GSTR-1 and GSTR-1A",
    detail: "B2B, B2C, exports, credit and debit notes, advances and HSN summary — add, upload, preview and file.",
  },
  {
    icon: Receipt,
    title: "GSTR-3B, start to finish",
    detail: "Prepare the return, offset liability with ITC and cash, generate the payment and file.",
  },
  {
    icon: Inbox,
    title: "IMS, GSTR-2A and GSTR-2B",
    detail: "Accept, reject or keep invoices pending in IMS and see exactly how GSTR-2B changes.",
  },
  {
    icon: FileSpreadsheet,
    title: "ITC reconciliation",
    detail: "Match your purchase register with 2A/2B, compare ITC and build a working paper.",
  },
  {
    icon: Landmark,
    title: "Challans and ledgers",
    detail: "Create PMT-06 challans, pay demands, file PMT-09 and follow the cash and credit ledgers.",
  },
  {
    icon: BadgeIndianRupee,
    title: "Refunds, TDS/TCS and demands",
    detail: "Practise refund applications, TDS/TCS credits and responding to demands.",
  },
  {
    icon: Store,
    title: "e-Invoicing (IRP)",
    detail: "Generate e-invoices and see them flow into returns on both sides.",
  },
  {
    icon: BookOpenCheck,
    title: "Guided practice",
    detail: "Exercises and a return journey that walk you through a filing cycle step by step.",
  },
];

const audiences = [
  {
    icon: GraduationCap,
    title: "Students and articled assistants",
    detail: "Walk into your first GST assignment having already filed returns — not just read about them.",
  },
  {
    icon: Briefcase,
    title: "Accountants and GST practitioners",
    detail: "Try a new form, IMS action or reconciliation approach before you do it on a client’s live GSTIN.",
  },
  {
    icon: Store,
    title: "Business owners",
    detail: "Understand what your accountant files every month, and file small returns yourself with confidence.",
  },
];

const trialSteps = [
  ["Create a free account", "Sign up on Erudsway with your email — it takes a minute."],
  ["Start your trial", "Click once. Your practice GST login is ready within seconds."],
  ["Practise for 7 days", "File, reconcile and pay as often as you like. Mistakes cost nothing."],
  ["Keep going with a plan", "Choose a plan before the trial ends to keep the same login and your work."],
] as const;

/** [feature, 3 months, 12 months]: true = included, false = not included, text = the detail. */
const comparison: [string, boolean | string, boolean | string][] = [
  ["Your own practice GSTIN (supplier + recipient)", true, true],
  ["GSTR-1, 1A, 2A, 2B, 3B and IMS", true, true],
  ["Challans, cash and credit ledgers, refunds, TDS/TCS", true, true],
  ["ITC reconciliation, workpapers and guided exercises", true, true],
  ["e-Invoicing (IRP) practice", true, true],
  ["Practice attempts", "Unlimited", "Unlimited"],
  ["Monthly filing cycles you can practise", "About 3", "A full year, incl. quarterly cycles"],
  ["Simulator updates released during your plan", true, true],
  ["Course videos, notes and downloadable files", false, false],
  ["Access period", "90 days", "365 days"],
];

const planRules = [
  "Access starts as soon as payment is confirmed; your practice login is ready within moments.",
  "Bought during your free trial? You keep the same login and everything you’ve practised.",
  "In the last 30 days of a plan you can buy it again; the new period starts when the current one ends.",
  "We email you before your plan ends. When it ends, the practice login and its returns are removed.",
];

const faqs = [
  [
    "Is this the real GST portal?",
    "No. It’s an independent practice simulator built to look and behave like the GST portal. It uses practice data only and never connects to GSTN or any government system — nothing you file is real.",
  ],
  [
    "Do I need to pay or add a card for the trial?",
    "No. The 7-day trial is free and needs only a verified Erudsway account. There’s one trial per account.",
  ],
  [
    "What happens when the trial ends?",
    "The practice login and its returns are removed. If you choose a plan before then, you keep the same login and everything you’ve practised. We email you two days before the trial ends.",
  ],
  [
    "How do I sign in to the simulator?",
    "From this page, one click signs you in. You can also sign in yourself at gst.erudsway.com with the username and password shown here.",
  ],
  [
    "Can I renew a plan?",
    "Yes. In the last 30 days of a plan you can buy it again; the new period starts when the current one ends, and you keep the same login. We remind you three days before it ends.",
  ],
  [
    "Is the simulator part of any course?",
    "The Simulator Mastery edition of our GST Practical Mastery course includes simulator access alongside the lessons. The plans below are for practice on its own.",
  ],
  ["Does it work on a phone?", "It’s built for a laptop or desktop browser, like the real portal."],
] as const;

export default async function GstSimulatorPage() {
  const info = await getSimulatorInfo();
  const trialDays = info?.trial_days ?? 7;
  const loginUrl = info?.login_url ?? "https://gst.erudsway.com/gst/login";
  const plans = info?.plans_course ? await getCourse(info.plans_course) : null;

  return (
    <>
      {/* Hero: the promise and the trial card */}
      <section className="dark bg-navy-950 text-foreground" aria-labelledby="sim-title">
        <div className="mx-auto grid max-w-[1340px] items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.2fr_1fr] lg:py-16">
          <div>
            <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">
              GST return filing simulator
            </p>
            <h1 id="sim-title" className="max-w-[20ch] text-[2.3rem] leading-[1.08] tracking-[-0.02em] sm:text-[3rem]">
              File GST returns for real — before you file them for real.
            </h1>
            <p className="mt-5 max-w-[56ch] text-lg leading-relaxed text-white/80">
              Get your own practice GSTIN on a portal that looks and works like the GST portal. File GSTR-1 and GSTR-3B,
              act on IMS, reconcile ITC and pay challans with realistic data — and make every mistake here, not on a
              client&rsquo;s return.
            </p>
            <ul className="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80" aria-label="About the trial">
              {assurances.map(({ icon: Icon, text }) => (
                <li key={text} className="flex items-center gap-2">
                  <Icon className="size-4 text-gold-400" aria-hidden />
                  {text}
                </li>
              ))}
            </ul>
          </div>
          <div id="trial" className="scroll-mt-28">
            <SimulatorAccessPanel trialDays={trialDays} loginUrl={loginUrl} />
          </div>
        </div>
      </section>

      {/* What you can practise */}
      <section className="mx-auto max-w-[1340px] px-4 py-12 sm:px-6" aria-label="What you can practise">
        <SectionHeading eyebrow="What you can practise" title="The whole GST cycle, on one practice login">
          Every screen you&rsquo;ll use in a monthly filing — from raising the invoice to paying the tax.
        </SectionHeading>
        <ul className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {features.map(({ icon: Icon, title, detail }) => (
            <li key={title} className="rounded-md border border-line bg-white p-5">
              <Icon className="size-5 text-gold-600" aria-hidden />
              <span className="mt-2.5 block font-semibold text-ink">{title}</span>
              <span className="mt-1 block text-sm leading-relaxed text-stone-700">{detail}</span>
            </li>
          ))}
        </ul>
      </section>

      {/* Both sides of every invoice */}
      <section className="border-y border-line bg-white" aria-labelledby="both-title">
        <div className="mx-auto grid max-w-[1340px] gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:items-center">
          <div>
            <p className="mb-3 text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">
              Learn it the way it works
            </p>
            <h2 id="both-title" className="text-h2">
              See both sides of every invoice
            </h2>
            <p className="mt-3 text-[0.95rem] leading-relaxed text-stone-700">
              Your login comes as a pair: a supplier and its customer. Report a sale in the supplier&rsquo;s GSTR-1,
              then switch to the recipient and watch it arrive in IMS and GSTR-2B. Accept or reject it and see what that
              does to the ITC you can claim in GSTR-3B.
            </p>
            <p className="mt-3 text-[0.95rem] leading-relaxed text-stone-700">
              It starts with three months of practice transactions, so there&rsquo;s something to file on day one.
            </p>
          </div>
          <ol className="grid gap-3" aria-label="How an invoice flows through the simulator">
            {[
              ["Supplier", "Raises the invoice and reports it in GSTR-1"],
              ["IMS", "The recipient accepts, rejects or keeps it pending"],
              ["GSTR-2B", "Accepted invoices become eligible ITC"],
              ["GSTR-3B", "ITC is claimed, liability set off, balance paid by challan"],
            ].map(([step, detail], i) => (
              <li key={step} className="flex items-center gap-4 rounded-md bg-paper p-4 ring-1 ring-line">
                <span
                  className="tabular flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-900 font-serif text-lg text-white"
                  aria-hidden
                >
                  {i + 1}
                </span>
                <span>
                  <span className="block font-semibold text-ink">{step}</span>
                  <span className="block text-sm text-stone-700">{detail}</span>
                </span>
                {i < 3 && (
                  <ArrowLeftRight className="ml-auto hidden size-4 rotate-90 text-stone-400 sm:block" aria-hidden />
                )}
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* Who it's for + how the trial works */}
      <section
        className="mx-auto grid max-w-[1340px] gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2"
        aria-label="Who it’s for and how the trial works"
      >
        <div>
          <SectionHeading eyebrow="Who it’s for" title="Built for people who file — or soon will" />
          <ul className="mt-6 space-y-4">
            {audiences.map(({ icon: Icon, title, detail }) => (
              <li key={title} className="flex gap-4">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-navy-100 text-navy-800">
                  <Icon className="size-5" aria-hidden />
                </span>
                <span>
                  <span className="block font-semibold text-ink">{title}</span>
                  <span className="block text-sm leading-relaxed text-stone-700">{detail}</span>
                </span>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <SectionHeading eyebrow="Free 7-day trial" title="How the trial works" />
          <ol className="mt-6 space-y-4">
            {trialSteps.map(([title, detail], i) => (
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
          <Link
            href="#trial"
            className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-navy-800 hover:underline"
          >
            <Sparkles className="size-4" aria-hidden /> Start your free trial
          </Link>
        </div>
      </section>

      {/* Plans */}
      <section id="plans" className="scroll-mt-28 border-y border-line bg-paper-2/60" aria-labelledby="plans-title">
        <div className="mx-auto max-w-[1340px] px-4 py-12 sm:px-6">
          <div className="max-w-2xl">
            <p className="mb-3 text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">After your trial</p>
            <h2 id="plans-title" className="text-h2">
              Keep practising with a plan
            </h2>
            <p className="mt-3 text-[0.95rem] leading-relaxed text-stone-700">
              Choose a plan during your trial and you keep the same login and everything you&rsquo;ve practised.
            </p>
          </div>
          {plans && plans.editions.length > 0 ? (
            <ul className="mt-8 grid max-w-4xl gap-5 md:grid-cols-2">
              {plans.editions.map((e, i) => {
                const months = e.access_days ? Math.max(1, Math.round(e.access_days / 30)) : null;
                return (
                  <li
                    key={e.id}
                    className={cn(
                      "relative flex flex-col rounded-lg border bg-white p-6",
                      e.is_recommended ? "border-navy-900 ring-1 ring-navy-900" : "border-line",
                    )}
                  >
                    {e.is_recommended && (
                      <span className="absolute -top-3 left-6 inline-flex h-6 items-center rounded-sm bg-gold-400 px-2 text-[0.68rem] font-bold tracking-wide text-navy-950 uppercase">
                        Best value
                      </span>
                    )}
                    <h3 className="font-sans text-lg font-bold text-ink">{e.name}</h3>
                    <p className="mt-2">
                      <span className="tabular font-serif text-3xl font-semibold text-ink">
                        {formatPaise(e.price.amount)}
                      </span>
                      {months && months > 1 && (
                        <span className="ml-2 text-sm text-stone-500">
                          ≈ {formatPaise(Math.round(e.price.amount / months))} a month
                        </span>
                      )}
                    </p>
                    <p className="text-xs text-stone-500">
                      {e.access_days ? `${e.access_days} days of access` : "No end date"}
                    </p>
                    {i > 0 && (
                      <p className="mt-4 text-xs font-semibold text-stone-500">
                        Everything in {plans.editions[i - 1].name}, plus:
                      </p>
                    )}
                    <ul className={cn("flex-1 space-y-1.5 text-sm text-stone-700", i > 0 ? "mt-1.5" : "mt-4")}>
                      {e.includes.map((item) => (
                        <li key={item} className="flex gap-2">
                          <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
                          {item}
                        </li>
                      ))}
                    </ul>
                    <div className="mt-6">
                      <PlanActions slug={plans.slug} edition={e.id} featured={e.is_recommended} />
                    </div>
                  </li>
                );
              })}
            </ul>
          ) : (
            <p className="mt-6 text-sm text-stone-600">
              Plans are being set up — start your free trial in the meantime.
            </p>
          )}

          {plans && plans.editions.length > 0 && (
            <div className="mt-12 grid max-w-5xl gap-8 lg:grid-cols-[1.4fr_1fr]">
              <div>
                <h3 className="font-sans text-lg font-bold text-ink">Compare the plans</h3>
                <p className="mt-1 text-sm text-stone-600">
                  Both plans include the same simulator. The difference is how long you can use it.
                </p>
                <div className="mt-4 overflow-x-auto rounded-lg border border-line bg-white">
                  <table className="w-full text-left text-sm">
                    <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                      <tr>
                        <th scope="col" className="px-4 py-3">
                          What you get
                        </th>
                        <th scope="col" className="px-4 py-3 text-center">
                          3 months
                        </th>
                        <th scope="col" className="px-4 py-3 text-center">
                          12 months
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                      {comparison.map(([feature, three, twelve]) => (
                        <tr key={feature}>
                          <th scope="row" className="px-4 py-2.5 font-normal text-stone-800">
                            {feature}
                          </th>
                          {[three, twelve].map((v, i) => (
                            <td key={i} className="px-4 py-2.5 text-center text-stone-800">
                              {v === true ? (
                                <Check className="mx-auto size-4 text-success" aria-label="Included" />
                              ) : v === false ? (
                                <span className="text-stone-400" aria-label="Not included">
                                  —
                                </span>
                              ) : (
                                v
                              )}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
              <div className="space-y-5">
                <div className="rounded-lg border border-line bg-white p-5">
                  <h3 className="font-sans text-base font-bold text-ink">How access works</h3>
                  <ul className="mt-3 space-y-2 text-sm leading-relaxed text-stone-700">
                    {planRules.map((rule) => (
                      <li key={rule} className="flex gap-2">
                        <Check className="mt-1 size-3.5 shrink-0 text-success" aria-hidden />
                        {rule}
                      </li>
                    ))}
                  </ul>
                </div>
                <div className="rounded-lg border border-gold-200 bg-gold-50/60 p-5">
                  <h3 className="font-sans text-base font-bold text-ink">Plans or the GST course?</h3>
                  <p className="mt-2 text-sm leading-relaxed text-stone-700">
                    These plans are simulator practice only, with no lessons. To learn GST with videos and working
                    files, take the{" "}
                    <Link
                      href="/course/gst-practical-mastery-registration-to-gstr-9c"
                      className="font-semibold text-navy-800 underline underline-offset-2"
                    >
                      GST Practical Mastery course
                    </Link>
                    . Its Simulator Mastery package already includes simulator access for the course&rsquo;s access
                    period, so you don&rsquo;t need a separate plan.
                  </p>
                </div>
              </div>
            </div>
          )}
        </div>
      </section>

      {/* FAQ */}
      <section className="mx-auto max-w-[900px] px-4 py-12 sm:px-6" aria-labelledby="faq-title">
        <h2 id="faq-title" className="text-h2">
          Questions
        </h2>
        <div className="mt-6 divide-y divide-line rounded-md border border-line bg-white">
          {faqs.map(([q, a]) => (
            <details key={q} className="group px-5 py-4">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                {q}
                <ChevronDown
                  className="size-4 shrink-0 text-stone-500 transition-transform group-open:rotate-180 motion-reduce:transition-none"
                  aria-hidden
                />
              </summary>
              <p className="mt-2 text-sm leading-relaxed text-stone-700">{a}</p>
            </details>
          ))}
        </div>
        <p className="mt-6 text-xs leading-relaxed text-stone-500">
          The GST simulator is an independent training tool by Erudsway Educorp LLP. It is not affiliated with, endorsed
          by or connected to GSTN, the GST portal or any government body, and uses fictional data only.
        </p>
      </section>
    </>
  );
}
