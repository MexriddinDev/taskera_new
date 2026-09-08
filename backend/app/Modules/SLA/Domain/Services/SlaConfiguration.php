<?php

declare(strict_types=1);

namespace App\Modules\SLA\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SlaConfiguration
{
    public const METRICS = ['ASSIGNMENT', 'ACCEPTANCE', 'FIRST_RESPONSE', 'WORK_START', 'RESOLUTION', 'CLOSURE', 'UPDATE'];

    public function validate(array $input, int $org, bool $publish): array
    {
        $owned = fn ($table) => Rule::exists($table, 'id')->where('organization_id', $org)->whereNull('deleted_at');
        $data = Validator::make($input, [
            'code' => 'required|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:5000',
            'effective_from' => 'required|date', 'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'calendar_id' => [$publish ? 'required' : 'nullable', 'integer', $owned('business_calendars')],
            'policy_priority' => 'required|integer|between:0,10000',
            'scope' => 'present|array:category_id,subcategory_id,priority_id,service_id,department_id,source_id,requester_type',
            'scope.category_id' => ['nullable', 'integer', $owned('categories')],
            'scope.subcategory_id' => ['nullable', 'integer', $owned('categories')],
            'scope.service_id' => ['nullable', 'integer', $owned('services')],
            'scope.department_id' => ['nullable', 'integer', $owned('departments')],
            'scope.priority_id' => 'nullable|integer|exists:ticket_priorities,id',
            'scope.source_id' => 'nullable|integer|exists:ticket_sources,id',
            'scope.requester_type' => 'nullable|string|in:employee,manager,vip,external',
            'targets' => [$publish ? 'required' : 'present', 'array', 'max:7'],
            'targets.*' => 'array:metric,minutes,start,calendar_mode',
            'targets.*.metric' => ['required', Rule::in(self::METRICS), 'distinct'],
            'targets.*.minutes' => 'required|integer|between:1,525600',
            'targets.*.start' => 'required|in:CREATED,ASSIGNED,ACCEPTED,RESOLVED',
            'targets.*.calendar_mode' => 'required|in:BUSINESS,24X7',
            'pause_statuses' => 'present|array|max:2',
            'pause_statuses.*' => 'in:WAITING_USER,WAITING_VENDOR|distinct',
            'extension' => 'required|array:enabled,max_minutes,approver_ids',
            'extension.enabled' => 'required|boolean', 'extension.max_minutes' => 'required|integer|between:0,525600',
            'extension.approver_ids' => 'present|array|max:100',
            'extension.approver_ids.*' => ['integer', 'distinct', $owned('users')],
            'escalations' => 'present|array|max:12',
            'escalations.*' => 'array:threshold,assignee,user_ids,channels',
            'escalations.*.threshold' => 'required|integer|between:1,500|distinct',
            'escalations.*.assignee' => 'required|boolean',
            'escalations.*.user_ids' => 'present|array|max:100',
            'escalations.*.user_ids.*' => ['integer', $owned('users')],
            'escalations.*.channels' => 'required|array|min:1|max:3',
            'escalations.*.channels.*' => 'in:IN_APP,EMAIL,TELEGRAM',
        ])->validate();
        $data['code'] = strtoupper($data['code']);
        $data['scope'] = array_replace(['category_id' => null, 'subcategory_id' => null, 'priority_id' => null,
            'service_id' => null, 'department_id' => null, 'source_id' => null, 'requester_type' => null], $data['scope']);
        if (!empty($data['scope']['subcategory_id']) && (!isset($data['scope']['category_id']) ||
            (int) DB::table('categories')->where('id', $data['scope']['subcategory_id'])->value('parent_id') !== (int) $data['scope']['category_id'])) {
            throw ValidationException::withMessages(['scope.subcategory_id' => 'Subkategoriya tanlangan kategoriyaga tegishli emas.']);
        }
        $starts = ['ASSIGNMENT' => ['CREATED'], 'ACCEPTANCE' => ['ASSIGNED'], 'FIRST_RESPONSE' => ['CREATED'],
            'WORK_START' => ['ACCEPTED'], 'RESOLUTION' => ['CREATED', 'ACCEPTED'], 'CLOSURE' => ['RESOLVED'], 'UPDATE' => ['CREATED', 'ACCEPTED']];
        foreach ($data['targets'] as $index => $target) {
            if (!in_array($target['start'], $starts[$target['metric']])) {
                throw ValidationException::withMessages(["targets.$index.start" => 'Ushbu taymer uchun boshlanish hodisasi mos emas.']);
            }
        }
        foreach ($data['escalations'] as $index => $rule) {
            if (!$rule['assignee'] && !$rule['user_ids']) {
                throw ValidationException::withMessages(["escalations.$index.user_ids" => 'Kamida bitta xabar oluvchini tanlang.']);
            }
            if (count(array_unique($rule['user_ids'])) !== count($rule['user_ids'])) {
                throw ValidationException::withMessages(["escalations.$index.user_ids" => 'Xabar oluvchi ikki marta tanlangan.']);
            }
            if (count(array_unique($rule['channels'])) !== count($rule['channels'])) {
                throw ValidationException::withMessages(["escalations.$index.channels" => 'Kanal ikki marta tanlangan.']);
            }
        }
        if ($publish && $data['extension']['enabled'] && (!$data['extension']['max_minutes'] || !$data['extension']['approver_ids'])) {
            throw ValidationException::withMessages(['extension' => 'Maksimal vaqt va tasdiqlovchilar majburiy.']);
        }
        usort($data['escalations'], fn ($a, $b) => $a['threshold'] <=> $b['threshold']);
        if ($publish) {
            $data['calendar'] = $this->calendar($data['calendar_id'], $org);
            // Validate that at least one future deadline can actually be calculated.
            app(BusinessTimeCalculator::class)->addSeconds(\Carbon\CarbonImmutable::now(), 60, $data['calendar']);
        }
        return $data;
    }

    public function calendar(int $id, int $org): array
    {
        $calendar = DB::table('business_calendars')->where('organization_id', $org)->whereNull('deleted_at')->where('is_active', true)->find($id);
        if (!$calendar) {
            throw ValidationException::withMessages(['calendar_id' => 'Faol ish kalendarini tanlang.']);
        }
        return ['id' => $calendar->id, 'name' => $calendar->name, 'is_24x7' => (bool) $calendar->is_24x7,
            'timezone' => DB::table('timezones')->where('id', $calendar->timezone_id)->value('name'),
            'business_hours' => DB::table('business_hours')->where('calendar_id', $id)->get()->map(fn ($h) => (array) $h)->all(),
            'holidays' => DB::table('calendar_holidays')->where('calendar_id', $id)->get()->map(fn ($h) => (array) $h)->all()];
    }
}
