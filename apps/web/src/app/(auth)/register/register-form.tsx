"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Field, FormAlert, PasswordInput, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { applyApiErrors } from "@/lib/api/form-errors";
import { safeNext, useSession } from "@/lib/auth/session";
import { registerSchema, type RegisterValues } from "@/lib/validation/auth";

export function RegisterForm() {
  const router = useRouter();
  const params = useSearchParams();
  const session = useSession();
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: "", email: "", password: "", password_confirmation: "", accept_terms: false },
  });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      await session.register(values);
      router.replace(`/verify-email?next=${encodeURIComponent(safeNext(params.get("next")))}`);
    } catch (e) {
      setFormError(
        applyApiErrors(e, form.setError, ["name", "email", "password", "password_confirmation", "accept_terms"]),
      );
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-5">
      {formError && <FormAlert>{formError}</FormAlert>}

      <Field label="Full name" error={errors.name?.message}>
        {(p) => <TextInput {...p} autoComplete="name" autoFocus {...form.register("name")} />}
      </Field>

      <Field label="Email" error={errors.email?.message}>
        {(p) => <TextInput {...p} type="email" autoComplete="email" {...form.register("email")} />}
      </Field>

      <Field
        label="Password"
        error={errors.password?.message}
        hint="At least 8 characters, with a letter and a number."
      >
        {(p) => <PasswordInput {...p} autoComplete="new-password" {...form.register("password")} />}
      </Field>

      <Field label="Confirm password" error={errors.password_confirmation?.message}>
        {(p) => <PasswordInput {...p} autoComplete="new-password" {...form.register("password_confirmation")} />}
      </Field>

      <div>
        <label className="flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-stone-700">
          <input
            type="checkbox"
            className="mt-1 size-4 shrink-0 accent-[var(--navy-900)]"
            aria-invalid={errors.accept_terms ? true : undefined}
            aria-describedby={errors.accept_terms ? "accept-terms-error" : undefined}
            {...form.register("accept_terms")}
          />
          <span>
            I am 18 or older and I accept the{" "}
            <a
              href="/policies#terms"
              target="_blank"
              className="font-semibold text-navy-800 underline underline-offset-2"
            >
              Terms &amp; Conditions
            </a>{" "}
            and{" "}
            <a
              href="/policies#privacy"
              target="_blank"
              className="font-semibold text-navy-800 underline underline-offset-2"
            >
              Privacy Policy
            </a>
            .
          </span>
        </label>
        {errors.accept_terms && (
          <p id="accept-terms-error" role="alert" className="mt-1.5 text-xs font-medium text-destructive">
            {errors.accept_terms.message}
          </p>
        )}
      </div>

      <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
        {isSubmitting ? "Creating your account…" : "Create account"}
      </Button>
    </form>
  );
}
