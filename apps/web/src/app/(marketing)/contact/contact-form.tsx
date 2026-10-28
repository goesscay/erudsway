"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2 } from "lucide-react";
import type { paths } from "@erudsway/api-client";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { isIndianMobile, mobileMessage } from "@/lib/validation/auth";

type Body = paths["/contact"]["post"]["requestBody"]["content"]["application/json"];
type Topic = Body["topic"];
type Errors = Partial<Record<"name" | "email" | "phone" | "topic" | "message", string>>;

const MAX = 3000;

export function ContactForm() {
  const { data: me } = useMe();
  const topics = useQuery({
    queryKey: ["contact-topics"],
    queryFn: async () => (await unwrap(api.GET("/contact/topics"))).data.items,
    staleTime: Infinity,
  });
  const [values, setValues] = useState({ name: "", email: "", phone: "", topic: "", message: "", website: "" });
  const [touchedMe, setTouchedMe] = useState(false);
  const [errors, setErrors] = useState<Errors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState<string | null>(null);

  // Signed-in learners: start from their own name and email.
  if (me && !touchedMe && !values.name && !values.email) {
    setTouchedMe(true);
    setValues((v) => ({ ...v, name: me.name, email: me.email }));
  }

  const set =
    (key: keyof typeof values) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
      setValues((v) => ({ ...v, [key]: e.target.value }));
      setErrors((er) => ({ ...er, [key]: undefined }));
    };

  const validate = (): Errors => {
    const e: Errors = {};
    if (values.name.trim().length < 2) e.name = "Enter your name.";
    if (!/^\S+@\S+\.\S+$/.test(values.email.trim())) e.email = "Enter a valid email address.";
    if (values.phone.trim() && !isIndianMobile(values.phone.trim())) e.phone = mobileMessage;
    if (!values.topic) e.topic = "Choose what your message is about.";
    if (values.message.trim().length < 10) e.message = "Tell us a little more (at least 10 characters).";
    return e;
  };

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setFormError(null);
    const found = validate();
    setErrors(found);
    if (Object.keys(found).length) return;
    setBusy(true);
    try {
      const res = await mutate(() =>
        api.POST("/contact", {
          body: {
            name: values.name.trim(),
            email: values.email.trim(),
            phone: values.phone.trim() || null,
            topic: values.topic as Topic,
            message: values.message.trim(),
            website: values.website || null,
          },
        }),
      );
      setSent(res.data.message);
    } catch (e) {
      if (e instanceof ApiRequestError) {
        const fieldErrors = Object.fromEntries(Object.entries(e.fields).map(([k, v]) => [k, v[0]])) as Errors;
        setErrors(fieldErrors);
        setFormError(
          e.status === 429
            ? "You’ve sent several messages already. Please wait a while, or email us directly."
            : Object.keys(fieldErrors).length
              ? null
              : e.message,
        );
      } else {
        setFormError("We couldn’t send your message. Please try again, or email us directly.");
      }
    } finally {
      setBusy(false);
    }
  };

  if (sent) {
    return (
      <div className="space-y-4" role="status">
        <FormAlert tone="success">
          <span className="flex items-start gap-2">
            <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden />
            {sent}
          </span>
        </FormAlert>
        <Button
          variant="outline"
          onClick={() => {
            setSent(null);
            setValues((v) => ({ ...v, topic: "", message: "" }));
          }}
        >
          Send another message
        </Button>
      </div>
    );
  }

  return (
    <form onSubmit={submit} noValidate className="space-y-5">
      {formError && <FormAlert>{formError}</FormAlert>}
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Your name" error={errors.name}>
          {(p) => <TextInput {...p} autoComplete="name" value={values.name} onChange={set("name")} maxLength={80} />}
        </Field>
        <Field label="Email" error={errors.email}>
          {(p) => (
            <TextInput
              {...p}
              type="email"
              autoComplete="email"
              value={values.email}
              onChange={set("email")}
              maxLength={190}
            />
          )}
        </Field>
      </div>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Mobile number" optional error={errors.phone}>
          {(p) => (
            <TextInput
              {...p}
              type="tel"
              inputMode="tel"
              autoComplete="tel"
              placeholder="98765 43210"
              value={values.phone}
              onChange={set("phone")}
              maxLength={20}
            />
          )}
        </Field>
        <Field label="What is it about?" error={errors.topic}>
          {(p) => (
            <select
              {...p}
              value={values.topic}
              onChange={set("topic")}
              className="h-11 w-full rounded-md border border-input bg-white px-3 text-[0.95rem]"
            >
              <option value="">Choose a topic…</option>
              {(topics.data ?? []).map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
          )}
        </Field>
      </div>
      <Field
        label="Message"
        error={errors.message}
        hint={`Include your order number or course name if it’s about a purchase. ${values.message.length}/${MAX}`}
      >
        {(p) => (
          <Textarea
            {...p}
            rows={6}
            value={values.message}
            onChange={set("message")}
            maxLength={MAX}
            className="bg-white"
          />
        )}
      </Field>
      {/* A trap for bots: hidden from people and screen readers. */}
      <div aria-hidden className="absolute -left-[9999px] h-px w-px overflow-hidden">
        <label>
          Website
          <input tabIndex={-1} autoComplete="off" value={values.website} onChange={set("website")} />
        </label>
      </div>
      <Button type="submit" size="lg" className="w-full sm:w-auto" disabled={busy}>
        {busy ? "Sending…" : "Send message"}
      </Button>
      <p className="text-xs text-stone-500">
        We use your details only to reply to this message. See our{" "}
        <a href="/policies#privacy" className="font-semibold text-navy-800 underline underline-offset-2">
          Privacy Policy
        </a>
        .
      </p>
    </form>
  );
}
