"use client";

import Image from "next/image";
import { useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ImageUp } from "lucide-react";
import { RichTextEditor } from "@/components/authoring/rich-text-editor";
import { SaveBar } from "@/components/authoring/save-bar";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { api, ApiRequestError, unwrap } from "@/lib/api/client";
import { postForm, useEditorAction, type CourseEditor } from "@/lib/authoring/editor";
import { levelLabel } from "@/lib/catalog/types";
import { StepHeader, StepSkeleton, useEditableCourse } from "../builder-shell";

const languages = ["English", "Hindi", "Tamil", "Telugu", "Kannada", "Malayalam", "Marathi", "Bengali", "Gujarati"];

export default function LandingStep() {
  const { course } = useEditableCourse();
  if (!course) return <StepSkeleton />;
  return <LandingForm key={course.id} course={course} />;
}

function LandingForm({ course }: { course: CourseEditor }) {
  const initial = {
    title: course.title,
    subtitle: course.subtitle ?? "",
    description: course.description ?? "",
    language: course.language,
    level: course.level,
    category: course.category.slug,
  };
  const [values, setValues] = useState(initial);
  const [baseline, setBaseline] = useState(initial);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const locked = course.status === "in_review" || course.status === "archived";
  const set = <K extends keyof typeof values>(key: K, value: (typeof values)[K]) => setValues((v) => ({ ...v, [key]: value }));
  const dirty = JSON.stringify(values) !== JSON.stringify(baseline);

  const categories = useQuery({ queryKey: ["categories"], queryFn: async () => (await unwrap(api.GET("/categories"))).data });

  const save = useEditorAction(course.id, () =>
    unwrap(
      api.PATCH("/instructor/courses/{course}", {
        params: { path: { course: course.id } },
        body: {
          title: values.title.trim(),
          subtitle: values.subtitle.trim() || null,
          description: values.description || null,
          language: values.language as "English",
          level: values.level as "all",
          category: values.category,
        },
      }),
    ),
  );

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        setError(null);
        setFieldErrors({});
        save.mutate(undefined, {
          onSuccess: () => setBaseline(values),
          onError: (err) => {
            if (err instanceof ApiRequestError && Object.keys(err.fields).length) {
              setFieldErrors(Object.fromEntries(Object.entries(err.fields).map(([k, v]) => [k, v[0] ?? ""])));
            } else {
              setError(err instanceof ApiRequestError ? err.message : "Couldn’t save.");
            }
          },
        });
      }}
    >
      <StepHeader title="Landing page">
        This is what learners see before they enroll. Lead with the outcome, not with yourself.
      </StepHeader>

      <fieldset disabled={locked} className="max-w-2xl space-y-6">
        <Field label="Course title" error={fieldErrors.title} hint={`${values.title.length}/120 · say exactly what learners will master`}>
          {(p) => <TextInput {...p} value={values.title} maxLength={120} onChange={(e) => set("title", e.target.value)} />}
        </Field>
        <Field label="Subtitle" error={fieldErrors.subtitle} hint={`${values.subtitle.length}/200 · one sentence on scope and approach`}>
          {(p) => <TextInput {...p} value={values.subtitle} maxLength={200} onChange={(e) => set("subtitle", e.target.value)} />}
        </Field>
        <Field label="Description" error={fieldErrors.description} hint="At least 200 characters. What is covered, how it is taught, and what makes it practical.">
          {(p) => <RichTextEditor id={p.id} aria-describedby={p["aria-describedby"]} value={values.description} onChange={(html) => set("description", html)} />}
        </Field>

        <div className="grid gap-6 sm:grid-cols-3">
          <Field label="Subcategory" error={fieldErrors.category}>
            {(p) => (
              <select {...p} value={values.category} onChange={(e) => set("category", e.target.value)} className="h-11 w-full rounded-md border border-input bg-white px-3 text-sm">
                {categories.data?.map((c) => (
                  <optgroup key={c.slug} label={c.name}>
                    {c.children?.map((s) => (
                      <option key={s.slug} value={s.slug}>
                        {s.name}
                      </option>
                    ))}
                  </optgroup>
                )) ?? <option value={values.category}>{course.category.name}</option>}
              </select>
            )}
          </Field>
          <Field label="Language" error={fieldErrors.language}>
            {(p) => (
              <select {...p} value={values.language} onChange={(e) => set("language", e.target.value)} className="h-11 w-full rounded-md border border-input bg-white px-3 text-sm">
                {languages.map((l) => (
                  <option key={l}>{l}</option>
                ))}
              </select>
            )}
          </Field>
          <Field label="Level" error={fieldErrors.level}>
            {(p) => (
              <select {...p} value={values.level} onChange={(e) => set("level", e.target.value)} className="h-11 w-full rounded-md border border-input bg-white px-3 text-sm">
                {Object.entries(levelLabel).map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </select>
            )}
          </Field>
        </div>
      </fieldset>

      <ThumbnailUploader course={course} locked={locked} />

      <SaveBar dirty={dirty} saving={save.isPending} saved={save.isSuccess && !dirty} error={error} locked={locked} />
    </form>
  );
}

function ThumbnailUploader({ course, locked }: { course: CourseEditor; locked: boolean }) {
  const input = useRef<HTMLInputElement>(null);
  const [error, setError] = useState<string | null>(null);
  const upload = useEditorAction(course.id, (file: File) => {
    const form = new FormData();
    form.append("image", file);
    return postForm<{ data: CourseEditor }>(`/instructor/courses/${course.id}/thumbnail`, form);
  });

  return (
    <section className="mt-10 max-w-2xl">
      <h2 className="font-sans text-base font-bold text-ink">Course image</h2>
      <p className="mt-1 mb-4 text-sm text-stone-500">JPEG, PNG or WebP, at least 750 × 422. We crop to 16:9 and optimise it.</p>
      {error && (
        <div className="mb-3">
          <FormAlert>{error}</FormAlert>
        </div>
      )}
      <div className="flex flex-wrap items-center gap-5">
        <div className="relative aspect-video w-72 overflow-hidden rounded-md border border-line bg-paper-2">
          {course.thumbnail_url ? (
            <Image src={course.thumbnail_url} alt="Current course image" fill sizes="288px" className="object-cover" />
          ) : (
            <span className="absolute inset-0 flex items-center justify-center text-xs text-stone-500">No image yet</span>
          )}
        </div>
        <div>
          <input
            ref={input}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={(e) => {
              const file = e.target.files?.[0];
              e.target.value = "";
              if (!file) return;
              setError(null);
              upload.mutate(file, { onError: (err) => setError(err instanceof ApiRequestError ? (Object.values(err.fields)[0]?.[0] ?? err.message) : "Upload failed.") });
            }}
          />
          <Button type="button" variant="outline" disabled={locked || upload.isPending} onClick={() => input.current?.click()}>
            <ImageUp /> {upload.isPending ? "Uploading…" : course.thumbnail_url ? "Replace image" : "Upload image"}
          </Button>
        </div>
      </div>
    </section>
  );
}
