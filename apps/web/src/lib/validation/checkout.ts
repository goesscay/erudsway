import { z } from "zod";
import type { paths } from "@erudsway/api-client";

type CheckoutBody = NonNullable<paths["/checkout"]["post"]["requestBody"]>["content"]["application/json"];

const GSTIN = /^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;

/**
 * Billing details for the GST invoice. The state is the place of supply; a GSTIN makes it a
 * business (B2B) invoice. The server re-checks everything, including the GSTIN check digit.
 */
export const billingSchema = z
  .object({
    name: z.string().trim().min(2, "Enter your full name").max(120),
    state_code: z.string().min(2, "Choose your state"),
    business: z.boolean(),
    gstin: z.string().trim().toUpperCase().optional(),
    business_name: z.string().trim().max(160).optional(),
    address: z.string().trim().max(300).optional(),
  })
  .superRefine((v, ctx) => {
    if (!v.business) return;
    if (!v.gstin || !GSTIN.test(v.gstin)) {
      ctx.addIssue({ code: "custom", path: ["gstin"], message: "Enter a valid 15-character GSTIN" });
    } else if (v.gstin.slice(0, 2) !== v.state_code) {
      ctx.addIssue({ code: "custom", path: ["gstin"], message: "This GSTIN is registered in a different state" });
    }
    if (!v.business_name || v.business_name.length < 2) {
      ctx.addIssue({ code: "custom", path: ["business_name"], message: "Enter the registered business name" });
    }
    if (!v.address || v.address.length < 5) {
      ctx.addIssue({ code: "custom", path: ["address"], message: "Enter the business address" });
    }
  });

export type BillingValues = z.infer<typeof billingSchema>;

/** The request body checkout expects (the state list comes from the API, so the code is one of its keys). */
export function billingPayload(v: BillingValues): CheckoutBody {
  return {
    name: v.name,
    state_code: v.state_code as CheckoutBody["state_code"],
    ...(v.business ? { gstin: v.gstin ?? null, business_name: v.business_name ?? null, address: v.address ?? null } : {}),
  };
}
