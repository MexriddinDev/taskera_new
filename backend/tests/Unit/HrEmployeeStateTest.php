<?php

namespace Tests\Unit;

use App\Services\EmployeeCheckService;
use App\Support\HrEmployeeState;
use PHPUnit\Framework\TestCase;

class HrEmployeeStateTest extends TestCase
{
    public function test_registry_contains_all_states(): void
    {
        $all = HrEmployeeState::all();
        $this->assertCount(28, $all);
        $this->assertArrayHasKey('A', $all);
        $this->assertArrayHasKey('P', $all);
        $this->assertArrayHasKey('SA', $all);
        $this->assertArrayHasKey('DB', $all);
    }

    public function test_active_working_state_is_identified(): void
    {
        $this->assertTrue(HrEmployeeState::isWorking('A'));
        $this->assertTrue(HrEmployeeState::canOpenAd('A'));
        $this->assertSame('Ishlayotgan xodimlar', HrEmployeeState::nameUz('A'));
        $this->assertSame('Рабочие', HrEmployeeState::nameRu('A'));
    }

    public function test_dismissed_state_is_not_working(): void
    {
        $this->assertFalse(HrEmployeeState::isWorking('P'));
        $this->assertFalse(HrEmployeeState::canOpenAd('P'));
        $this->assertSame("Ishdan bo'shatilganlar", HrEmployeeState::nameUz('P'));
        $this->assertSame('Уволенные', HrEmployeeState::nameRu('P'));
    }

    public function test_doc_states(): void
    {
        $docCodes = HrEmployeeState::docStateCodes();
        $this->assertEqualsCanonicalizing(['SA', 'SV', 'SU', 'SO'], $docCodes);
    }

    public function test_employee_check_service_condition_helpers(): void
    {
        $service = new EmployeeCheckService();
        $this->assertSame('Ishlayotgan xodimlar', $service->conditionLabel('A', 'uz'));
        $this->assertSame('Рабочие', $service->conditionLabel('A', 'ru'));
        $this->assertTrue($service->isConditionWorking('A'));
        $this->assertFalse($service->isConditionWorking('P'));
    }
}
