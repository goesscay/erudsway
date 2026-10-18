"use client";

import Link from "next/link";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft, MailCheck } from "lucide-react";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { api, mutate } from "@/lib/api/client";
import { applyApiErrors } from "@/lib/api/form-errors";
import { forgotSchema, type ForgotValues } from "@/lib/validation/auth";

export default function ForgotPasswordPage() {
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<ForgotValues>({ resolver: zodResolver(forgotSchema), defaultValues: { email: "" } });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async ({ email }) => {
    setFormError(null);
    try {
      await mutate(() => api.POST("/auth/forgot-password", { body: { email } }));
      setSentTo(email);
    } catch (e) {
      setFormError(applyApiErrors(e, form.setError, ["email"]));
    }
  });

  if (sentTo) {
    return (
      <div>
        <MailCheck className="size-10 text-gold-600" strokeWidth={1.5} />
        <h1 className="text-h2 mt-5">Check your inbox</h1>
        <p className="mt-3 text-sm leading-relaxed text-stone-700">
          If an account exists for <strong className="text-ink">{sentTo}</strong>, we&rsquo;ve sent a link to reset your
          password. It expires in 60 minutes.
        </p>
        <p className="mt-6 text-sm text-stone-700">
          Didn&rsquo;t get it? Check spam, or{" "}
          <button type="button" onClick={() => setSentTo(null)} className="font-semibold text-navy-800 hover:underline">
            try another email
          </button>
          .
        </p>
      </div>
    );
  }

  return (
    <>
      <Link href="/login" className="mb-8 inline-flex items-center gap-1.5 text-sm font-semibold text-stone-700 hover:text-navy-900">
        <ArrowLeft className="size-4" /> Back to log in
      </Link>
      <h1 className="text-h2">Forgot your password?</h1>
      <p className="mt-2 mb-8 text-sm text-stone-700">Enter your email and we&rsquo;ll send you a reset link.</p>
      <form onSubmit={onSubmit} noValidate className="space-y-5">
        {formError && <FormAlert>{formError}</FormAlert>}
        <Field label="Email" error={errors.email?.message}>
          {(p) => <TextInput {...p} type="email" autoComplete="email" autoFocus {...form.register("email")} />}
        </Field>
        <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
          {isSubmitting ? "Sending…" : "Send reset link"}
        </Button>
      </form>
    </>
  );
}
