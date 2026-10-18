"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { CircleCheck, CircleX, Mail } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { api, ApiRequestError, mutate } from "@/lib/api/client";
import { meKey, safeNext, useMe } from "@/lib/auth/session";

export function VerifyStatus() {
  const params = useSearchParams();
  const status = params.get("status");
  const next = safeNext(params.get("next"));
  const { data: me } = useMe();
  const queryClient = useQueryClient();
  const [notice, setNotice] = useState<{ tone: "success" | "error"; text: string } | null>(null);
  const [sending, setSending] = useState(false);

  // The verification happened on the API; refresh the cached user so "verify" banners disappear.
  useEffect(() => {
    if (status === "verified") void queryClient.invalidateQueries({ queryKey: meKey });
  }, [status, queryClient]);

  if (status === "verified") {
    return (
      <div>
        <CircleCheck className="size-10 text-success" strokeWidth={1.5} />
        <h1 className="text-h2 mt-5">Email verified</h1>
        <p className="mt-3 text-sm text-stone-700">Thanks — your account is fully set up.</p>
        <Link href={me ? "/my-learning" : "/login"} className={buttonVariants({ size: "lg", className: "mt-8 w-full" })}>
          {me ? "Go to my learning" : "Log in"}
        </Link>
      </div>
    );
  }

  const resend = async () => {
    setSending(true);
    setNotice(null);
    try {
      const res = await mutate(() => api.POST("/auth/email/resend"));
      setNotice({ tone: "success", text: (res as { data: { message: string } }).data.message });
    } catch (e) {
      setNotice({
        tone: "error",
        text:
          e instanceof ApiRequestError && e.status === 429
            ? "Please wait a minute before requesting another email."
            : e instanceof ApiRequestError && e.status === 401
              ? "Log in to request a new link."
              : "We couldn't send the email. Please try again.",
      });
    } finally {
      setSending(false);
    }
  };

  return (
    <div>
      {status === "invalid" ? (
        <CircleX className="size-10 text-destructive" strokeWidth={1.5} />
      ) : (
        <Mail className="size-10 text-gold-600" strokeWidth={1.5} />
      )}
      <h1 className="text-h2 mt-5">{status === "invalid" ? "That link didn't work" : "Verify your email"}</h1>
      <p className="mt-3 text-sm leading-relaxed text-stone-700">
        {status === "invalid"
          ? "The verification link is invalid or has expired. Request a fresh one below."
          : <>We sent a verification link to <strong className="text-ink">{me?.email ?? "your email"}</strong>. Click it to activate your account. The link expires in 60 minutes.</>}
      </p>

      <div className="mt-8 space-y-3">
        {notice && <FormAlert tone={notice.tone}>{notice.text}</FormAlert>}
        <Button size="lg" variant="outline" className="w-full" onClick={resend} disabled={sending}>
          {sending ? "Sending…" : "Resend verification email"}
        </Button>
        <Link href={next} className={buttonVariants({ variant: "ghost", className: "w-full" })}>
          Continue to Erudsway
        </Link>
      </div>
    </div>
  );
}
