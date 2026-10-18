import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { RegisterForm } from "./register-form";

export const metadata: Metadata = { title: "Create your account" };

export default function RegisterPage() {
  return (
    <>
      <h1 className="text-h2">Start learning today</h1>
      <p className="mt-2 mb-8 text-sm text-stone-700">
        Already have an account?{" "}
        <Link href="/login" className="font-semibold text-navy-800 underline-offset-2 hover:underline">
          Log in
        </Link>
      </p>
      <Suspense>
        <RegisterForm />
      </Suspense>
    </>
  );
}
