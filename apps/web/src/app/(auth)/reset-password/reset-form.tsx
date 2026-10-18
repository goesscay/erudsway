"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Field, FormAlert, PasswordInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { api, ApiRequestError, mutate } from "@/lib/api/client";
import { applyApiErrors } from "@/lib/api/form-errors";
import { resetSchema, type ResetValues } from "@/lib/validation/auth";

export function ResetForm() {
  const router = useRouter();
  const params = useSearchParams();
  const token = params.get("token") ?? "";
  const email = params.get("email") ?? "";
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<ResetValues>({
    resolver: zodResolver(resetSchema),
    defaultValues: { password: "", password_confirmation: "" },
  });
  const { errors, isSubmitting } = form.formState;

  if (!token || !email) {
    return (
      <FormAlert>
        This reset link is incomplete. <Link href="/forgot-password" className="font-semibold underline">Request a new one</Link>.
      </FormAlert>
    );
  }

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      await mutate(() => api.POST("/auth/reset-password", { body: { ...values, token, email } }));
      router.replace("/login?reset=1");
    } catch (e) {
      if (e instanceof ApiRequestError && e.fields.token) {
        setFormError(e.fields.token[0] ?? e.message);
        return;
      }
      setFormError(applyApiErrors(e, form.setError, ["password", "password_confirmation"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-5">
      {formError && (
        <FormAlert>
          {formError}{" "}
          <Link href="/forgot-password" className="font-semibold underline">
            Request a new link
          </Link>
        </FormAlert>
      )}
      <p className="text-sm text-stone-700">
        Resetting the password for <strong className="text-ink">{email}</strong>
      </p>
      <Field label="New password" error={errors.password?.message} hint="At least 8 characters, with a letter and a number.">
        {(p) => <PasswordInput {...p} autoComplete="new-password" autoFocus {...form.register("password")} />}
      </Field>
      <Field label="Confirm new password" error={errors.password_confirmation?.message}>
        {(p) => <PasswordInput {...p} autoComplete="new-password" {...form.register("password_confirmation")} />}
      </Field>
      <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
        {isSubmitting ? "Saving…" : "Set new password"}
      </Button>
    </form>
  );
}
