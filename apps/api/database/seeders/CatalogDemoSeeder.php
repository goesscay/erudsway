<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\InstructorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local demo catalogue for an accounts, tax and finance learning portal.
 * Instructors and courses are fictional; course images are Unsplash photos.
 */
class CatalogDemoSeeder extends Seeder
{
    /** Top-level areas (as planned by Erudsway) => [description, subcategories]. */
    private const CATEGORIES = [
        'GST' => ['Registration, returns, input tax credit and e-invoicing — GST as practitioners apply it.',
            ['Registration & Returns', 'Input Tax Credit', 'E-invoicing & E-way Bills']],
        'Income Tax' => ['Return filing, capital gains and planning for individuals and businesses.',
            ['Return Filing', 'Capital Gains', 'Tax Planning']],
        'TDS & TCS' => ['Deduction, collection, quarterly returns and certificates without penalties.',
            ['TDS Compliance', 'TCS']],
        'Accounts & Career' => ['Bookkeeping, Tally, Excel and the career skills accountants need.',
            ['Bookkeeping & Tally', 'Excel for Finance', 'Career Guidance']],
        'Audit & Assurance' => ['Statutory, tax and internal audit — planning, evidence and reporting.',
            ['Statutory Audit', 'Tax Audit', 'Internal Audit']],
        'ROC & Company Law' => ['Incorporation, annual filings and ongoing compliance for companies and LLPs.',
            ['Company Compliance', 'LLP', 'Incorporation']],
        'Litigation & Appeals' => ['Replying to notices, drafting submissions and handling appeals.',
            ['GST Litigation', 'Income Tax Appeals']],
        'FEMA & Cross-border' => ['FDI, ODI, reporting to RBI and the basics of international taxation.',
            ['FEMA Compliance', 'International Taxation']],
        'IPO & Capital Markets' => ['How companies go public and how SEBI regulates listed entities.',
            ['IPO & SEBI Regulations', 'Capital Markets']],
        'Startup & Setup' => ['Choosing an entity, registering it and staying compliant in year one.',
            ['Business Registration', 'Funding & Valuation']],
    ];

    private const PRICE_TIERS = [39900, 44900, 49900, 59900, 79900, 99900, 129900, 199900, 349900];

    private const INSTRUCTORS = [
        'vikram' => ['CA Vikram Sethi', 'Chartered Accountant · Indirect tax practitioner, 15 years',
            'Vikram runs a GST-focused practice in Delhi and has represented clients before the GST authorities since the law came into force. His courses follow the monthly compliance cycle exactly as his team works it.'],
        'nandini' => ['CA Nandini Iyer', 'Chartered Accountant · Direct tax and TDS specialist',
            'Nandini advises salaried professionals, family businesses and NRIs on income tax. She has filed thousands of returns and teaches with real (anonymised) cases from her Chennai practice.'],
        'rohan' => ['CS Rohan Deshpande', 'Company Secretary · Corporate law and capital markets',
            'Rohan was company secretary of a listed manufacturing company and has worked on two IPOs. He now advises startups and mid-sized companies on ROC and SEBI compliance from Pune.'],
        'farah' => ['Adv. Farah Qureshi', 'Advocate · Tax litigation and FEMA',
            'Farah appears before the ITAT and GST appellate authorities and advises on cross-border structuring. She teaches drafting the way she reviews it: precise, sourced and persuasive.'],
        'arjun' => ['CA Arjun Menon', 'Chartered Accountant · Audit partner and mentor',
            'Arjun leads statutory and internal audits for listed and unlisted companies in Kochi and has mentored more than 300 articled assistants through their training.'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            // Remove demo data from earlier taxonomies so the catalogue matches this one exactly.
            Course::withTrashed()->whereNotIn('slug', array_column($this->courses(), 'slug'))->forceDelete();
            $categories = $this->categories();
            $tiers = collect(self::PRICE_TIERS)->mapWithKeys(fn (int $amount) => [
                $amount => PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => $amount])->id,
            ]);
            $instructors = collect(self::INSTRUCTORS)->map(fn (array $i, string $key) => $this->instructor($key, ...$i));

            foreach ($this->courses() as $i => $c) {
                $this->course($i, $c, $instructors[$c['by']], $categories[$c['category']], $c['price'] ? $tiers[$c['price']] : null);
            }
        });
    }

    /** @return array<string, int> subcategory name => id */
    private function categories(): array
    {
        $keep = [];
        $ids = [];
        $position = 0;
        foreach (self::CATEGORIES as $parentName => [$description, $children]) {
            $parent = Category::query()->updateOrCreate(
                ['slug' => str($parentName)->slug()->toString()],
                ['name' => $parentName, 'description' => $description, 'parent_id' => null, 'position' => $position++, 'is_active' => true],
            );
            $keep[] = $parent->id;
            foreach ($children as $j => $name) {
                $child = Category::query()->updateOrCreate(
                    ['slug' => str($name)->slug()->toString()],
                    ['name' => $name, 'parent_id' => $parent->id, 'position' => $j, 'is_active' => true],
                );
                $ids[$name] = $child->id;
                $keep[] = $child->id;
            }
        }
        // Retire categories from earlier demo taxonomies (children first for the self-referencing FK).
        Category::query()->whereNotIn('id', $keep)->whereNotNull('parent_id')->delete();
        Category::query()->whereNotIn('id', $keep)->delete();

        return $ids;
    }

    private function instructor(string $key, string $name, string $headline, string $bio): User
    {
        $user = User::query()->firstOrCreate(['email' => "{$key}@instructors.erudsway.test"], ['name' => $name, 'password' => str()->random(32)]);
        $user->forceFill(['name' => $name, 'email_verified_at' => now()])->save();
        $user->profile()->firstOrCreate();
        $user->syncRoles([Role::Student->value, Role::Instructor->value]);
        InstructorProfile::query()->updateOrCreate(['user_id' => $user->id], [
            'slug' => str(preg_replace('/^(CA|CS|Adv\.)\s+/', '', $name))->slug()->toString(),
            'display_name' => $name,
            'headline' => $headline,
            'bio' => $bio,
            'approved_at' => now()->subYear(),
        ]);

        return $user;
    }

    /** @param array<string, mixed> $c */
    private function course(int $index, array $c, User $instructor, int $categoryId, ?int $tierId): void
    {
        mt_srand(1000 + $index); // deterministic demo numbers

        $course = Course::query()->updateOrCreate(['slug' => $c['slug']], [
            'instructor_id' => $instructor->id,
            'category_id' => $categoryId,
            'price_tier_id' => $tierId,
            'title' => $c['title'],
            'subtitle' => $c['subtitle'],
            'description' => '<p>'.implode('</p><p>', $c['description']).'</p>',
            'language' => $c['language'] ?? 'English',
            'level' => $c['level'],
            'thumbnail_path' => "https://images.unsplash.com/{$c['image']}?auto=format&fit=crop&w=960&q=80",
            'outcomes' => $c['outcomes'],
            'requirements' => $c['requirements'] ?? ['Basic familiarity with accounting terms', 'A laptop to follow the worked examples'],
            'audience' => $c['audience'] ?? ['Accountants, articled assistants, finance executives and business owners'],
        ]);

        $course->sections()->delete();
        $seconds = 0;
        $lectures = 0;
        foreach ($c['sections'] as $s => [$sectionTitle, $lectureTitles]) {
            $section = $course->sections()->create(['title' => $sectionTitle, 'position' => $s + 1]);
            foreach ($lectureTitles as $l => $lectureTitle) {
                $kind = str_starts_with($lectureTitle, 'Quiz:') ? LectureKind::Quiz
                    : (str_starts_with($lectureTitle, 'Reading:') ? LectureKind::Article : LectureKind::Video);
                $duration = $kind === LectureKind::Video ? mt_rand(6, 24) * 60 + mt_rand(0, 59) : ($kind === LectureKind::Quiz ? 300 : 240);
                $section->lectures()->create([
                    'course_id' => $course->id,
                    'title' => preg_replace('/^(Quiz|Reading): /', '', $lectureTitle),
                    'kind' => $kind,
                    'position' => $l + 1,
                    'duration_seconds' => $duration,
                    'is_free_preview' => $s === 0 && $l < 2 && $kind === LectureKind::Video,
                ]);
                $seconds += $duration;
                $lectures++;
            }
        }

        $course->forceFill([
            'status' => CourseStatus::Published,
            'published_at' => now()->subDays($c['age_days']),
            'enrollment_count' => $c['learners'],
            'rating_count' => (int) round($c['learners'] * 0.17),
            'rating_avg' => $c['rating'],
            'duration_seconds' => $seconds,
            'lecture_count' => $lectures,
        ])->save();
    }

    /**
     * Generic syllabus shape for demo courses: intro, one section per topic, wrap-up.
     *
     * @param  list<string>  $topics
     * @return list<array{0: string, 1: list<string>}>
     */
    private function syllabus(array $topics): array
    {
        $sections = [['Getting started', ['Welcome and how this course is organised', 'The law and where to find it', 'Reading: Templates, checklists and bare-act extracts']]];
        foreach ($topics as $i => $topic) {
            $sections[] = [$topic, [
                "{$topic}: the provisions explained",
                'Worked case study',
                'Common errors we see in practice',
                'Practical exercise with solution',
                ...($i % 2 === 1 ? ["Quiz: {$topic}"] : []),
            ]];
        }
        $sections[] = ['Wrapping up', ['Compliance calendar and checklist', 'Recent amendments and how to keep up']];

        return $sections;
    }

    /** @return list<array<string, mixed>> */
    private function courses(): array
    {
        return [
            // GST
            [
                'slug' => 'gst-practitioner-course', 'by' => 'vikram', 'category' => 'Registration & Returns',
                'title' => 'GST Practitioner Course: Registration to Annual Return',
                'subtitle' => 'The complete monthly and annual GST cycle — registrations, invoices, GSTR-1, GSTR-3B, ITC and GSTR-9 — as a practising CA runs it.',
                'description' => [
                    'This course follows one fictional trading client through a full year of GST compliance. You will register the business, raise compliant invoices, file monthly returns, reconcile input tax credit and close the year with the annual return.',
                    'Every lecture uses the actual GST portal screens and the forms you will see in practice, with downloadable working files.',
                ],
                'level' => 'all', 'price' => 49900, 'learners' => 48210, 'rating' => 4.8, 'age_days' => 420,
                'image' => 'photo-1711097383282-28097ae16b1d',
                'outcomes' => [
                    'Register businesses under GST, including amendments and cancellations',
                    'Prepare and file GSTR-1 and GSTR-3B without late fees or mismatches',
                    'Reconcile input tax credit with GSTR-2B every month',
                    'Apply place-of-supply and reverse-charge rules correctly',
                    'Prepare the annual return (GSTR-9) and reconciliation statement',
                    'Respond to common system-generated notices',
                ],
                'sections' => [
                    ['Getting oriented', ['How this course is organised', 'GST in one picture: supply, tax and credit', 'Reading: Working files and checklists']],
                    ['Registration', ['Who must register, and when', 'Registering on the GST portal, step by step', 'Amendments, cancellation and revocation', 'Quiz: Registration']],
                    ['Supply, valuation and place of supply', ['What counts as a supply', 'Valuation rules with examples', 'Place of supply for goods and services', 'Reverse charge mechanism']],
                    ['Invoices and monthly returns', ['Tax invoices, credit notes and debit notes', 'Filing GSTR-1', 'Filing GSTR-3B', 'Quiz: Monthly returns']],
                    ['Input tax credit', ['Conditions to claim ITC', 'Blocked credits under Section 17(5)', 'Reconciling with GSTR-2B', 'Reversals and interest']],
                    ['Year end', ['GSTR-9: the annual return', 'Reconciliation statement', 'Compliance calendar and checklist']],
                ],
            ],
            [
                'slug' => 'gst-input-tax-credit-reconciliation', 'by' => 'vikram', 'category' => 'Input Tax Credit',
                'title' => 'Input Tax Credit: Rules, Reconciliation and Reversals',
                'subtitle' => 'Claim every rupee you are entitled to — and nothing you are not — with a monthly reconciliation routine.',
                'description' => ['Input tax credit is where most GST disputes start. This course builds a reconciliation routine in Excel and walks through the rules behind each adjustment.'],
                'level' => 'intermediate', 'price' => 44900, 'learners' => 16480, 'rating' => 4.7, 'age_days' => 200,
                'image' => 'photo-1709880945165-d2208c6ad2ec',
                'outcomes' => ['Apply the conditions and time limits for ITC', 'Identify blocked credits', 'Run a monthly GSTR-2B reconciliation in Excel', 'Compute reversals and interest correctly'],
                'sections' => $this->syllabus(['Eligibility and conditions', 'Blocked credits', 'GSTR-2B reconciliation', 'Reversals and interest']),
            ],
            [
                'slug' => 'e-invoicing-e-way-bills', 'by' => 'vikram', 'category' => 'E-invoicing & E-way Bills',
                'title' => 'E-invoicing and E-way Bills Made Simple',
                'subtitle' => 'Generate IRNs and e-way bills correctly, and fix the errors that stop goods on the road.',
                'description' => ['A short, practical course on the e-invoicing and e-way bill systems for businesses that have just crossed the thresholds.'],
                'level' => 'beginner', 'language' => 'Hindi', 'price' => null, 'learners' => 22390, 'rating' => 4.6, 'age_days' => 35,
                'image' => 'photo-1626266061368-46a8f578ddd6',
                'outcomes' => ['Know when e-invoicing and e-way bills apply', 'Generate and cancel IRNs and e-way bills', 'Fix common validation errors'],
                'sections' => $this->syllabus(['E-invoicing', 'E-way bills']),
            ],
            // Income Tax
            [
                'slug' => 'income-tax-return-filing', 'by' => 'nandini', 'category' => 'Return Filing',
                'title' => 'Income Tax Return Filing: ITR-1 to ITR-4 in Practice',
                'subtitle' => 'File returns for salaried people, freelancers and small businesses — old and new regimes compared.',
                'description' => ['Learn to prepare and file the four most common ITR forms from start to finish, including AIS/TIS reconciliation and choosing between tax regimes.'],
                'level' => 'beginner', 'price' => 44900, 'learners' => 39870, 'rating' => 4.8, 'age_days' => 310,
                'image' => 'photo-1554224155-cfa08c2a758f',
                'outcomes' => ['Choose the right ITR form and regime', 'Reconcile income with AIS and Form 26AS', 'File ITR-1 to ITR-4 end to end', 'Handle revised and belated returns'],
                'sections' => $this->syllabus(['Residential status and heads of income', 'Old vs new regime', 'AIS and 26AS reconciliation', 'Filing on the e-filing portal']),
            ],
            [
                'slug' => 'capital-gains-taxation', 'by' => 'nandini', 'category' => 'Capital Gains',
                'title' => 'Capital Gains: Shares, Mutual Funds and Property',
                'subtitle' => 'Compute short- and long-term gains, apply exemptions and report them correctly in your return.',
                'description' => ['Worked examples covering listed shares, mutual funds, property sales and the exemptions under Sections 54 and 54F.'],
                'level' => 'intermediate', 'price' => 59900, 'learners' => 14220, 'rating' => 4.7, 'age_days' => 25,
                'image' => 'photo-1554224155-6726b3ff858f',
                'outcomes' => ['Classify gains as short or long term', 'Compute gains on shares, funds and property', 'Apply Section 54 and 54F exemptions', 'Report gains in Schedule CG'],
                'sections' => $this->syllabus(['Shares and mutual funds', 'Property', 'Exemptions', 'Reporting in the return']),
            ],
            // TDS & TCS
            [
                'slug' => 'tds-tcs-compliance', 'by' => 'nandini', 'category' => 'TDS Compliance',
                'title' => 'TDS & TCS Compliance: Deduction, Returns and Certificates',
                'subtitle' => 'Rates, thresholds, quarterly returns, Form 16/16A and correcting defaults — the full TDS cycle.',
                'description' => ['Built around the quarterly cycle: deduct correctly, deposit on time, file 24Q/26Q/27EQ and issue certificates — then fix defaults when they appear on TRACES.'],
                'level' => 'all', 'price' => 49900, 'learners' => 27530, 'rating' => 4.7, 'age_days' => 260,
                'image' => 'photo-1554224154-26032ffc0d07',
                'outcomes' => ['Apply the right section, rate and threshold', 'File quarterly TDS and TCS returns', 'Issue Form 16 and 16A', 'Resolve TRACES defaults and file corrections'],
                'sections' => $this->syllabus(['Sections, rates and thresholds', 'Deposits and challans', 'Quarterly returns', 'TRACES and corrections']),
            ],
            // Accounts & Career
            [
                'slug' => 'tally-prime-with-gst', 'by' => 'arjun', 'category' => 'Bookkeeping & Tally',
                'title' => 'Tally Prime with GST: Practical Accounting',
                'subtitle' => 'Record a business’s full year in Tally Prime — vouchers, inventory, GST and final accounts.',
                'description' => ['Hands-on bookkeeping for beginners and career switchers. You will maintain a trading company’s books for a full year and prepare its final accounts.'],
                'level' => 'beginner', 'language' => 'Tamil', 'price' => 39900, 'learners' => 33610, 'rating' => 4.6, 'age_days' => 380,
                'image' => 'photo-1724985284026-dd2451e4857a',
                'outcomes' => ['Create companies, ledgers and stock items', 'Record all common voucher types', 'Generate GST reports from Tally', 'Prepare trial balance and final accounts'],
                'sections' => $this->syllabus(['Masters and setup', 'Vouchers', 'Inventory', 'GST in Tally', 'Final accounts']),
            ],
            [
                'slug' => 'excel-for-accountants', 'by' => 'arjun', 'category' => 'Excel for Finance',
                'title' => 'Excel for Accountants: Reconciliations, MIS and Schedules',
                'subtitle' => 'XLOOKUP, pivot tables and Power Query for the reports accountants build every month.',
                'description' => ['Stop rebuilding the same workbook every month. Build reusable reconciliations, MIS reports and audit schedules.'],
                'level' => 'beginner', 'price' => 39900, 'learners' => 41950, 'rating' => 4.7, 'age_days' => 560,
                'image' => 'photo-1460925895917-afdab827c52f',
                'outcomes' => ['Reconcile bank and ledger data quickly', 'Build monthly MIS with pivot tables', 'Clean data with Power Query', 'Prepare audit-ready schedules'],
                'sections' => $this->syllabus(['Clean data first', 'Lookups and reconciliation', 'Pivot tables and MIS', 'Power Query']),
            ],
            [
                'slug' => 'articleship-to-practice-career-guide', 'by' => 'arjun', 'category' => 'Career Guidance',
                'title' => 'From Articleship to Practice: A Career Guide for Accountants',
                'subtitle' => 'What firms look for, how to grow during articleship, and the paths open to you after qualifying.',
                'description' => ['A free, candid guide for students and newly qualified accountants, based on mentoring hundreds of articled assistants.'],
                'level' => 'beginner', 'price' => null, 'learners' => 25480, 'rating' => 4.8, 'age_days' => 90,
                'image' => 'photo-1517048676732-d65bc937f952',
                'outcomes' => ['Choose an articleship that builds real skills', 'Prepare for interviews at firms and companies', 'Compare practice, industry and advisory careers'],
                'sections' => $this->syllabus(['Choosing a firm', 'Interviews', 'Career paths']),
            ],
            // Audit & Assurance
            [
                'slug' => 'statutory-audit-in-practice', 'by' => 'arjun', 'category' => 'Statutory Audit',
                'title' => 'Statutory Audit in Practice: Planning to CARO Reporting',
                'subtitle' => 'Risk assessment, sampling, working papers and the audit report — following the Standards on Auditing.',
                'description' => ['Follow a complete statutory audit of a mid-sized private company, from engagement letter to signed report and CARO.'],
                'level' => 'advanced', 'price' => 79900, 'learners' => 9640, 'rating' => 4.8, 'age_days' => 150,
                'image' => 'photo-1554224155-1696413565d3',
                'outcomes' => ['Plan an audit using risk assessment', 'Design and document audit procedures', 'Evaluate misstatements', 'Draft the audit report and CARO'],
                'sections' => $this->syllabus(['Engagement and planning', 'Risk assessment', 'Audit evidence and sampling', 'Reporting and CARO']),
            ],
            [
                'slug' => 'tax-audit-form-3cd', 'by' => 'nandini', 'category' => 'Tax Audit',
                'title' => 'Tax Audit under Section 44AB: Form 3CD Clause by Clause',
                'subtitle' => 'Applicability, documentation and every Form 3CD clause with the issues reviewers flag.',
                'description' => ['A clause-by-clause walkthrough of Form 3CD with sample working papers and reporting language.'],
                'level' => 'intermediate', 'price' => 59900, 'learners' => 12870, 'rating' => 4.6, 'age_days' => 340,
                'image' => 'photo-1504868584819-f8e8b4b6d7e3',
                'outcomes' => ['Determine tax audit applicability', 'Complete Form 3CA/3CB and 3CD', 'Document clause-wise working papers'],
                'sections' => $this->syllabus(['Applicability', 'Form 3CD clauses 1–20', 'Form 3CD clauses 21–44', 'Filing and UDIN']),
            ],
            // ROC & Company Law
            [
                'slug' => 'roc-annual-compliance', 'by' => 'rohan', 'category' => 'Company Compliance',
                'title' => 'ROC Annual Compliance: AOC-4, MGT-7 and Board Processes',
                'subtitle' => 'Board meetings, AGMs, registers and annual filings for private and public companies.',
                'description' => ['Everything a company must do each year under the Companies Act, 2013 — with a compliance calendar, minutes templates and MCA filing walkthroughs.'],
                'level' => 'intermediate', 'price' => 49900, 'learners' => 18320, 'rating' => 4.7, 'age_days' => 230,
                'image' => 'photo-1450101499163-c8848c66ca85',
                'outcomes' => ['Run compliant board meetings and AGMs', 'Maintain statutory registers', 'File AOC-4 and MGT-7 on MCA V3', 'Avoid common penalties'],
                'sections' => $this->syllabus(['Board meetings', 'General meetings', 'Statutory registers', 'Annual filings on MCA']),
            ],
            [
                'slug' => 'llp-formation-and-compliance', 'by' => 'rohan', 'category' => 'LLP',
                'title' => 'LLP Formation and Compliance',
                'subtitle' => 'Incorporate an LLP, draft the agreement and file Form 8 and Form 11 on time.',
                'description' => ['A practical guide to the LLP lifecycle for professionals advising small businesses.'],
                'level' => 'beginner', 'language' => 'Hindi', 'price' => 39900, 'learners' => 10560, 'rating' => 4.5, 'age_days' => 40,
                'image' => 'photo-1521791055366-0d553872125f',
                'outcomes' => ['Incorporate an LLP using FiLLiP', 'Draft key clauses of the LLP agreement', 'File annual returns on time'],
                'sections' => $this->syllabus(['Incorporation', 'LLP agreement', 'Annual compliance']),
            ],
            // Litigation & Appeals
            [
                'slug' => 'gst-notices-and-appeals', 'by' => 'farah', 'category' => 'GST Litigation',
                'title' => 'GST Notices and Appeals: Drafting Replies That Work',
                'subtitle' => 'Read a show cause notice, build the defence and draft replies and appeals the authorities take seriously.',
                'description' => ['Using redacted real notices, learn to structure replies, cite law and judgments, and file appeals within limitation.'],
                'level' => 'advanced', 'price' => 79900, 'learners' => 7830, 'rating' => 4.9, 'age_days' => 20,
                'image' => 'photo-1767972159871-b9f5d320be2b',
                'outcomes' => ['Analyse a show cause notice', 'Draft structured replies with authorities', 'File appeals under Section 107', 'Prepare for personal hearings'],
                'sections' => $this->syllabus(['Types of notices', 'Drafting replies', 'Appeals', 'Personal hearings']),
            ],
            [
                'slug' => 'income-tax-appeals-cita-itat', 'by' => 'farah', 'category' => 'Income Tax Appeals',
                'title' => 'Income Tax Appeals: From CIT(A) to the ITAT',
                'subtitle' => 'Grounds of appeal, statements of facts, paper books and arguing before the Tribunal.',
                'description' => ['A practitioner’s guide to the appellate process under the Income-tax Act, with sample grounds and paper books.'],
                'level' => 'advanced', 'price' => 99900, 'learners' => 5210, 'rating' => 4.8, 'age_days' => 180,
                'image' => 'photo-1774898988393-5c752e4d55e9',
                'outcomes' => ['Draft grounds of appeal and statements of facts', 'Prepare paper books', 'Present arguments before the ITAT'],
                'sections' => $this->syllabus(['Appeals before CIT(A)', 'Drafting grounds', 'The ITAT', 'Stay and rectification']),
            ],
            // FEMA & Cross-border
            [
                'slug' => 'fema-for-practitioners', 'by' => 'farah', 'category' => 'FEMA Compliance',
                'title' => 'FEMA for Practitioners: FDI, ODI and RBI Reporting',
                'subtitle' => 'Structure foreign investment in and out of India and file the FIRMS reports on time.',
                'description' => ['Covers inbound and outbound investment rules, pricing guidelines, reporting on FIRMS and compounding of contraventions.'],
                'level' => 'advanced', 'price' => 99900, 'learners' => 6120, 'rating' => 4.7, 'age_days' => 270,
                'image' => 'photo-1700394474173-6428c2ea061c',
                'outcomes' => ['Apply FDI routes and sectoral caps', 'Handle ODI and overseas investment rules', 'File FC-GPR, FC-TRS and ODI forms', 'Navigate compounding'],
                'sections' => $this->syllabus(['FDI', 'ODI', 'Reporting on FIRMS', 'Compounding']),
            ],
            [
                'slug' => 'international-taxation-dtaa', 'by' => 'nandini', 'category' => 'International Taxation',
                'title' => 'International Taxation and DTAA Basics',
                'subtitle' => 'Residency, permanent establishment, treaty benefits and withholding on payments abroad.',
                'description' => ['An introduction to cross-border tax for practitioners advising NRIs and companies paying foreign vendors.'],
                'level' => 'intermediate', 'price' => 79900, 'learners' => 8740, 'rating' => 4.6, 'age_days' => 120,
                'image' => 'photo-1653586432276-08241d72adb9',
                'outcomes' => ['Determine residency and taxability', 'Apply DTAA provisions and TRC requirements', 'Compute withholding under Section 195'],
                'sections' => $this->syllabus(['Residency', 'Permanent establishment', 'Treaty benefits', 'Section 195 withholding']),
            ],
            // IPO & Capital Markets
            [
                'slug' => 'ipo-process-sebi-icdr', 'by' => 'rohan', 'category' => 'IPO & SEBI Regulations',
                'title' => 'The IPO Process under SEBI ICDR: From DRHP to Listing',
                'subtitle' => 'Eligibility, due diligence, offer documents, book building and post-listing obligations.',
                'description' => ['A walkthrough of an Indian IPO from the company secretary’s desk, covering the roles of merchant bankers, lawyers and auditors.'],
                'level' => 'advanced', 'price' => 129900, 'learners' => 4870, 'rating' => 4.8, 'age_days' => 60,
                'image' => 'photo-1611974789855-9c2a0a7236a3',
                'outcomes' => ['Assess IPO eligibility', 'Understand the DRHP and RHP', 'Follow book building and allotment', 'Meet LODR obligations after listing'],
                'sections' => $this->syllabus(['Eligibility and readiness', 'Offer documents', 'Book building and listing', 'Post-listing compliance']),
            ],
            [
                'slug' => 'capital-markets-fundamentals', 'by' => 'rohan', 'category' => 'Capital Markets',
                'title' => 'Capital Markets Fundamentals for Finance Professionals',
                'subtitle' => 'How exchanges, depositories and intermediaries work, and how SEBI regulates them.',
                'description' => ['An education-focused course on Indian capital markets. It does not recommend securities and is not investment advice.'],
                'level' => 'beginner', 'price' => 44900, 'learners' => 15920, 'rating' => 4.6, 'age_days' => 330,
                'image' => 'photo-1560221328-12fe60f83ab8',
                'outcomes' => ['Explain how trades clear and settle', 'Describe the role of each market intermediary', 'Read corporate announcements and results'],
                'sections' => $this->syllabus(['Market structure', 'Intermediaries', 'Regulation', 'Corporate disclosures']),
            ],
            // Startup & Setup
            [
                'slug' => 'starting-up-in-india', 'by' => 'rohan', 'category' => 'Business Registration',
                'title' => 'Starting Up in India: Entity, Registration and Year-One Compliance',
                'subtitle' => 'Proprietorship, LLP or private company? Choose, register and stay compliant in your first year.',
                'description' => ['A practical guide for founders and their advisers: choosing the entity, incorporation, GST and other registrations, Startup India recognition and a first-year compliance calendar.'],
                'level' => 'beginner', 'price' => 44900, 'learners' => 20310, 'rating' => 4.7, 'age_days' => 15,
                'image' => 'photo-1542744173-8e7e53415bb0',
                'outcomes' => ['Choose the right legal entity', 'Complete incorporation and registrations', 'Apply for Startup India recognition', 'Follow a first-year compliance calendar'],
                'sections' => $this->syllabus(['Choosing an entity', 'Incorporation and registrations', 'Startup India', 'Year-one compliance']),
            ],
            [
                'slug' => 'startup-funding-and-valuation', 'by' => 'arjun', 'category' => 'Funding & Valuation',
                'title' => 'Startup Funding and Valuation Basics',
                'subtitle' => 'Term sheets, cap tables, valuation reports and the tax and FEMA angles of raising money.',
                'description' => ['What founders and finance teams need to know before raising an angel or seed round.'],
                'level' => 'intermediate', 'price' => 59900, 'learners' => 9350, 'rating' => 4.6, 'age_days' => 110,
                'image' => 'photo-1568992687947-868a62a9f521',
                'outcomes' => ['Read a term sheet', 'Maintain a cap table', 'Understand valuation methods and reports', 'Spot tax and FEMA issues in funding'],
                'sections' => $this->syllabus(['Instruments and term sheets', 'Cap tables', 'Valuation', 'Tax and FEMA']),
            ],
        ];
    }
}
