"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ShieldAlert } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { AttachmentPicker } from "@/components/help/ticket-parts";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, unwrap } from "@/lib/api/client";
import { checkFiles, postWithFiles } from "@/lib/help/api";

type Errors = Partial<Record<"category" | "subject" | "message" | "attachments" | "order_reference", string>>;

const MAX = 5000;
const selectClass = "h-11 w-full rounded-md border border-input bg-white px-3 text-[0.95rem]";

export default function NewTicketPage() {
  return <RequireAuth>{() => <NewTicket />}</RequireAuth>;
}

function NewTicket() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const options = useQuery({
    queryKey: ["support", "options"],
    queryFn: async () => (await unwrap(api.GET("/me/support/options"))).data,
    staleTime: 5 * 60_000,
  });
  const [values, setValues] = useState({
    category: "",
    course_id: "",
    module: "",
    subject: "",
    order_reference: "",
    message: "",
  });
  const [files, setFiles] = useState<File[]>([]);
  const [confirmed, setConfirmed] = useState(false);
  const [errors, setErrors] = useState<Errors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set =
    (key: keyof typeof values) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
      setValues((v) => ({ ...v, [key]: e.target.value }));
      setErrors((er) => ({ ...er, [key]: undefined }));
    };

  const validate = (): Errors => {
    const e: Errors = {};
    if (!values.category) e.category = "Choose what your request is about.";
    if (values.subject.trim().length < 4) e.subject = "Give your request a short title.";
    if (values.message.trim().length < 10) e.message = "Describe the problem (at least 10 characters).";
    const fileProblem = checkFiles(files);
    if (fileProblem) e.attachments = fileProblem;
    return e;
  };

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setFormError(null);
    const found = validate();
    setErrors(found);
    if (Object.keys(found).length) return;
    if (!confirmed) {
      setFormError("Please confirm you haven’t included passwords, OTPs or card details.");
      return;
    }
    setBusy(true);
    try {
      const res = await postWithFiles<{ data: { id: string; number: string } }>(
        "/me/support/tickets",
        {
          category: values.category,
          course_id: values.course_id || null,
          module: values.module.trim() || null,
          subject: values.subject.trim(),
          order_reference: values.order_reference.trim() || null,
          message: values.message.trim(),
        },
        files,
      );
      await queryClient.invalidateQueries({ queryKey: ["support", "tickets"] });
      try {
        sessionStorage.setItem("support-ticket-created", res.data.id);
      } catch {
        // The confirmation banner is a nicety; the ticket exists either way.
      }
      router.push(`/help/tickets/${res.data.id}`);
    } catch (e) {
      if (e instanceof ApiRequestError) {
        const fieldErrors: Errors = {};
        for (const [k, v] of Object.entries(e.fields)) {
          const key = (k.startsWith("attachments") ? "attachments" : k) as keyof Errors;
          fieldErrors[key] ??= v[0];
        }
        setErrors(fieldErrors);
        setFormError(
          e.status === 429
            ? "You’ve raised several tickets recently. Please wait a while, or reply on an existing ticket."
            : Object.keys(fieldErrors).length
              ? null
              : e.message,
        );
      } else {
        setFormError("We couldn’t send your ticket. Please try again.");
      }
      setBusy(false);
    }
  };

  const courses = options.data?.courses ?? [];

  return (
    <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
      <nav aria-label="Breadcrumb" className="text-sm text-stone-500">
        <Link href="/help" className="hover:text-navy-800">
          Help Centre
        </Link>{" "}
        /{" "}
        <Link href="/help/tickets" className="hover:text-navy-800">
          My tickets
        </Link>{" "}
        / New
      </nav>
      <h1 className="mt-3 text-h1">Raise a support ticket</h1>
      <p className="mt-2 text-stone-600">
        Tell us what happened, where, and what you&rsquo;ve already tried. One ticket per issue helps us answer faster.
      </p>
      <p className="mt-4 flex gap-2 rounded-md bg-gold-50 px-4 py-3 text-sm text-stone-800">
        <ShieldAlert className="mt-0.5 size-4 shrink-0 text-gold-600" aria-hidden />
        Never include passwords, OTPs, card numbers, UPI PINs or GST/TDS portal credentials, in the text or in
        screenshots.
      </p>

      <form onSubmit={submit} noValidate className="mt-8 space-y-5 rounded-md border border-line bg-white p-5 sm:p-7">
        {formError && <FormAlert>{formError}</FormAlert>}
        <div className="grid gap-5 sm:grid-cols-2">
          <Field label="What is it about?" error={errors.category}>
            {(p) => (
              <select {...p} value={values.category} onChange={set("category")} className={selectClass}>
                <option value="">Choose a topic…</option>
                {(options.data?.categories ?? []).map((c) => (
                  <option key={c.value} value={c.value}>
                    {c.label}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field label="Course" optional>
            {(p) => (
              <select {...p} value={values.course_id} onChange={set("course_id")} className={selectClass}>
                <option value="">{courses.length ? "Not about a course" : "No courses yet"}</option>
                {courses.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.title}
                  </option>
                ))}
              </select>
            )}
          </Field>
        </div>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field label="Lesson, module or screen" optional>
            {(p) => (
              <TextInput
                {...p}
                value={values.module}
                onChange={set("module")}
                maxLength={120}
                placeholder="e.g. GSTR-1, Lesson 4"
              />
            )}
          </Field>
          <Field label="Order or payment reference" optional error={errors.order_reference}>
            {(p) => (
              <TextInput
                {...p}
                value={values.order_reference}
                onChange={set("order_reference")}
                maxLength={60}
                placeholder="From Purchases or your bank"
              />
            )}
          </Field>
        </div>
        <Field label="Subject" error={errors.subject}>
          {(p) => <TextInput {...p} value={values.subject} onChange={set("subject")} maxLength={150} />}
        </Field>
        <Field
          label="Describe the problem"
          error={errors.message}
          hint={`What did you expect, what happened instead, and any error message. ${values.message.length}/${MAX}`}
        >
          {(p) => (
            <Textarea
              {...p}
              rows={7}
              value={values.message}
              onChange={set("message")}
              maxLength={MAX}
              className="bg-white"
            />
          )}
        </Field>
        <div>
          <AttachmentPicker
            files={files}
            onChange={(f) => {
              setFiles(f);
              setErrors((er) => ({ ...er, attachments: undefined }));
            }}
          />
          {errors.attachments && <p className="mt-1.5 text-sm text-destructive">{errors.attachments}</p>}
        </div>
        <label className="flex items-start gap-3 text-sm text-stone-700">
          <input
            type="checkbox"
            checked={confirmed}
            onChange={(e) => setConfirmed(e.target.checked)}
            className="mt-0.5 size-4 accent-navy-900"
          />
          I haven&rsquo;t included passwords, OTPs, card details or other secrets.
        </label>
        <div className="flex flex-wrap gap-3">
          <Button type="submit" size="lg" disabled={busy}>
            {busy ? "Sending…" : "Submit ticket"}
          </Button>
          <Link
            href="/help/tickets"
            className="inline-flex h-11 items-center px-3 text-sm font-semibold text-stone-600 hover:text-ink"
          >
            Cancel
          </Link>
        </div>
      </form>
    </div>
  );
}
