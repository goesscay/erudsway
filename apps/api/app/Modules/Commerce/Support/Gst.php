<?php

namespace App\Modules\Commerce\Support;

use Illuminate\Support\Carbon;

/**
 * GST arithmetic and reference data. Listed prices include GST, so tax is taken out of the
 * amount paid; place of supply (buyer's state vs ours) decides CGST+SGST or IGST.
 */
final class Gst
{
    /** GST state codes (as used in GSTINs and place of supply). */
    public const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
        '97' => 'Other Territory',
    ];

    /**
     * Split a GST-inclusive amount into [taxable value, tax], in paise.
     *
     * @return array{0: int, 1: int}
     */
    public static function split(int $inclusiveAmount): array
    {
        $rate = (int) config('commerce.gst.rate_bps');
        $taxable = (int) round($inclusiveAmount * 10000 / (10000 + $rate));

        return [$taxable, $inclusiveAmount - $taxable];
    }

    /**
     * CGST/SGST for a supply within the seller's state, IGST otherwise.
     *
     * @return array{cgst: int, sgst: int, igst: int}
     */
    public static function components(int $tax, string $buyerStateCode): array
    {
        if ($buyerStateCode === (string) config('commerce.seller.state_code')) {
            $cgst = intdiv($tax, 2);

            return ['cgst' => $cgst, 'sgst' => $tax - $cgst, 'igst' => 0];
        }

        return ['cgst' => 0, 'sgst' => 0, 'igst' => $tax];
    }

    /** Format, state code and check digit of a GSTIN. */
    public static function validGstin(string $gstin, ?string $stateCode = null): bool
    {
        if (preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin) !== 1) {
            return false;
        }
        if ($stateCode !== null && substr($gstin, 0, 2) !== $stateCode) {
            return false;
        }

        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $product = strpos($chars, $gstin[$i]) * ($i % 2 === 0 ? 1 : 2);
            $sum += intdiv($product, 36) + $product % 36;
        }

        return $chars[(36 - $sum % 36) % 36] === $gstin[14];
    }

    /** Indian financial year for a date, e.g. "2026-27". */
    public static function financialYear(\DateTimeInterface $date): string
    {
        $local = Carbon::instance($date)->setTimezone('Asia/Kolkata');
        $start = $local->month >= 4 ? $local->year : $local->year - 1;

        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }
}
