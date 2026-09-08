<?php

namespace Tests\Unit;

use App\Modules\SLA\Domain\Services\BusinessTimeCalculator;
use App\Modules\SLA\Domain\Services\PolicyMatcher;
use Carbon\CarbonImmutable as Time;
use PHPUnit\Framework\TestCase;

class SlaBusinessTimeTest extends TestCase
{
    private function office(): array
    {
        return ['timezone' => 'Asia/Tashkent', 'is_24x7' => false,
            'business_hours' => array_map(fn ($day) => ['weekday' => $day, 'start_time' => '09:00', 'end_time' => '18:00'], [1, 2, 3, 4, 5]), 'holidays' => []];
    }

    public function test_friday_deadline_skips_weekend_and_holiday(): void
    {
        $clock = new BusinessTimeCalculator;
        $calendar = $this->office();
        $start = Time::parse('2026-09-04 17:30', 'Asia/Tashkent');
        $this->assertSame('2026-09-07 12:30', $clock->addSeconds($start, 14400, $calendar)->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
        $calendar['holidays'][] = ['holiday_date' => '2026-09-07', 'is_working_override' => false];
        $end = $clock->addSeconds($start, 14400, $calendar);
        $this->assertSame('2026-09-08 12:30', $end->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
        $this->assertSame(14400, $clock->secondsBetween($start, $end, $calendar));
    }

    public function test_overlapping_shifts_are_counted_once_and_override_opens_weekend(): void
    {
        $calendar = $this->office();
        $calendar['business_hours'][] = ['weekday' => 1, 'start_time' => '10:00', 'end_time' => '12:00'];
        $clock = new BusinessTimeCalculator;
        $this->assertSame(9 * 3600, $clock->secondsBetween(Time::parse('2026-09-07 09:00', 'Asia/Tashkent'), Time::parse('2026-09-07 18:00', 'Asia/Tashkent'), $calendar));
        $calendar['holidays'][] = ['holiday_date' => '2026-09-05', 'is_working_override' => true, 'start_time' => '10:00', 'end_time' => '12:00'];
        $this->assertSame('2026-09-05 11:00', $clock->addSeconds(Time::parse('2026-09-04 18:00', 'Asia/Tashkent'), 3600, $calendar)->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_dst_uses_real_elapsed_seconds(): void
    {
        $calendar = ['is_24x7' => false, 'timezone' => 'America/New_York', 'business_hours' => [['weekday' => 0, 'start_time' => '01:00', 'end_time' => '04:00']]];
        $start = Time::parse('2026-03-08 01:00', 'America/New_York');
        $clock = new BusinessTimeCalculator;
        $end = $clock->addSeconds($start, 7200, $calendar);
        $this->assertSame('04:00', $end->setTimezone('America/New_York')->format('H:i'));
        $this->assertSame(7200, $clock->secondsBetween($start, $end, $calendar));
    }

    public function test_specificity_priority_dates_and_default_matching(): void
    {
        $make = fn ($id, $scope, $priority = 0) => (object) ['sla_policy_id' => $id, 'config' => ['effective_from' => '2026-01-01', 'scope' => $scope, 'policy_priority' => $priority]];
        $matcher = new PolicyMatcher;
        $versions = [$make(1, []), $make(2, ['category_id' => 10], 999), $make(3, ['category_id' => 10, 'priority_id' => 1]), $make(4, ['category_id' => 10, 'priority_id' => 1], 20)];
        $this->assertSame(4, $matcher->match($versions, ['category_id' => 10, 'priority_id' => 1], '2026-09-08')->sla_policy_id);
        $this->assertSame(1, $matcher->match($versions, ['category_id' => 99], '2026-09-08')->sla_policy_id);
        $this->assertNull($matcher->match($versions, [], '2025-01-01'));
    }
}
