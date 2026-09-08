<?php

declare(strict_types=1);

namespace App\Modules\SLA\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class BusinessTimeCalculator
{
    public function addSeconds(CarbonImmutable $start, int $seconds, array $calendar): CarbonImmutable
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Duration must not be negative.');
        }
        if ($seconds === 0) {
            return $start;
        }
        if ($calendar['is_24x7'] ?? false) {
            return $start->utc()->addSeconds($seconds);
        }
        $cursor = $start->setTimezone($calendar['timezone']);
        for ($day = 0; $day < 3660; $day++) {
            foreach ($this->intervals($cursor, $calendar) as [$from, $to]) {
                $from = $from->max($cursor);
                $available = max(0, $to->getTimestamp() - $from->getTimestamp());
                if ($seconds <= $available) {
                    return $from->utc()->addSeconds($seconds);
                }
                $seconds -= $available;
            }
            $cursor = $cursor->addDay()->startOfDay();
        }
        throw new InvalidArgumentException('Calendar has insufficient working hours within ten years.');
    }

    public function secondsBetween(CarbonImmutable $from, CarbonImmutable $to, array $calendar): int
    {
        if ($to < $from) {
            return -$this->secondsBetween($to, $from, $calendar);
        }
        if ($calendar['is_24x7'] ?? false) {
            return $to->getTimestamp() - $from->getTimestamp();
        }
        $cursor = $from->setTimezone($calendar['timezone'])->startOfDay();
        $end = $to->setTimezone($calendar['timezone']);
        $seconds = 0;
        for ($day = 0; $cursor <= $end && $day < 3660; $day++, $cursor = $cursor->addDay()) {
            foreach ($this->intervals($cursor, $calendar) as [$a, $b]) {
                $seconds += max(0, $b->min($to)->getTimestamp() - $a->max($from)->getTimestamp());
            }
        }
        return $seconds;
    }

    private function intervals(CarbonImmutable $day, array $calendar): array
    {
        $holiday = collect($calendar['holidays'] ?? [])->firstWhere('holiday_date', $day->toDateString());
        if ($holiday) {
            $hours = ($holiday['is_working_override'] ?? false) ? [$holiday] : [];
        } else {
            $hours = array_filter($calendar['business_hours'] ?? [], fn ($h) => (int) $h['weekday'] === $day->dayOfWeek && ($h['is_working'] ?? true));
        }
        $intervals = [];
        foreach ($hours as $hour) {
            if (empty($hour['start_time']) || empty($hour['end_time'])) {
                continue;
            }
            $a = $day->setTimeFromTimeString($hour['start_time']);
            $b = $day->setTimeFromTimeString($hour['end_time']);
            if ($b <= $a) {
                throw new InvalidArgumentException('Split overnight shifts into separate calendar days.');
            }
            $intervals[] = [$a, $b];
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        // Merge overlaps so the same business second is never counted twice.
        $merged = [];
        foreach ($intervals as $interval) {
            $last = count($merged) - 1;
            if ($last >= 0 && $interval[0] <= $merged[$last][1]) {
                $merged[$last][1] = $merged[$last][1]->max($interval[1]);
            } else {
                $merged[] = $interval;
            }
        }
        return $merged;
    }
}
