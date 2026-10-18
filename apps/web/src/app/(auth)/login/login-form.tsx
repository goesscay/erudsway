"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Field, FormAlert, PasswordInput, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { applyApiErrors } from "@/lib/api/form-errors";
import { safeNext, useSession } from "@/lib/auth/session";
import { loginSchema, type LoginValues } from "@/lib/validation/auth";

export function LoginForm() {
  const router = useRouter();
  const params = useSearchParams();
  const session = useSession();
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "", remember: true },
  });
  const { errors, isSubmitting } = form.formState;
  const remember = useWatch({ control: form.control, name: "remember" });

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      await session.login(values);
      router.replace(safeNext(params.get("next")));
    } catch (e) {
      setFormError(applyApiErrors(e, form.setError, ["email", "password"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-5">
      {params.get("reset") === "1" && <FormAlert tone="success">Password updated. Log in with your new password.</FormAlert>}
      {formError && <FormAlert>{formError}</FormAlert>}

      <Field label="Email" error={errors.email?.message}>
        {(p) => <TextInput {...p} type="email" autoComplete="email" autoFocus {...form.register("email")} />}
      </Field>

      <Field label="Password" error={errors.password?.message}>
        {(p) => <PasswordInput {...p} autoComplete="current-password" {...form.register("password")} />}
      </Field>

      <div className="flex items-center justify-between">
        <Label className="flex items-center gap-2 text-sm font-normal text-stone-700">
          <Checkbox
            checked={remember}
            onCheckedChange={(v) => form.setValue("remember", v === true)}
          />
          Keep me signed in
        </Label>
        <Link href="/forgot-password" className="text-sm font-semibold text-navy-800 hover:underline">
          Forgot password?
        </Link>
      </div>

      <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
        {isSubmitting ? "Signing in…" : "Log in"}
      </Button>
    </form>
  );
}
