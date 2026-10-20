"use client";

/**
 * Razorpay Checkout (https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/).
 * The widget's script must come from Razorpay itself; card and UPI details never touch our site.
 */

export interface RazorpaySuccess {
  razorpay_payment_id: string;
  razorpay_order_id: string;
  razorpay_signature: string;
}

interface RazorpayOptions {
  key: string;
  order_id: string;
  amount: number;
  currency: string;
  name: string;
  description: string;
  prefill: { name: string; email: string };
  theme: { color: string };
  handler: (response: RazorpaySuccess) => void;
  modal: { ondismiss: () => void; confirm_close: boolean };
}

interface RazorpayInstance {
  open(): void;
  on(event: "payment.failed", cb: (r: { error: { description?: string } }) => void): void;
}

declare global {
  interface Window {
    Razorpay?: new (options: RazorpayOptions) => RazorpayInstance;
  }
}

const SCRIPT = "https://checkout.razorpay.com/v1/checkout.js";
let loading: Promise<void> | null = null;

function load(): Promise<void> {
  if (window.Razorpay) return Promise.resolve();
  loading ??= new Promise<void>((resolve, reject) => {
    const s = document.createElement("script");
    s.src = SCRIPT;
    s.async = true;
    s.onload = () => resolve();
    s.onerror = () => {
      loading = null;
      reject(new Error("Couldn’t load the payment window. Check your connection and try again."));
    };
    document.head.appendChild(s);
  });
  return loading;
}

export type PaymentOutcome =
  { status: "paid"; response: RazorpaySuccess } | { status: "dismissed" } | { status: "failed"; message: string };

/** Opens the payment window and resolves when the learner pays, closes it or the payment fails. */
export async function payWithRazorpay(
  options: Omit<RazorpayOptions, "handler" | "modal" | "theme">,
): Promise<PaymentOutcome> {
  await load();
  return new Promise((resolve) => {
    let settled = false;
    const done = (outcome: PaymentOutcome) => {
      if (!settled) {
        settled = true;
        resolve(outcome);
      }
    };
    // A failed attempt leaves the window open so the learner can try another method;
    // we only report the failure if they then close it.
    let lastError: string | null = null;
    const rzp = new window.Razorpay!({
      ...options,
      theme: { color: "#001b40" },
      handler: (response) => done({ status: "paid", response }),
      modal: {
        ondismiss: () => done(lastError ? { status: "failed", message: lastError } : { status: "dismissed" }),
        confirm_close: true,
      },
    });
    rzp.on("payment.failed", (r) => {
      lastError = r.error.description ?? "The payment didn’t go through.";
    });
    rzp.open();
  });
}
