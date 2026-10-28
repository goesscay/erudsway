import { SUPPORT_EMAIL } from "@/lib/contact";

/** Help Centre topics. `value` matches the API's ticket categories. */
export const helpCategories = [
  { value: "account", name: "Account & Login", desc: "Sign-up, email verification, password and profile" },
  { value: "courses", name: "Courses & Learning", desc: "Course access, videos, notes and files" },
  { value: "gst_simulator", name: "GST Simulator", desc: "Free trial, plans, returns and IMS practice" },
  { value: "tds_simulator", name: "TDS Simulator", desc: "TDS lessons and working files" },
  { value: "payments", name: "Payments & Refunds", desc: "Payments, invoices, renewals and refunds" },
  { value: "certificates", name: "Certificates", desc: "When certificates are issued and how to share them" },
  { value: "faculty", name: "Faculty & Instructors", desc: "Teach on Erudsway, course review and payouts" },
  { value: "technical", name: "Technical Support", desc: "Errors, video playback and file issues" },
] as const;

export type HelpCategory = (typeof helpCategories)[number]["value"];

export type HelpArticle = {
  slug: string;
  category: HelpCategory;
  title: string;
  summary: string;
  tags: string[];
  steps: string[];
  tip?: string;
  links?: [label: string, href: string][];
};

export const helpArticles: HelpArticle[] = [
  {
    slug: "create-an-account",
    category: "account",
    title: "Create an Erudsway account",
    summary: "Sign up with your email and confirm it to start learning.",
    tags: ["sign up", "register", "verify email", "activation"],
    steps: [
      "Select Sign up at the top of any page.",
      "Enter your name, email and a password. Adding your mobile number is optional, but the GST Simulator trial needs one.",
      "We email you a verification link. Open it on any device to confirm your email address.",
      "If the email hasn’t arrived after a few minutes, check your spam or promotions folder, then sign in and use Resend link on the reminder at the top of the page.",
      "Once verified, you can buy courses, start the GST Simulator trial and raise support tickets.",
    ],
    tip: "We never ask for an OTP or your password by phone, email or WhatsApp.",
    links: [["Sign up", "/register"]],
  },
  {
    slug: "reset-password",
    category: "account",
    title: "Reset your password or fix sign-in problems",
    summary: "What to do when you can’t sign in.",
    tags: ["forgot password", "login", "sign in", "reset link"],
    steps: [
      "On the sign-in page, choose Forgot password and enter the email you registered with.",
      "Open the reset link we email you and choose a new password. The link expires after a short time, so request a new one if it has.",
      "Make sure you are using the same email you signed up or bought with. Each email is a separate account.",
      "If you still can’t sign in, raise a ticket from the Contact page with the email address and the exact error shown.",
    ],
    tip: "Never share your password or reset link with anyone, including Erudsway staff or instructors.",
    links: [["Forgot password", "/forgot-password"]],
  },
  {
    slug: "update-your-profile",
    category: "account",
    title: "Update your name, mobile number or password",
    summary: "Change your account details from Account settings.",
    tags: ["profile", "mobile", "phone", "name", "account settings"],
    steps: [
      "Sign in and open Account settings from the menu under your name.",
      "Update your name or mobile number and save.",
      "To change your password, enter your current password and the new one.",
      "The name on your account is the name printed on certificates, so check it before you finish a course.",
    ],
    links: [["Account settings", "/account"]],
  },
  {
    slug: "course-not-in-my-learning",
    category: "courses",
    title: "A course I bought isn’t in My Learning",
    summary: "Check the account, the order status and what to send us.",
    tags: ["enrolment", "course missing", "my learning", "order"],
    steps: [
      "Sign in with the same email you used at checkout.",
      "Open My Learning and refresh the page.",
      "Open Account → Purchases to check the order. A paid order gives access straight away.",
      "Don’t pay again. If the payment left your account but the order isn’t marked paid, it is usually confirmed within a few minutes.",
      "If the course is still missing, raise a Payments & Refunds ticket with the order number from Purchases.",
    ],
    links: [
      ["My Learning", "/my-learning"],
      ["Purchases", "/account/purchases"],
    ],
  },
  {
    slug: "video-not-playing",
    category: "courses",
    title: "A course video won’t play",
    summary: "Fix buffering, a blank player or a video that stops.",
    tags: ["video", "playback", "buffering", "streaming", "blank"],
    steps: [
      "Check your connection and reload the lesson.",
      "Use an up-to-date version of Chrome, Edge, Safari or Firefox.",
      "Turn off ad blockers or other extensions for erudsway.com and try again.",
      "Try another device or network to see whether the problem is local.",
      "If it still fails, raise a Technical Support ticket with the course, the lesson title and a screenshot.",
    ],
    tip: "Course videos are for your own study and can’t be downloaded or shared.",
  },
  {
    slug: "notes-and-working-files",
    category: "courses",
    title: "Find notes and downloadable working files",
    summary: "Where lesson resources live and what to do if one is missing.",
    tags: ["notes", "download", "files", "pdf", "excel", "resources"],
    steps: [
      "Open the course from My Learning.",
      "Open the lesson. Files the instructor attached are listed under its resources.",
      "What a course includes is listed on its course page, and some editions include more than others.",
      "If a file is listed but won’t download or open, see “A PDF or Excel file won’t open”.",
      "If a resource you expected is missing, raise a Courses & Learning ticket with the course and lesson name.",
    ],
  },
  {
    slug: "course-access-period",
    category: "courses",
    title: "How long you can access a course",
    summary: "Where to see your access period and what happens at the end.",
    tags: ["validity", "expiry", "access period", "months", "renew"],
    steps: [
      "Each course page shows the access period for every edition before you buy.",
      "Account → Purchases lists the edition you bought, so you can match it to the access period on the course page.",
      "Some editions can be renewed in their final 30 days; the new period starts when the current one ends.",
      "If the access period doesn’t match what you bought, raise a ticket with the order number.",
    ],
  },
  {
    slug: "gst-simulator-trial",
    category: "gst_simulator",
    title: "Start the free 7-day GST Simulator trial",
    summary: "Get your own practice GST login in a few clicks.",
    tags: ["gst simulator", "trial", "free", "practice gstin", "login"],
    steps: [
      "Sign in with a verified account and open GST Simulator from the top menu.",
      "Select Start my free trial. Add your mobile number if we ask for it.",
      "Your practice login is ready within seconds. Select Open the simulator to go straight in.",
      "The trial lasts 7 days and there is one trial per account. We email you two days before it ends.",
      "Buy a plan before the trial ends to keep the same login and everything you’ve practised.",
    ],
    tip: "The simulator is a training environment. Nothing you do there files a return, pays tax or reaches the GST portal.",
    links: [["GST Simulator", "/gst-simulator"]],
  },
  {
    slug: "gst-simulator-plans",
    category: "gst_simulator",
    title: "GST Simulator plans, renewal and expiry",
    summary: "3-month and 12-month plans, and what happens when one ends.",
    tags: ["plan", "renew", "expiry", "3 months", "12 months", "gst simulator"],
    steps: [
      "Choose a 3-month or 12-month plan on the GST Simulator page.",
      "If you buy during your trial, you keep the same login and your work.",
      "In the last 30 days of a plan you can buy it again. The new period starts when the current one ends.",
      "We remind you three days before your plan ends. When it ends, your practice login is removed.",
    ],
    links: [["Compare the plans", "/gst-simulator#plans"]],
  },
  {
    slug: "gstr1-to-gstr3b",
    category: "gst_simulator",
    title: "Practise GSTR-1, IMS, GSTR-2B and GSTR-3B",
    summary: "A suggested order for working through a return period.",
    tags: ["gstr-1", "gstr-3b", "ims", "gstr-2b", "itc", "returns"],
    steps: [
      "Start with your outward supplies: enter the invoices for the period in GSTR-1 and check each table.",
      "Open IMS to accept, reject or keep pending the invoices your suppliers reported.",
      "Review GSTR-2B to see the input tax credit that results.",
      "Prepare GSTR-3B, compare liability with the available credit and pay any balance with a practice challan.",
      "Repeat for the next period. Mistakes cost nothing, so try different scenarios.",
    ],
    tip: "The simulator follows the portal’s screens for practice. Always apply current GST law and notifications to real filings.",
  },
  {
    slug: "gstr9-access",
    category: "gst_simulator",
    title: "Why GSTR-9 and GSTR-9C aren’t available to me",
    summary: "The annual return modules are in limited testing.",
    tags: ["gstr-9", "gstr-9c", "annual return", "pilot", "access"],
    steps: [
      "GSTR-9 and GSTR-9C are being tested with a small group of learners before they open to everyone.",
      "If you are in the test group, sign in with the account we enabled and the modules appear in your simulator.",
      "Everyone else will see them once testing is complete. We’ll announce it on the GST Simulator page.",
      "If you were told you’re in the test group but can’t see them, raise a GST Simulator ticket.",
    ],
  },
  {
    slug: "tds-learning",
    category: "tds_simulator",
    title: "TDS lessons and working files",
    summary: "Learn TDS with course lessons while the simulator is built.",
    tags: ["tds", "tds simulator", "working files", "challan", "24q", "26q"],
    steps: [
      "Open your TDS course from My Learning.",
      "Work through the lessons on deduction, challans and statement preparation, using the attached working files.",
      "The TDS Simulator is coming soon. It isn’t open yet, and we’ll announce it when it is.",
      "If a lesson or file is missing from your TDS course, raise a TDS Simulator ticket with the course and lesson name.",
    ],
  },
  {
    slug: "payment-taken-course-not-active",
    category: "payments",
    title: "Payment taken but the course isn’t active",
    summary: "What to check before paying again.",
    tags: ["payment", "debited", "not activated", "razorpay", "failed"],
    steps: [
      "Don’t pay again.",
      "Open Account → Purchases. If the order is marked paid, the course is in My Learning.",
      "If the order isn’t marked paid, wait a few minutes. Payments are usually confirmed shortly after the bank responds.",
      "If the amount left your account but no paid order appears, raise a Payments & Refunds ticket with the payment reference from your bank or UPI app.",
      "If a payment can’t be matched to an order, it is refunded to the original payment method.",
    ],
    tip: "Never send card numbers, CVVs, UPI PINs or full bank statements. A payment reference and the date are enough.",
    links: [["Purchases", "/account/purchases"]],
  },
  {
    slug: "invoices",
    category: "payments",
    title: "Download an invoice",
    summary: "Every paid order comes with an invoice.",
    tags: ["invoice", "receipt", "bill", "download"],
    steps: [
      "Open Account → Purchases.",
      "Find the order and select its invoice to download it as a PDF.",
      "We also email the invoice when the payment is confirmed.",
      "If the billing details on an invoice need correcting, raise a Payments & Refunds ticket with the order number.",
    ],
    links: [["Purchases", "/account/purchases"]],
  },
  {
    slug: "refunds-and-duplicate-payments",
    category: "payments",
    title: "Refunds, duplicate payments and wrong orders",
    summary: "How refund requests are handled.",
    tags: ["refund", "duplicate", "cancellation", "wrong course"],
    steps: [
      "Read the Refund & Cancellation policy to see when a refund applies.",
      "For a duplicate payment or an order placed by mistake, raise a Payments & Refunds ticket with both order numbers.",
      "We review each request against the policy and reply on the ticket.",
      "Approved refunds go back to the original payment method. Your bank or payment app controls the final timing.",
    ],
    links: [["Refund & Cancellation policy", "/policies#refund"]],
  },
  {
    slug: "certificates",
    category: "certificates",
    title: "Get and share your course certificate",
    summary: "Certificates are issued automatically when you complete a course.",
    tags: ["certificate", "completion", "download", "verify", "share"],
    steps: [
      "Complete every lesson in the course. Your progress is shown in My Learning.",
      "Your certificate is issued automatically, and we email you when it’s ready.",
      "Find all your certificates under Account → Certificates, where you can download them.",
      "Each certificate has a public verification page you can share with employers.",
      "If you finished a course but no certificate appeared, raise a Certificates ticket with the course name.",
    ],
    tip: "The certificate shows the name on your account, so check it in Account settings first.",
    links: [["My certificates", "/account/certificates"]],
  },
  {
    slug: "teach-on-erudsway",
    category: "faculty",
    title: "Apply to teach on Erudsway",
    summary: "Share your expertise and propose a course.",
    tags: ["instructor", "faculty", "teach", "apply"],
    steps: [
      "Open Teach on Erudsway and sign in or create an account.",
      "Tell us about your professional background, subject areas and the course you’d like to teach.",
      "Our team reviews each application and replies by email.",
      "Once approved, you build your course in the instructor studio and submit it for review before it goes live.",
    ],
    tip: "Applying doesn’t guarantee approval. Revenue share and payout terms are agreed during onboarding.",
    links: [["Teach on Erudsway", "/teach"]],
  },
  {
    slug: "course-review-and-payouts",
    category: "faculty",
    title: "Course review, publishing and payouts",
    summary: "What happens after you submit a course.",
    tags: ["course review", "publish", "payout", "instructor", "revenue"],
    steps: [
      "Submit the course from the instructor studio when every section is ready.",
      "Our team reviews it and either publishes it or sends back changes to make.",
      "Make the requested changes and submit again.",
      "Your earnings and payouts are listed in the instructor area once your course is selling.",
    ],
  },
  {
    slug: "report-an-error",
    category: "technical",
    title: "Report a website or simulator error",
    summary: "What to include so we can fix it quickly.",
    tags: ["bug", "error", "blank page", "technical", "screenshot"],
    steps: [
      "Note the page, course or simulator screen where it happened.",
      "Write down what you expected and what happened instead, including any error message or code.",
      "Take a screenshot. Hide any personal or taxpayer details first.",
      "Mention your browser and device.",
      "Raise a Technical Support ticket and attach the screenshot.",
    ],
  },
  {
    slug: "file-wont-open",
    category: "technical",
    title: "A PDF or Excel file won’t open",
    summary: "Troubleshoot a download that fails or won’t open.",
    tags: ["pdf", "excel", "download", "file", "corrupt"],
    steps: [
      "Download the file again using an up-to-date browser.",
      "Open it with an app that supports the file type, such as a PDF reader or a spreadsheet app.",
      "On a phone, try opening it on a computer instead.",
      "If the file still seems damaged, raise a Technical Support ticket with the course, lesson and file name.",
    ],
  },
];

export const helpFaqs: [question: string, answer: string][] = [
  [
    "How do I access a course after buying it?",
    "Sign in with the email you used at checkout and open My Learning. Paid courses appear there straight away.",
  ],
  [
    "Can I download course videos?",
    "No. Videos stream inside the course. Notes and working files attached to lessons can be downloaded.",
  ],
  [
    "Does the GST Simulator file a real GST return?",
    "No. It is a practice environment. Nothing you do there is sent to the GST portal or pays any tax.",
  ],
  [
    "Is the GST Simulator trial really free?",
    "Yes. The 7-day trial needs only a verified account and a mobile number, with no card. There is one trial per account.",
  ],
  [
    "Why can’t I see GSTR-9 and GSTR-9C?",
    "They are being tested with a small group of learners first, and will open to everyone once testing is complete.",
  ],
  [
    "When will the TDS Simulator be available?",
    "It is coming soon. Until then, TDS courses include lessons and working files. We’ll announce the launch on the website.",
  ],
  [
    "My payment went through but the course isn’t active. What should I do?",
    "Don’t pay again. Check Account → Purchases, and if no paid order appears, raise a Payments & Refunds ticket with the payment reference.",
  ],
  [
    "Will I get a certificate?",
    "Yes. When you complete every lesson in a course, your certificate is issued automatically and appears under Account → Certificates.",
  ],
  [
    "How do I know when my ticket has been answered?",
    "We email you when our team replies, and My tickets shows each ticket’s status and the whole conversation.",
  ],
];

export const popularSearches = ["GST Simulator trial", "Video not playing", "Payment", "Certificate", "Refund"];

export const supportEmail = SUPPORT_EMAIL;

export function categoryName(value: string): string {
  return helpCategories.find((c) => c.value === value)?.name ?? value;
}

export function findArticle(slug: string): HelpArticle | undefined {
  return helpArticles.find((a) => a.slug === slug);
}

export function searchArticles(query: string, category?: string): HelpArticle[] {
  const words = query.toLowerCase().split(/\s+/).filter(Boolean);
  return helpArticles.filter((a) => {
    if (category && a.category !== category) return false;
    if (!words.length) return true;
    const haystack = [a.title, a.summary, categoryName(a.category), ...a.tags].join(" ").toLowerCase();
    return words.every((w) => haystack.includes(w));
  });
}
