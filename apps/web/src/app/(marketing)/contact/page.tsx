import type { Metadata } from "next";
import Link from "next/link";
import { ArrowUpRight, Clock, Mail, MapPin, MessageCircle, Phone, ShieldCheck } from "lucide-react";
import { ADDRESS, MAP_HREF, PHONE, PHONE_HREF, SUPPORT_EMAIL, WHATSAPP_HREF } from "@/lib/contact";
import { ContactForm } from "./contact-form";

export const metadata: Metadata = {
  title: "Contact us",
  description:
    "Talk to the Erudsway team about courses, payments, the GST simulator, teaching or partnerships. Email, phone, WhatsApp or send us a message.",
};

const channels = [
  {
    icon: Mail,
    title: "Email",
    value: SUPPORT_EMAIL,
    href: `mailto:${SUPPORT_EMAIL}`,
    note: "For courses, orders, invoices and account help.",
  },
  {
    icon: Phone,
    title: "Phone",
    value: PHONE,
    href: PHONE_HREF,
    note: "Call us during working hours.",
  },
  {
    icon: MessageCircle,
    title: "WhatsApp",
    value: PHONE,
    href: WHATSAPP_HREF,
    note: "Message us any time; we reply in working hours.",
    external: true,
  },
];

const helpful = [
  ["Refunds, cancellations and grievances", "/policies#refund"],
  ["Start the free GST simulator trial", "/gst-simulator"],
  ["Teach or contribute a course", "/teach"],
  ["Browse all courses", "/courses"],
] as const;

export default function ContactPage() {
  return (
    <>
      <section className="dark bg-navy-950 text-foreground" aria-labelledby="contact-title">
        <div className="mx-auto max-w-[1340px] px-4 py-12 sm:px-6 lg:py-16">
          <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">Contact us</p>
          <h1
            id="contact-title"
            className="max-w-[20ch] text-[2.3rem] leading-[1.08] tracking-[-0.02em] sm:text-[3rem]"
          >
            We&rsquo;re here to help.
          </h1>
          <p className="mt-4 max-w-[58ch] text-lg leading-relaxed text-white/80">
            Questions about a course, a payment, the GST simulator or teaching with us? Write to us and a member of the
            Erudsway team will get back to you, usually within one working day.
          </p>
        </div>
      </section>

      <div className="mx-auto grid max-w-[1340px] gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_1.15fr] lg:py-16">
        {/* Ways to reach us */}
        <div className="space-y-8">
          <section aria-labelledby="reach-title">
            <h2 id="reach-title" className="font-sans text-lg font-bold text-navy-900">
              Reach us directly
            </h2>
            <ul className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
              {channels.map(({ icon: Icon, title, value, href, note, external }) => (
                <li key={title} className="rounded-xl border border-line bg-white p-5">
                  <span className="flex items-center gap-2 text-xs font-bold tracking-wide text-stone-500 uppercase">
                    <Icon className="size-4 text-gold-600" aria-hidden /> {title}
                  </span>
                  <a
                    href={href}
                    {...(external ? { target: "_blank", rel: "noreferrer" } : {})}
                    className="mt-2 block font-semibold break-all text-navy-900 hover:underline"
                  >
                    {value}
                  </a>
                  <span className="mt-1 block text-xs text-stone-500">{note}</span>
                </li>
              ))}
              <li className="rounded-xl border border-line bg-white p-5 sm:col-span-2 lg:col-span-1 xl:col-span-2">
                <span className="flex items-center gap-2 text-xs font-bold tracking-wide text-stone-500 uppercase">
                  <MapPin className="size-4 text-gold-600" aria-hidden /> Office
                </span>
                <address className="mt-2 text-sm leading-relaxed text-stone-700 not-italic">
                  <strong className="block text-navy-900">Erudsway Educorp LLP</strong>
                  {ADDRESS}
                </address>
                <a
                  href={MAP_HREF}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-navy-800 hover:underline"
                >
                  Open in Google Maps <ArrowUpRight className="size-3.5" aria-hidden />
                </a>
              </li>
            </ul>
          </section>

          <section aria-labelledby="helpful-title" className="rounded-xl bg-paper-2/70 p-5">
            <h2 id="helpful-title" className="font-sans text-sm font-bold text-navy-900">
              You might find it faster here
            </h2>
            <ul className="mt-3 space-y-2 text-sm">
              {helpful.map(([label, href]) => (
                <li key={href}>
                  <Link href={href} className="font-medium text-navy-800 hover:underline">
                    {label}
                  </Link>
                </li>
              ))}
            </ul>
          </section>

          <ul className="space-y-2 text-sm text-stone-600">
            <li className="flex items-center gap-2">
              <Clock className="size-4 text-gold-600" aria-hidden /> We usually reply within one working day.
            </li>
            <li className="flex items-center gap-2">
              <ShieldCheck className="size-4 text-gold-600" aria-hidden /> Please don&rsquo;t send passwords or card
              details.
            </li>
          </ul>
        </div>

        {/* Message form */}
        <section aria-labelledby="form-title" className="rounded-2xl border border-line bg-white p-6 sm:p-8">
          <h2 id="form-title" className="font-sans text-lg font-bold text-navy-900">
            Send us a message
          </h2>
          <p className="mt-1 mb-6 text-sm text-stone-600">It goes straight to our support team.</p>
          <ContactForm />
        </section>
      </div>
    </>
  );
}
