import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { LoginForm } from "./login-form";

export const metadata: Metadata = { title: "Log in" };

export default function LoginPage() {
  return (
    <>
      <h1 className="text-h2">Welcome back</h1>
      <p className="mt-2 mb-8 text-sm text-stone-700">
        New to Erudsway?{" "}
        <Link href="/register" className="font-semibold text-navy-800 underline-offset-2 hover:underline">
          Create an account
        </Link>
      </p>
      <Suspense>
        <LoginForm />
      </Suspense>
    </>
  );
}
