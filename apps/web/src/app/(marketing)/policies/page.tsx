import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Legal policies",
  description:
    "Terms & Conditions, Privacy, Refund & Cancellation, Professional Disclaimer, Cookies, Delivery & Access, Acceptable Use, Grievances and Policy Changes for Erudsway Educorp LLP.",
};

const SUPPORT = "support@erudsway.com";
const PHONE = "+91 86080 59459";
const PHONE_HREF = "tel:+918608059459";
const ADDRESS =
  "No. 7/7A, Sundaresan Street, Zamin Pallavaram, Thiripura Sundari Colony, Pallavaram, Tambaram, Tamil Nadu 600043, India";

const sections = [
  { id: "terms", title: "Terms & Conditions", effective: "1 October 2026" },
  { id: "privacy", title: "Privacy Policy", effective: "16 September 2026" },
  { id: "refund", title: "Refund & Cancellation", effective: "1 October 2026" },
  { id: "disclaimer", title: "Professional Disclaimer", effective: "1 October 2026" },
  { id: "cookies", title: "Cookie Policy", effective: "1 October 2026" },
  { id: "delivery", title: "Delivery & Access", effective: "1 October 2026" },
  { id: "conduct", title: "Learning & Acceptable Use", effective: "1 October 2026" },
  { id: "grievance", title: "Grievances", effective: "1 October 2026" },
  { id: "changes", title: "Policy Changes", effective: "1 October 2026" },
] as const;

type SectionId = (typeof sections)[number]["id"];

function Mail({ subject }: { subject?: string }) {
  return (
    <a
      href={`mailto:${SUPPORT}${subject ? `?subject=${encodeURIComponent(subject)}` : ""}`}
      className="font-medium text-navy-800 underline underline-offset-2"
    >
      {SUPPORT}
    </a>
  );
}

function Phone() {
  return (
    <a href={PHONE_HREF} className="font-medium text-navy-800 underline underline-offset-2">
      {PHONE}
    </a>
  );
}

function Section({ id, children }: { id: SectionId; children: React.ReactNode }) {
  const meta = sections.find((s) => s.id === id)!;
  return (
    <section
      id={id}
      aria-labelledby={`${id}-title`}
      className="scroll-mt-32 border-t border-line pt-10 first:border-t-0 first:pt-0"
    >
      <h2 id={`${id}-title`} className="text-h2">
        {meta.title}
      </h2>
      <p className="mt-1 text-xs text-stone-500">Last updated 1 October 2026 · Effective from {meta.effective}</p>
      <div className="policy mt-6 space-y-4 text-[0.95rem] leading-relaxed text-stone-700">{children}</div>
      <p className="mt-6">
        <a href="#top" className="text-xs font-semibold text-navy-800 hover:underline">
          Back to contents ↑
        </a>
      </p>
    </section>
  );
}

function H({ children }: { children: React.ReactNode }) {
  return <h3 className="pt-2 font-sans text-base font-bold text-ink">{children}</h3>;
}

export default function PoliciesPage() {
  return (
    <div id="top" className="mx-auto max-w-[1340px] px-4 py-10 sm:px-6 lg:py-14">
      <header className="max-w-3xl">
        <p className="mb-3 text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">Legal</p>
        <h1 className="text-[2.2rem] leading-tight tracking-[-0.01em] sm:text-[2.6rem]">Legal policies</h1>
        <p className="mt-4 text-[0.95rem] leading-relaxed text-stone-700">
          How Erudsway Educorp LLP runs its online learning, consulting, simulation and knowledge-sharing platform at
          erudsway.com, and how you use its courses, resources, appointments, software tools and community features.
        </p>
      </header>

      <div className="mt-10 grid gap-10 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-14">
        <nav aria-label="Policies" className="lg:sticky lg:top-28 lg:self-start">
          <p className="mb-2 text-xs font-bold tracking-[0.14em] text-stone-500 uppercase">Contents</p>
          <ol className="grid gap-1 sm:grid-cols-2 lg:grid-cols-1">
            {sections.map((s, i) => (
              <li key={s.id}>
                <a
                  href={`#${s.id}`}
                  className="flex gap-2 rounded-sm px-2 py-1.5 text-sm text-stone-700 hover:bg-paper-2 hover:text-navy-900 focus-visible:outline-2 focus-visible:outline-navy-900"
                >
                  <span className="tabular w-5 text-stone-500">{i + 1}.</span>
                  {s.title}
                </a>
              </li>
            ))}
          </ol>
          <p className="mt-6 hidden text-xs leading-relaxed text-stone-500 lg:block">
            Questions? <Mail /> or <Phone />.
          </p>
        </nav>

        <div className="max-w-3xl min-w-0 space-y-12">
          <Section id="terms">
            <H>1. About Erudsway</H>
            <p>
              Erudsway Educorp LLP (“Erudsway”, “we”, “us” or “our”) owns and operates erudsway.com and related learning
              environments, applications, simulators, software tools and community spaces (together, the “Platform”).
              Our correspondence address is {ADDRESS}. Contact: <Mail /> · Phone/WhatsApp: <Phone />.
            </p>
            <p>
              The Platform provides online and, where offered, offline learning; recorded and live courses; practical
              exercises, downloadable learning resources and assessments; educational simulators and webpage software;
              consultations and appointment booking; Knowledge Hub articles; knowledge-sharing forums and communities;
              and institutional or business training. Subjects may include accounting, taxation, audit, company and
              regulatory filings, finance, technology, automation, professional examinations, workplace skills and other
              announced topics. Erudsway also creates learning content itself and may commission, license or acquire
              content from professionals and other contributors.
            </p>
            <H>2. Agreement and service-specific terms</H>
            <p>
              By visiting the Platform, opening an account, placing an order, booking a consultation, posting in a
              community, or using a simulator, you agree to these Terms and the applicable Privacy, Refund &amp;
              Cancellation, Professional Disclaimer, Cookie, Delivery &amp; Access and Learning &amp; Acceptable Use
              policies. If you do not agree, do not use the relevant service.
            </p>
            <p>
              A course page, checkout screen, appointment confirmation or written institutional work order may state
              specific scope, price, language, access period, schedule, deliverables or other conditions. Those details
              form part of the relevant order. For institutional assignments, a signed proposal or work order governs
              the agreed assignment where it specifically conflicts with these website terms; statutory consumer rights
              are not removed by this clause.
            </p>
            <H>3. Accounts, eligibility and minors</H>
            <p>
              You must provide accurate account details, keep your password and one-time codes confidential, and
              promptly tell us if you suspect unauthorised account use. You are responsible for activity under your
              account, except to the extent applicable law places responsibility elsewhere. Accounts and paid access are
              for one named learner unless the order expressly permits multiple users or an institution-wide licence.
            </p>
            <p>
              A person under 18 must not independently create an account, purchase a service or submit personal
              information through the Platform. Erudsway will permit a minor account only after age screening and a
              legally adequate parent or guardian verification and consent process is operational and completed. A
              parent or legal guardian must supervise a minor’s use and is responsible for permissions and purchases
              made on the minor’s behalf. We may suspend or close an account if age or consent requirements are not met.
            </p>
            <H>4. Courses, consultations and other services</H>
            <p>
              Course descriptions, preview materials, learning outcomes, language, format, included resources,
              assessment availability, instructor information and access duration are shown on the relevant listing or
              checkout. Course enrolment is a learning-service purchase; it does not by itself create a
              professional-client, advocate-client, accountant-client, employment, partnership or agency relationship.
            </p>
            <p>
              Consultations are provided by Erudsway or by the independent professional identified for that appointment,
              as stated at booking. Before paying, review the provider, stated qualifications, subject, scope, session
              length, time, fees, deliverables and cancellation terms. A consultation does not include continuing
              representation, filing, certification or a written opinion unless the booking or a separate engagement
              expressly says so.
            </p>
            <p>
              Institutional training, live events, coaching, assessments, templates, software access, subscriptions or
              other offerings may have additional terms shown before purchase. We may update content to correct errors
              or reflect changed law, technology or course design. A material change to a paid service will be handled
              under the applicable order and law.
            </p>
            <H>5. Orders, prices and payment</H>
            <p>
              Prices, payment currency, applicable charges and any available payment schedules are displayed before an
              order is placed. Payment is processed through Razorpay or another online payment method presented at
              checkout. Payment providers may apply their own terms and security checks; do not share card PINs,
              passwords or one-time codes with anyone claiming to represent Erudsway.
            </p>
            <p>
              An order is accepted when we or the payment provider confirms it. A payment debit without an order
              confirmation, duplicate charge, suspected unauthorised payment or payment failure should be reported
              promptly with the transaction reference. We may cancel an unconfirmed or fraudulent order and may restrict
              access where payment is reversed or remains unpaid, subject to applicable law.
            </p>
            <H>6. Platform content and intellectual property</H>
            <p>
              Course videos, audio, slides, notes, templates, assessments, simulator designs, software, marks, branding,
              articles and other Platform materials are owned by Erudsway or used under permission. Buying access gives
              you a limited, personal, non-exclusive, non-transferable and revocable licence to use the purchased
              materials for your own learning during the stated access period. It does not transfer copyright or
              ownership to you.
            </p>
            <p>
              You must not copy, screen-record, download where downloading is not authorised, retransmit, publish, sell,
              sublicense, share login credentials for, scrape, adapt or create a competing course from Platform
              materials. You may use a permitted download only for your own non-commercial study. Copyright, trademark
              and other intellectual-property laws continue to apply.
            </p>
            <p>
              <strong className="text-ink">Professional and other contributors.</strong> Some content is created by
              Erudsway; other content may be produced by professionals or other contributors under a contract, written
              assignment, exclusive licence, non-exclusive licence or other agreed arrangement. Each contributor’s
              signed written agreement must identify the relevant works, rights, duration, territory, consideration,
              attribution and any update or reuse rights before content is uploaded. Where a contributor has assigned
              rights outright or granted an exclusive licence, that contributor must not publish, license, upload,
              distribute, sell, provide or otherwise make the assigned or exclusively licensed recordings or materials
              available on another website, platform, service, marketplace or other medium, except for an express
              written carve-out in that agreement. Contributors must not use Erudsway learner records, confidential
              information or third-party client material in their courses. Applicable statutory author rights are not
              waived by these Terms.
            </p>
            <p>
              This website policy does not replace a contributor agreement or itself transfer a contributor’s copyright.
              If no signed written agreement is in place, Erudsway must not treat the contributor’s materials as
              exclusively owned or licensed.
            </p>
            <H>7. Simulators, software and practice environments</H>
            <p>
              Simulators, mock portals, calculators, workflow demonstrations, sample webpages and other interactive
              tools are provided for education, practice and familiarisation. Unless a screen clearly says otherwise,
              they are not official government, regulator, tax, banking or professional-body portals; they do not submit
              live filings, create official records or replace professional review. A simulated result may differ from a
              live system or current law.
            </p>
            <p>
              Use only fictional, sample or properly anonymised data. Do not enter real taxpayer, client, employee,
              bank, identity or portal credentials, or confidential information, unless a specific service expressly
              requires it and its instructions authorise the data. Verify current law, figures and filing requirements
              through official sources before taking real-world action. Erudsway does not guarantee that a simulator is
              error-free, available without interruption or suitable for a particular transaction.
            </p>
            <H>8. Forums, Knowledge Hub and user submissions</H>
            <p>
              You are responsible for questions, comments, files, profile content and other material you submit (“User
              Content”). You retain rights you already hold in User Content. You give Erudsway a worldwide,
              non-exclusive, royalty-free licence to host, store, reproduce, format, display and transmit it only as
              reasonably necessary to operate, secure, moderate and provide the Platform and the feature through which
              you submitted it. We will seek separate permission before using identifiable User Content in external
              advertising unless a public-post feature clearly says otherwise.
            </p>
            <p>
              Do not post unlawful, infringing, defamatory, harassing, discriminatory, misleading, sexually
              exploitative, threatening or confidential material; another person’s personal information without
              authority; malicious code; or spam. Forum posts may be visible to other users and may be copied by them.
              Do not post information you need to keep private. We may remove or restrict content, disable features or
              suspend accounts to protect users, comply with law, enforce these Terms or respond to a credible
              complaint. Where legally required, we will follow applicable notice, grievance and takedown procedures.
            </p>
            <p>
              Community answers and Knowledge Hub materials may be supplied by users or contributors and are not
              automatically reviewed as personalised professional advice. Views expressed by contributors or users do
              not necessarily represent Erudsway.
            </p>
            <H>9. Acceptable conduct and security</H>
            <p>
              You must not: bypass access controls; share, resell or transfer an account; probe, disrupt or overload the
              Platform; upload malware; scrape or harvest information; impersonate another person; interfere with
              assessments; use the Platform to break law or third-party rules; infringe intellectual property; or use
              learning tools to produce deceptive, fraudulent or unauthorised filings. You must respect
              professional-body rules, employer restrictions and confidentiality duties that apply to you.
            </p>
            <H>10. Third-party links and availability</H>
            <p>
              The Platform may link to third-party websites, payment providers, video hosts, calendars or software.
              Their services are governed by their own terms and privacy notices. We do not control third-party services
              and are not responsible for their content or availability, subject to any responsibility that applicable
              law places on us for our own service.
            </p>
            <p>
              We aim to keep the Platform secure and available but do not promise uninterrupted access. Maintenance,
              internet failures, device compatibility, cyber incidents and third-party outages may interrupt service. We
              may change, suspend or discontinue a feature; where a change materially affects an active paid order, we
              will address it as required by that order and applicable law.
            </p>
            <H>11. No government or professional-body affiliation</H>
            <p>
              Unless a specific page expressly identifies a current, authorised affiliation, Erudsway is an independent
              education and technology platform. A course, certificate, simulation or logo does not mean that a
              government department, regulator, university, professional institute or examination body has approved,
              accredited or endorsed Erudsway or the course. Do not describe training as a statutory qualification
              unless the relevant authority has formally recognised it.
            </p>
            <H>12. Warranties and limits of liability</H>
            <p>
              The Platform is provided for the uses described in the relevant listing and these policies. To the fullest
              extent permitted by law, we exclude implied warranties and liability for indirect or consequential loss
              arising from use of the Platform, reliance on general learning materials, learner decisions, third-party
              services or interruptions beyond our reasonable control.
            </p>
            <p>
              Nothing in these Terms excludes or limits liability that cannot legally be excluded, including applicable
              consumer remedies, liability for fraud or wilful misconduct, or other non-waivable statutory rights. If a
              paid service is not supplied as promised, a material mismatch is established, or a technical defect
              attributable to us prevents the purchased service from being used and is not reasonably corrected, the
              available remedy will be determined under applicable law and the relevant order.
            </p>
            <H>13. Your responsibility for misuse</H>
            <p>
              You are responsible for losses, claims and reasonable costs caused by your unlawful use, infringement,
              unauthorised disclosure, material breach or misuse of the Platform, to the extent the law permits
              recovery. This does not require you to indemnify Erudsway for Erudsway’s own unlawful conduct or for
              liability that cannot legally be shifted to you.
            </p>
            <H>14. Suspension, termination and governing law</H>
            <p>
              We may suspend or terminate access for a serious or repeated breach, suspected fraud, security threat,
              non-payment, unlawful conduct or a legal requirement. Where appropriate and lawful, we will give notice
              and an opportunity to correct a breach. Ending access does not cancel accrued payment obligations or
              rights and remedies already available under law.
            </p>
            <p>
              These Terms are governed by the laws of India. Subject to non-waivable consumer rights and the
              jurisdiction of competent statutory forums, courts at Chennai, Tamil Nadu will have jurisdiction over
              disputes arising from these Terms. You may also use any consumer or other statutory remedy available to
              you.
            </p>
          </Section>

          <Section id="privacy">
            <H>1. Who is responsible</H>
            <p>
              Erudsway Educorp LLP operates the Platform and handles personal data for the purposes described here. Send
              privacy requests to <Mail />, phone/WhatsApp <Phone />, or the correspondence address stated in the Terms.
              This notice applies to erudsway.com, our LMS, simulators and community features.
            </p>
            <H>2. Information we handle</H>
            <p>Depending on the service you use, we may handle:</p>
            <ul className="list-disc space-y-1.5 pl-5">
              <li>
                identity and contact details such as name, email address, phone number, account name and, where needed,
                age or guardian contact details;
              </li>
              <li>account, course, attendance, assessment, progress, certificate and support records;</li>
              <li>
                orders, payment status, transaction references, refunds and invoices supplied through our payment
                provider (we do not ask you to disclose payment passwords or one-time codes);
              </li>
              <li>
                consultation booking details and information you choose to send to a professional for that booking;
              </li>
              <li>
                forum posts, questions, messages, uploaded files, moderation reports and communications with support;
              </li>
              <li>
                device, browser, log, approximate network and usage information needed to deliver, secure and
                troubleshoot the Platform; and
              </li>
              <li>cookie or similar technology choices, where used.</li>
            </ul>
            <p>
              Please do not send sensitive identifiers, real client records or confidential documents through a course,
              forum or simulator unless a particular service explicitly requests them through a suitable secure channel.
            </p>
            <H>3. Why we use information</H>
            <p>
              We use information to create and secure accounts; enrol learners and provide courses, consultations, tools
              and community functions; process payments and issue order records; answer questions and grievances; track
              progress and improve learning materials; prevent fraud, piracy, abuse and security incidents; communicate
              service, account and policy information; send optional marketing where permitted; meet legal obligations;
              and establish, exercise or defend legal claims.
            </p>
            <p>
              We process only information reasonably connected to the service or purpose. If you decline information
              that is necessary to deliver a requested service or comply with law, we may be unable to provide that
              service.
            </p>
            <H>4. Sharing and service providers</H>
            <p>
              We may share the minimum information needed with payment processors, hosting and LMS providers, video or
              file delivery services, customer-support tools, email/SMS/WhatsApp providers, scheduling tools, security
              and analytics providers, professional instructors or consultation providers, and institutional customers
              where they arranged your training. We may also disclose information to courts, regulators, law-enforcement
              authorities or other persons where legally required, and in connection with a business reorganisation
              subject to appropriate safeguards.
            </p>
            <p>
              Providers may process information under their own terms. We do not sell personal information as a
              standalone product. Where a provider processes data outside India, we will handle the transfer subject to
              applicable law and the safeguards then required.
            </p>
            <H>5. Retention and security</H>
            <p>
              We keep information for as long as reasonably needed to provide the service, maintain course and
              transaction records, meet legal and accounting duties, address disputes, enforce agreements and protect
              the Platform. Retention periods vary by record type. We may delete, de-identify or restrict information
              when it is no longer needed, except where law requires retention.
            </p>
            <p>
              We use administrative, technical and organisational safeguards appropriate to the data and risks. No
              internet transmission or storage system can be guaranteed completely secure. Tell us promptly if you
              believe your account or information has been compromised.
            </p>
            <H>6. Your choices and requests</H>
            <p>
              You may contact us to request access to, correction of, updating of or deletion of personal information,
              to withdraw a consent where processing relies on consent, or to object or complain where applicable law
              gives you that right. We may need to verify your identity and may retain information where required by law
              or needed to resolve a transaction, dispute or security issue. We will respond in accordance with
              applicable law.
            </p>
            <p>
              You can opt out of non-essential marketing communications using the unsubscribe option or by contacting
              support. Service, security, payment and account notices may still be sent when necessary.
            </p>
            <H>7. Children and guardian consent</H>
            <p>
              We do not permit a person under 18 to register directly unless the age-screening and legally required
              parent or guardian verification and consent process is operational and completed. Until that process is
              available, under-18 registration and purchases are disabled. If we learn that a minor’s account was
              created without required consent, we may suspend it and delete or restrict the information as law permits.
              Parents or guardians may contact us using the details in this policy.
            </p>
            <H>8. Third-party services and links</H>
            <p>
              Links to a payment provider, messaging service, video host or other third party take you to a service we
              do not control. Their collection and use of information are described in their own notices. Review those
              notices before providing information to them.
            </p>
            <H>9. Privacy contact</H>
            <p>
              Write to <Mail subject="Privacy Request" /> with “Privacy Request” in the subject, or contact us at the
              phone/WhatsApp and correspondence address in the Terms. Please identify the account or transaction and
              explain the request. Do not include passwords or one-time codes.
            </p>
          </Section>

          <Section id="refund">
            <H>1. Recorded courses and digital learning products</H>
            <p>
              Review the course description, sample lesson, syllabus, language, included files, access period, device
              requirements and price before ordering. A completed digital-course purchase is final for a change of mind
              once access is enabled. We do not provide discretionary refunds because a learner chooses not to start or
              finish, changes their mind, buys the wrong course, dislikes a teaching style that was accurately
              described, misses a session, or does not achieve an expected examination, employment, business or
              financial result.
            </p>
            <p>
              This does not remove remedies that applicable law requires. If we fail to provide the purchased access,
              supply a materially different service, charge twice, or leave a material technical defect attributable to
              us unresolved after reasonable support, contact us promptly. Depending on the facts and law, we may
              restore access, correct the issue, provide equivalent access, reschedule, or refund the affected amount.
            </p>
            <H>2. Live courses and events</H>
            <p>
              Any event-specific cancellation, transfer or rescheduling terms will be displayed before payment. If
              Erudsway cancels an event or cannot provide a material part of the paid service, we will offer an
              appropriate replacement, rescheduling or refund as required by the published event terms and applicable
              law. A learner’s non-attendance or change of mind is not by itself a basis for a discretionary refund once
              the event or reserved service has begun.
            </p>
            <H>3. Consultations and appointments</H>
            <p>
              Before booking, verify the professional or service provider, subject, scope, stated credentials, date,
              time, session duration, fee and cancellation conditions. Once an appointment is confirmed and the provider
              has reserved the time, the booking fee is non-refundable for a learner’s change of mind, late cancellation
              or failure to attend. If rescheduling is offered, it will be subject to the appointment listing and
              provider availability.
            </p>
            <p>
              If the provider cancels, does not attend, or the consultation cannot be delivered for a reason
              attributable to Erudsway or the provider, contact support. We will arrange a reschedule or other remedy,
              including a refund where required by applicable law. This policy does not waive a remedy that cannot
              legally be excluded.
            </p>
            <H>4. Duplicate payments, failed orders and refund processing</H>
            <p>
              Report duplicate or unauthorised transactions and payment debits for which no order was created to{" "}
              <Mail /> with the payment reference. If a refund is due, it will normally be returned to the original
              payment method. Banks and payment providers control final processing times; we will provide the refund
              instruction or status available to us.
            </p>
            <H>5. How to request help</H>
            <p>
              Send your registered email or phone, order or appointment reference, transaction reference if relevant,
              and a clear description of the issue. We may ask for information reasonably needed to investigate. Nothing
              in this policy restricts a consumer’s rights under applicable law or prevents a complaint before a
              competent authority.
            </p>
          </Section>

          <Section id="disclaimer">
            <H>1. Education and general information</H>
            <p>
              Courses, articles, discussions, downloadable material, templates, assessments and simulations are for
              education, practice and general information. They are not, by themselves, legal advice, tax advice, audit
              assurance, accounting certification, investment advice, medical advice, engineering advice or a substitute
              for a professional engagement. Examples and explanations may be simplified and may not address your facts.
            </p>
            <p>
              Laws, forms, rates, procedures, software and official portals change. You must verify the current position
              from official sources and obtain advice from an appropriately qualified professional before acting in a
              specific matter. Erudsway does not guarantee examination results, qualifications, licences, job placement,
              business results, tax outcomes, approval of filings or regulatory acceptance.
            </p>
            <H>2. Consultations and professional providers</H>
            <p>
              The consultation listing or booking confirmation identifies who is responsible for the particular service
              and its scope. A professional participating on the Platform may be an independent contractor or service
              provider and is not automatically Erudsway’s employee, partner or agent. A professional-client engagement
              exists only if the relevant provider accepts it under separate engagement terms. Ask who will perform the
              work and what is included before paying.
            </p>
            <p>
              Each professional is responsible for maintaining applicable licences, qualifications, professional
              standards, independence, confidentiality and employer or professional-body permissions. Erudsway does not
              warrant an individual’s advice or guarantee a particular result. Nothing in this disclaimer excludes any
              responsibility that law places on Erudsway or the professional.
            </p>
            <H>3. Simulators and examples</H>
            <p>
              Simulators and portal demonstrations are mock learning environments. They do not file documents, make
              payments, verify legal status or communicate with a government or professional-body system unless a
              specific tool expressly says so. Practice outputs must not be relied upon as a submitted filing, official
              acknowledgement or professional opinion.
            </p>
            <H>4. No endorsement</H>
            <p>
              Course completion certificates, attendance records and assessment results are issued only as described in
              the relevant course. Unless we expressly state otherwise and hold the required authorisation, they are not
              a degree, statutory credential, professional-body membership, licence or government accreditation. Mention
              of an authority, law, product or third-party service is for context and does not imply endorsement.
            </p>
          </Section>

          <Section id="cookies">
            <H>1. What cookies do</H>
            <p>
              Cookies and similar technologies are small files or identifiers used by a website or service. Depending on
              the Platform features enabled, Erudsway and its service providers may use them to keep you signed in,
              maintain security and session state, remember preferences, measure service performance, understand
              aggregate usage, or support features you request.
            </p>
            <H>2. Categories</H>
            <ul className="list-disc space-y-1.5 pl-5">
              <li>
                <strong className="text-ink">Essential:</strong> account authentication, security, load balancing,
                payment flow and core site functions.
              </li>
              <li>
                <strong className="text-ink">Preference:</strong> remembering choices such as language or display
                settings.
              </li>
              <li>
                <strong className="text-ink">Analytics:</strong> understanding which pages or features work and
                diagnosing errors.
              </li>
              <li>
                <strong className="text-ink">Advertising or embedded media:</strong> used only where such tools are
                enabled and subject to applicable consent requirements and the provider’s terms.
              </li>
            </ul>
            <H>3. Your choices</H>
            <p>
              Where required, non-essential cookies will be activated only after you make the relevant choice through
              the cookie banner or settings tool. You can also manage cookies through your browser; blocking essential
              cookies may prevent login, payment or course access from working. Browser-level deletion may not
              automatically withdraw a choice stored in a separate consent tool.
            </p>
            <H>4. Third parties and updates</H>
            <p>
              Third-party video, analytics, payment and embedded services may set their own cookies when you use them.
              Their use is governed by their notices and settings. We will update this policy if our cookie practices
              materially change. The published notice should match the cookies and tracking tools actually enabled on
              the live Platform.
            </p>
          </Section>

          <Section id="delivery">
            <H>1. Digital delivery</H>
            <p>
              Courses, resources, simulator access and other digital products are delivered through the account, email,
              LMS or other delivery channel identified at checkout. Access normally begins after successful payment and
              order confirmation. Check your email, spam folder and account dashboard before contacting support.
            </p>
            <H>2. Access period and devices</H>
            <p>
              The course page or checkout will state the access period, where limited. If no end date is shown, access
              is provided while the course remains available on the Platform, subject to these policies, lawful changes
              and reasonable technical maintenance. Access is personal to the named learner unless an institutional or
              multi-user licence is stated. Keep your login secure; sharing may result in suspension.
            </p>
            <p>
              You are responsible for internet connectivity, compatible devices, software and any data charges. A stable
              broadband connection, current browser and supported media player may be needed. Downloadable materials are
              available only where the course page expressly provides them. Downloads remain subject to the licence in
              the Terms.
            </p>
            <H>3. Live delivery and appointments</H>
            <p>
              Live class links, venue details, session times, time zone, access instructions and any required
              preparation will be sent to the contact details supplied at booking. Join early and use the correct
              account. A participant is responsible for checking schedule messages and informing support of contact
              changes. Appointment-specific terms govern consultation access and rescheduling.
            </p>
            <H>4. Access issues</H>
            <p>
              If access is missing, delayed or not functioning, email <Mail /> with the registered account and order
              reference. We may request a browser/device description or screenshot that does not expose passwords,
              payment credentials or another person’s data. We will make reasonable efforts to restore access or resolve
              a verified delivery issue.
            </p>
          </Section>

          <Section id="conduct">
            <H>1. Learning environment</H>
            <p>
              Erudsway supports practical learning through courses, Tamil explanations with English technical
              terminology where appropriate, demonstrations, exercises, assessments, simulators, resources, expert
              sessions and institutional training. Course availability and features vary by listing. A learner is
              responsible for their own study, assessment submissions and decisions based on learning materials.
            </p>
            <H>2. Learner conduct</H>
            <p>
              Use the Platform for lawful learning and professional development. Treat learners, instructors, support
              staff and community members respectfully. Do not harass, threaten, discriminate, impersonate, spam, cheat,
              disrupt a class or assessment, publish another person’s private information, or ask others to violate
              professional confidentiality.
            </p>
            <p>
              Do not redistribute class recordings, notes, slides, templates, question banks, course logins or
              assessment material. Do not record a live session unless the course expressly authorises recording. Do not
              remove notices of ownership or use Erudsway’s brand to imply a credential, affiliation or endorsement you
              do not have.
            </p>
            <H>3. Safe use of simulations and materials</H>
            <p>
              Use fictional or anonymised facts in practice exercises. Do not upload real client, employer, taxpayer,
              student or financial records to a public forum or simulator. Do not use mock outputs as official filings
              or submit them to a live authority without independent verification. Do not reverse engineer, tamper with
              or bypass simulator controls.
            </p>
            <H>4. Forum and Knowledge Hub rules</H>
            <p>
              Stay on topic, identify assumptions, and distinguish opinion from verified law or fact. Do not present
              yourself as speaking for Erudsway or an authority without written permission. Report abusive, infringing,
              unsafe or confidential material to support. We may moderate or remove posts, restrict features and suspend
              accounts as described in the Terms and where permitted by law.
            </p>
            <H>5. Enforcement</H>
            <p>
              We may issue a warning, remove content, restrict access, suspend or terminate an account, withhold a
              certificate where an assessment was compromised, or refer suspected unlawful conduct to an appropriate
              authority. We will consider the seriousness and context of the conduct and follow applicable legal
              requirements. These measures do not limit other remedies available to Erudsway or affected persons.
            </p>
          </Section>

          <Section id="grievance">
            <H>1. Grievance Officer</H>
            <dl className="grid gap-x-6 gap-y-2 rounded-md border border-line bg-white p-5 text-sm sm:grid-cols-[180px_1fr]">
              <dt className="text-stone-500">Grievance Officer</dt>
              <dd className="text-ink">
                To be appointed — until then, grievances are handled at the contact details below.
              </dd>
              <dt className="text-stone-500">Entity</dt>
              <dd className="text-ink">Erudsway Educorp LLP</dd>
              <dt className="text-stone-500">Email</dt>
              <dd>
                <Mail subject="Grievance" />
              </dd>
              <dt className="text-stone-500">Phone/WhatsApp</dt>
              <dd>
                <Phone />
              </dd>
              <dt className="text-stone-500">Correspondence address</dt>
              <dd className="text-ink">{ADDRESS}</dd>
            </dl>
            <H>2. How to submit a complaint</H>
            <p>
              Email <Mail subject="Grievance" /> with “Grievance” in the subject, or write to the correspondence address
              above. Include your name, contact details, account email or phone, order/appointment reference, a concise
              description and the resolution requested. Do not send passwords, one-time codes or unrelated sensitive
              records.
            </p>
            <p>
              We will acknowledge consumer complaints within 48 hours and endeavour to resolve them within one month of
              receipt, or within a shorter period where applicable law requires. We will provide a complaint reference
              where available. From 1 January 2027, we will also provide the complainant with a copy of the complaint as
              recorded by the Grievance Officer and participate in the National Consumer Helpline convergence process,
              as required by the amended e-commerce rules. If the matter concerns content or conduct in a forum,
              identify the relevant page, post or user and explain the issue. We will assess and act within the
              timelines required by applicable law.
            </p>
            <H>3. Other remedies</H>
            <p>
              Contacting Erudsway does not prevent you from approaching a consumer commission, regulator,
              law-enforcement authority, court or other competent forum. Nothing in this policy requires you to waive a
              statutory remedy or limits the jurisdiction of a competent authority.
            </p>
          </Section>

          <Section id="changes">
            <p>
              We may revise these policies to reflect new Platform features, service arrangements, security practices or
              legal requirements. The “Last updated” and “Effective from” dates show when a revision was made and when
              it applies. If a change materially affects an active paid service or the way personal information is used,
              we will provide notice through the Platform, email or another appropriate channel where required.
            </p>
            <p>
              The version published on erudsway.com applies to future use from its effective date. A change does not
              retroactively remove rights or remedies that applicable law preserves. Please review this page
              periodically. If you do not agree to a revised term, stop using the affected feature and contact us about
              any active order.
            </p>
            <p>
              For questions about these policies, write to <Mail /> or call/WhatsApp <Phone />.
            </p>
          </Section>
        </div>
      </div>
    </div>
  );
}
