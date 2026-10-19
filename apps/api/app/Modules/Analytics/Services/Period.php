<?php

namespace App\Modules\Analytics\Services;

use Illuminate\Support\Carbon;

/**
 * A reporting period in whole Indian calendar days (reports are read in IST), with the
 * equally long period just before it for comparisons.
 */
final class Period
{
    public const TZ = 'Asia/Kolkata';

    private function __construct(public readonly Carbon $from, public readonly Carbon $to) {}

    /** From/to as Y-m-d dates in IST; defaults to the last 30 days including today. */
    public static function fromDates(?string $from, ?string $to): self
    {
        $end = ($to ? Carbon::parse($to, self::TZ) : Carbon::now(self::TZ))->endOfDay();
        $start = ($from ? Carbon::parse($from, self::TZ) : $end->copy()->subDays(29))->startOfDay();

        return new self($start->utc(), $end->utc());
    }

    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->copy()->subDays($days), $this->from->copy()->subSecond());
    }

    public function days(): int
    {
        return (int) round($this->from->diffInDays($this->to, true));
    }

    /** @return list<string> every IST date in the period, Y-m-d */
    public function dates(): array
    {
        $dates = [];
        $day = $this->from->copy()->timezone(self::TZ)->startOfDay();
        $last = $this->to->copy()->timezone(self::TZ)->startOfDay();
        while ($day->lte($last)) {
            $dates[] = $day->toDateString();
            $day->addDay();
        }

        return $dates;
    }

    /** The IST calendar date of a moment (a Carbon, or a raw UTC timestamp from the database). */
    public static function istDate(\DateTimeInterface|string $at): string
    {
        $moment = $at instanceof \DateTimeInterface ? Carbon::instance($at) : Carbon::parse($at, 'UTC');

        return $moment->timezone(self::TZ)->toDateString();
    }

    /** @return array{from: string, to: string} */
    public function toArray(): array
    {
        return [
            'from' => $this->from->copy()->timezone(self::TZ)->toDateString(),
            'to' => $this->to->copy()->timezone(self::TZ)->toDateString(),
        ];
    }
}
