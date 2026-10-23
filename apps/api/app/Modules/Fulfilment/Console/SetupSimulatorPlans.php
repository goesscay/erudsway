<?php

namespace App\Modules\Fulfilment\Console;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Finance\Services\CommissionRules;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\InstructorProfile;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the product that sells the GST simulator on its own: a short published course whose
 * editions are the plans (3 and 12 months), sold by an "Erudsway" platform account that keeps
 * the whole amount. Safe to run again: it only creates what's missing.
 */
class SetupSimulatorPlans extends Command
{
    protected $signature = 'simulator:setup-plans
        {--three-months=1999 : Price of the 3-month plan in rupees, GST included}
        {--twelve-months=4999 : Price of the 12-month plan in rupees, GST included}';

    protected $description = 'Create the GST simulator plans (a course with 3- and 12-month editions) if they don’t exist';

    private const PLATFORM_EMAIL = 'simulator-plans@erudsway.com';

    public function handle(CommissionRules $commission, CourseStats $stats, AuditLogger $audit): int
    {
        $slug = (string) config('services.simulator.plans_course');
        if (Course::query()->where('slug', $slug)->exists()) {
            $this->info("The simulator plans course “{$slug}” already exists. Change prices in the course builder’s editions.");

            return self::SUCCESS;
        }
        $admin = User::role(Role::SuperAdmin->value)->orderBy('id')->first() ?? User::role(Role::Admin->value)->orderBy('id')->first();
        $category = Category::query()->where('slug', 'registration-returns')->first() ?? Category::query()->where('slug', 'gst')->first();
        if ($admin === null || $category === null) {
            $this->error('Needs an admin account and the GST category first.');

            return self::FAILURE;
        }
        $three = PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => (int) $this->option('three-months') * 100], ['is_active' => true]);
        $twelve = PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => (int) $this->option('twelve-months') * 100], ['is_active' => true]);

        $course = DB::transaction(function () use ($slug, $category, $three, $twelve, $stats) {
            $seller = $this->platformSeller();
            $course = Course::query()->create([
                'instructor_id' => $seller->id,
                'category_id' => $category->id,
                'price_tier_id' => $three->id,
                'slug' => $slug,
                'title' => 'GST Simulator: Practise Returns on a Real-Style GST Portal',
                'subtitle' => 'Your own practice GSTIN to file GSTR-1 and GSTR-3B, work IMS, reconcile ITC and pay challans — with zero risk.',
                'description' => '<p>A practice login on a GST portal simulator that looks and behaves like the real one. File returns, reconcile input tax credit, act on IMS and pay challans with realistic practice data — so the first time you do it for a client isn’t the first time you’ve done it.</p>'
                    .'<p>Every login comes with a supplier and a matching recipient, and three months of practice data, so you can see both sides of every invoice.</p>',
                'language' => 'English',
                'level' => CourseLevel::All,
                'outcomes' => [
                    'File GSTR-1 (B2B, B2C, credit notes, HSN summary) and check the summary before filing',
                    'Prepare and file GSTR-3B, set off liability and pay through the cash and credit ledgers',
                    'Accept, reject or keep invoices pending in IMS and see their effect on GSTR-2B',
                    'Reconcile purchases with GSTR-2A/2B and build an ITC working paper',
                    'Create challans (PMT-06), pay demands and practise refunds and TDS/TCS credits',
                ],
                'requirements' => ['Basic GST concepts (supply, ITC, returns)', 'A laptop or desktop browser'],
                'audience' => ['Commerce students and articled assistants', 'Accountants and GST practitioners', 'Business owners who file their own returns'],
            ]);
            $course->forceFill(['status' => CourseStatus::Published, 'published_at' => now(), 'pricing_confirmed_at' => now()])->save();

            $base = ['grants_simulator' => true, 'includes_doubt_session' => false, 'video_view_limit' => null];
            $includes = ['Your own practice GSTIN (supplier + recipient)', 'GSTR-1, 1A, 2A, 2B, 3B and IMS', 'Challans, ledgers, refunds and TDS/TCS', 'ITC reconciliation and guided exercises'];
            $course->editions()->create([...$base, 'position' => 1, 'name' => '3 months', 'price_tier_id' => $three->id, 'access_days' => 90, 'includes' => $includes, 'is_recommended' => false]);
            $course->editions()->create([...$base, 'position' => 2, 'name' => '12 months', 'price_tier_id' => $twelve->id, 'access_days' => 365, 'includes' => ['A full year of monthly and quarterly filing cycles', 'The lowest price per month'], 'is_recommended' => true]);

            $section = $course->sections()->create(['title' => 'Getting started', 'position' => 1]);
            $html = '<h3>Your practice login</h3><p>As soon as your plan starts, a practice GST login is created for you. Open it from your course page with one click — or sign in on the simulator with the username and password shown there.</p>'
                .'<h3>What to practise first</h3><ol><li>Look around the dashboard and the return calendar.</li><li>File GSTR-1 for the latest month: add a B2B invoice, check the summary, then file.</li><li>Open GSTR-2B and IMS as the recipient and act on that invoice.</li><li>Prepare GSTR-3B, offset your liability and pay the balance by challan.</li></ol>'
                .'<h3>When your plan ends</h3><p>We’ll remind you a few days before. Renew before the end date to keep the same login and your practice returns.</p>';
            $section->lectures()->create([
                'course_id' => $course->id,
                'title' => 'How your GST simulator access works',
                'kind' => LectureKind::Article,
                'position' => 1,
                'article_html' => $html,
                'duration_seconds' => 120,
                'is_free_preview' => true,
            ]);
            $stats->refresh($course);

            return $course;
        });

        // The platform keeps the whole amount: no instructor share on any channel.
        foreach (SaleChannel::cases() as $channel) {
            if ($commission->rateFor($channel, $course->instructor_id) !== 0) {
                $commission->set($admin, $channel, $course->instructor_id, 0);
            }
        }
        $audit->record('simulator.plans_created', $course, null, ['three_months' => $three->amount, 'twelve_months' => $twelve->amount], $admin);
        $this->info("Created and published “{$course->title}” ({$slug}) with 3- and 12-month plans.");

        return self::SUCCESS;
    }

    /** The account that "sells" the plans, shown as the course's creator. It can't sign in. */
    private function platformSeller(): User
    {
        $user = User::query()->firstOrCreate(['email' => self::PLATFORM_EMAIL], ['name' => 'Erudsway', 'password' => Str::random(64)]);
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        if ($user->profile()->doesntExist()) {
            $user->profile()->create();
        }
        $user->assignRole(Role::Instructor->value);
        InstructorProfile::query()->firstOrCreate(['user_id' => $user->id], [
            'slug' => 'erudsway',
            'display_name' => 'Erudsway',
            'headline' => 'Practice tools from the Erudsway team',
            'approved_at' => now(),
        ]);

        return $user;
    }
}
