"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { RequireAuth } from "@/components/auth/require-auth";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { NotificationPreferences } from "@/components/engagement/notification-preferences";
import { Field, FormAlert, PasswordInput, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { api, mutate } from "@/lib/api/client";
import { applyApiErrors } from "@/lib/api/form-errors";
import { useSession, type CurrentUser } from "@/lib/auth/session";
import {
  changePasswordSchema,
  profileSchema,
  type ChangePasswordValues,
  type ProfileValues,
} from "@/lib/validation/auth";

export function AccountSettings() {
  return (
    <RequireAuth>
      {(me) => (
        <div className="mx-auto max-w-4xl px-6 py-12">
          <div className="flex items-center gap-5 border-b border-line pb-8">
            <InstructorAvatar name={me.name} className="size-16 text-xl" />
            <div>
              <h1 className="text-h2">Account settings</h1>
              <p className="text-sm text-stone-500">
                {me.email} · member since{" "}
                {new Date(me.created_at).toLocaleDateString("en-IN", { month: "long", year: "numeric" })}
              </p>
            </div>
          </div>
          <div className="divide-y divide-line">
            <Section title="Profile" description="Your name appears on certificates and course reviews.">
              <ProfileForm me={me} />
            </Section>
            <Section title="Password" description="Use a password you don't use on other sites.">
              <PasswordForm />
            </Section>
            <Section
              id="notifications"
              title="Notifications"
              description="Choose what we email you about. Your Erudsway inbox follows the same choices."
            >
              <NotificationPreferences instructor={!!me.instructor} />
            </Section>
          </div>
        </div>
      )}
    </RequireAuth>
  );
}

function Section({
  id,
  title,
  description,
  children,
}: {
  id?: string;
  title: string;
  description: string;
  children: React.ReactNode;
}) {
  return (
    <section id={id} className="grid scroll-mt-24 gap-6 py-10 md:grid-cols-[240px_1fr]">
      <div>
        <h2 className="font-sans text-base font-bold text-ink">{title}</h2>
        <p className="mt-1 text-sm text-stone-500">{description}</p>
      </div>
      <div className="max-w-lg">{children}</div>
    </section>
  );
}

export function ProfileForm({ me }: { me: CurrentUser }) {
  const session = useSession();
  const [notice, setNotice] = useState<{ tone: "success" | "error"; text: string } | null>(null);
  const form = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      name: me.name,
      headline: me.profile.headline ?? "",
      bio: me.profile.bio ?? "",
      phone: me.profile.phone ?? "",
    },
  });
  const { errors, isSubmitting, isDirty } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setNotice(null);
    try {
      const res = await mutate(() =>
        api.PATCH("/me", {
          body: {
            name: values.name,
            headline: values.headline || null,
            bio: values.bio || null,
            phone: values.phone || null,
          },
        }),
      );
      session.setUser(res.data);
      form.reset(values);
      setNotice({ tone: "success", text: "Profile saved." });
    } catch (e) {
      const msg = applyApiErrors(e, form.setError, ["name", "headline", "bio", "phone"]);
      if (msg) setNotice({ tone: "error", text: msg });
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-5">
      {notice && <FormAlert tone={notice.tone}>{notice.text}</FormAlert>}
      <Field label="Full name" error={errors.name?.message}>
        {(p) => <TextInput {...p} autoComplete="name" {...form.register("name")} />}
      </Field>
      <Field label="Headline" optional error={errors.headline?.message} hint="e.g. Final-year B.Com student, Pune">
        {(p) => <TextInput {...p} {...form.register("headline")} />}
      </Field>
      <Field label="About you" optional error={errors.bio?.message}>
        {(p) => <Textarea {...p} rows={4} className="bg-white" {...form.register("bio")} />}
      </Field>
      <Field label="Mobile number" optional error={errors.phone?.message}>
        {(p) => <TextInput {...p} type="tel" autoComplete="tel" placeholder="+91" {...form.register("phone")} />}
      </Field>
      <Button type="submit" disabled={isSubmitting || !isDirty}>
        {isSubmitting ? "Saving…" : "Save profile"}
      </Button>
    </form>
  );
}

export function PasswordForm() {
  const [notice, setNotice] = useState<{ tone: "success" | "error"; text: string } | null>(null);
  const form = useForm<ChangePasswordValues>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: { current_password: "", password: "", password_confirmation: "" },
  });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setNotice(null);
    try {
      await mutate(() => api.PUT("/me/password", { body: values }));
      form.reset();
      setNotice({ tone: "success", text: "Password changed." });
    } catch (e) {
      const msg = applyApiErrors(e, form.setError, ["current_password", "password", "password_confirmation"]);
      if (msg) setNotice({ tone: "error", text: msg });
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-5">
      {notice && <FormAlert tone={notice.tone}>{notice.text}</FormAlert>}
      <Field label="Current password" error={errors.current_password?.message}>
        {(p) => <PasswordInput {...p} autoComplete="current-password" {...form.register("current_password")} />}
      </Field>
      <Field
        label="New password"
        error={errors.password?.message}
        hint="At least 8 characters, with a letter and a number."
      >
        {(p) => <PasswordInput {...p} autoComplete="new-password" {...form.register("password")} />}
      </Field>
      <Field label="Confirm new password" error={errors.password_confirmation?.message}>
        {(p) => <PasswordInput {...p} autoComplete="new-password" {...form.register("password_confirmation")} />}
      </Field>
      <Button type="submit" variant="outline" disabled={isSubmitting}>
        {isSubmitting ? "Updating…" : "Change password"}
      </Button>
    </form>
  );
}
