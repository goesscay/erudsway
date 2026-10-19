<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data every environment needs, production included: the ten course areas with their
 * subcategories, and the price tiers instructors pick from. Safe to run again — it only
 * adds and updates, never deletes:
 *
 *   php artisan db:seed --class=ReferenceDataSeeder --force
 */
class ReferenceDataSeeder extends Seeder
{
    /** Top-level areas (as planned by Erudsway) => [description, subcategories]. */
    public const CATEGORIES = [
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

    /** List prices in paise, GST included. */
    public const PRICE_TIERS = [39900, 44900, 49900, 59900, 79900, 99900, 129900, 199900, 349900];

    public function run(): void
    {
        DB::transaction(function () {
            $position = 0;
            foreach (self::CATEGORIES as $parentName => [$description, $children]) {
                $parent = Category::query()->updateOrCreate(
                    ['slug' => str($parentName)->slug()->toString()],
                    ['name' => $parentName, 'description' => $description, 'parent_id' => null, 'position' => $position++, 'is_active' => true],
                );
                foreach ($children as $j => $name) {
                    Category::query()->updateOrCreate(
                        ['slug' => str($name)->slug()->toString()],
                        ['name' => $name, 'parent_id' => $parent->id, 'position' => $j, 'is_active' => true],
                    );
                }
            }

            foreach (self::PRICE_TIERS as $amount) {
                PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => $amount]);
            }
        });
    }
}
