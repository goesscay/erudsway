"use client";

import { useState } from "react";
import { Check, Link2 } from "lucide-react";
import { Button, buttonVariants } from "@/components/ui/button";

/** Copy the public link, or add the certificate to a LinkedIn profile. */
export function CertificateShare({
  url,
  courseTitle,
  issuedAt,
  code,
}: {
  url: string;
  courseTitle: string;
  issuedAt: string;
  code: string;
}) {
  const [copied, setCopied] = useState(false);
  const issued = new Date(issuedAt);
  const linkedIn = new URL("https://www.linkedin.com/profile/add");
  linkedIn.search = new URLSearchParams({
    startTask: "CERTIFICATION_NAME",
    name: courseTitle,
    organizationName: "Erudsway Educorp LLP",
    issueYear: String(issued.getFullYear()),
    issueMonth: String(issued.getMonth() + 1),
    certUrl: url,
    certId: code,
  }).toString();

  return (
    <>
      <a
        href={linkedIn.toString()}
        target="_blank"
        rel="noopener noreferrer"
        className={buttonVariants({ variant: "outline", className: "w-full" })}
      >
        Add to LinkedIn profile
      </a>
      <Button
        variant="ghost"
        className="w-full"
        onClick={async () => {
          try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
          } catch {
            window.prompt("Copy this link:", url);
          }
        }}
      >
        {copied ? <Check /> : <Link2 />} {copied ? "Link copied" : "Copy link"}
      </Button>
    </>
  );
}
