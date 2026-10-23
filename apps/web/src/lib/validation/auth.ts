import { z } from "zod";

/** Mirrors the API's rules so most mistakes are caught before a round trip; the API stays authoritative. */
const password = z
  .string()
  .min(8, "Use at least 8 characters.")
  .regex(/[A-Za-z]/, "Include at least one letter.")
  .regex(/[0-9]/, "Include at least one number.");

const email = z.string().trim().min(1, "Enter your email address.").email("Enter a valid email address.");

export const loginSchema = z.object({
  email,
  password: z.string().min(1, "Enter your password."),
  remember: z.boolean(),
});
export type LoginValues = z.infer<typeof loginSchema>;

/** An Indian mobile number however it's typed: 98765 43210, +91 98765-43210, 098765 43210. */
export function isIndianMobile(value: string): boolean {
  let digits = value.replace(/[\s\-().]/g, "");
  if (digits.startsWith("+91")) digits = digits.slice(3);
  else if (digits.length === 12 && digits.startsWith("91")) digits = digits.slice(2);
  else if (digits.length === 11 && digits.startsWith("0")) digits = digits.slice(1);
  return /^[6-9]\d{9}$/.test(digits);
}

export const mobileMessage = "Enter a 10-digit Indian mobile number, e.g. 98765 43210.";

export const registerSchema = z
  .object({
    name: z.string().trim().min(2, "Enter your full name.").max(80, "Keep it under 80 characters."),
    email,
    phone: z
      .string()
      .trim()
      .refine((v) => v === "" || isIndianMobile(v), mobileMessage),
    password,
    password_confirmation: z.string(),
    accept_terms: z
      .boolean()
      .refine((v) => v, "Confirm that you are 18 or older and accept the Terms and Privacy Policy."),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords don't match.",
  });
export type RegisterValues = z.infer<typeof registerSchema>;

export const forgotSchema = z.object({ email });
export type ForgotValues = z.infer<typeof forgotSchema>;

export const resetSchema = z
  .object({ password, password_confirmation: z.string() })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords don't match.",
  });
export type ResetValues = z.infer<typeof resetSchema>;

export const profileSchema = z.object({
  name: z.string().trim().min(2, "Enter your full name.").max(80),
  headline: z.string().trim().max(120, "Keep it under 120 characters."),
  bio: z.string().trim().max(2000, "Keep it under 2,000 characters."),
  phone: z
    .string()
    .trim()
    .refine((v) => v === "" || /^\+?[0-9 ]{8,16}$/.test(v), "Enter a valid phone number, e.g. +91 98765 43210."),
});
export type ProfileValues = z.infer<typeof profileSchema>;

export const changePasswordSchema = z
  .object({
    current_password: z.string().min(1, "Enter your current password."),
    password,
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords don't match.",
  });
export type ChangePasswordValues = z.infer<typeof changePasswordSchema>;

export const teachingExperience = [
  { value: "none", label: "I haven't taught before" },
  { value: "informal", label: "Informally — mentoring colleagues or friends" },
  { value: "classroom", label: "In a classroom or training room" },
  { value: "online", label: "Online — videos, webinars or courses" },
] as const;

export const applicationSchema = z.object({
  headline: z.string().trim().min(10, "Describe yourself in at least 10 characters.").max(120),
  topics: z.array(z.string().trim().min(2).max(40)).min(1, "Add at least one topic.").max(5, "Pick up to five topics."),
  experience: z
    .string()
    .trim()
    .min(100, "Tell us a little more — at least 100 characters.")
    .max(3000, "Keep it under 3,000 characters."),
  teaching_experience: z.enum(["none", "informal", "classroom", "online"], { message: "Choose one option." }),
  sample_url: z
    .string()
    .trim()
    .refine((v) => v === "" || /^https:\/\/\S+\.\S+/.test(v), "Enter a full https:// link."),
});
export type ApplicationValues = z.infer<typeof applicationSchema>;
