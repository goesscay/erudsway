import type { Metadata } from "next";
import { Suspense } from "react";
import { RegisterForm } from "./register-form";
import { RegisterIntro } from "./register-intro";

export const metadata: Metadata = { title: "Create your account" };

export default function RegisterPage() {
  return (
    <>
      <Suspense fallback={<RegisterIntro.Fallback />}>
        <RegisterIntro />
      </Suspense>
      <Suspense>
        <RegisterForm />
      </Suspense>
    </>
  );
}
