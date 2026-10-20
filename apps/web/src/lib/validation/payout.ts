import { z } from "zod";

/** Mirrors the API's rules; the server checks again (and that a GSTIN matches the PAN). */
export const payoutSchema = z
  .object({
    legal_name: z.string().trim().min(3, "Enter your name as on your PAN").max(160),
    pan: z
      .string()
      .trim()
      .toUpperCase()
      .regex(/^[A-Z]{5}[0-9]{4}[A-Z]$/, "Enter a PAN like ABCDE1234F"),
    gstin: z
      .string()
      .trim()
      .toUpperCase()
      .refine(
        (v) => v === "" || /^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.test(v),
        "Enter a valid 15-character GSTIN",
      ),
    account_holder: z.string().trim().min(3, "Enter the account holder’s name").max(160),
    account_number: z
      .string()
      .trim()
      .regex(/^\d{9,18}$/, "Enter 9–18 digits"),
    account_number_confirmation: z.string().trim(),
    ifsc: z
      .string()
      .trim()
      .toUpperCase()
      .regex(/^[A-Z]{4}0[A-Z0-9]{6}$/, "Enter an IFSC like HDFC0001234"),
  })
  .refine((v) => v.account_number === v.account_number_confirmation, {
    path: ["account_number_confirmation"],
    message: "The account numbers don’t match",
  })
  .refine((v) => v.gstin === "" || v.gstin.slice(2, 12) === v.pan, {
    path: ["gstin"],
    message: "This GSTIN belongs to a different PAN",
  });

export type PayoutValues = z.infer<typeof payoutSchema>;
