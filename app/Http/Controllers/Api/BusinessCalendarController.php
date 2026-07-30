<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessCalendarResource;
use App\Modules\SLA\Infrastructure\Eloquent\BusinessCalendar;
use App\Modules\SLA\Infrastructure\Eloquent\BusinessHour;
use App\Modules\SLA\Infrastructure\Eloquent\CalendarHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessCalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $calendars = BusinessCalendar::query()
            ->with(['businessHours', 'holidays'])
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => BusinessCalendarResource::collection($calendars),
            'meta' => [
                'current_page' => $calendars->currentPage(),
                'last_page' => $calendars->lastPage(),
                'per_page' => $calendars->perPage(),
                'total' => $calendars->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone_id' => 'required|integer|exists:timezones,id',
            'is_24x7' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'business_hours' => 'nullable|array',
            'business_hours.*.weekday' => 'required_with:business_hours|integer|between:0,6',
            'business_hours.*.start_time' => 'required_with:business_hours|date_format:H:i',
            'business_hours.*.end_time' => 'required_with:business_hours|date_format:H:i',
            'business_hours.*.is_working' => 'nullable|boolean',
            'holidays' => 'nullable|array',
            'holidays.*.holiday_date' => 'required_with:holidays|date',
            'holidays.*.name' => 'required_with:holidays|string|max:255',
            'holidays.*.is_working_override' => 'nullable|boolean',
            'holidays.*.start_time' => 'nullable|date_format:H:i',
            'holidays.*.end_time' => 'nullable|date_format:H:i',
        ]);

        $calendar = BusinessCalendar::create([
            'public_id' => (string) Str::uuid(),
            'organization_id' => $request->header('X-Organization-Id', 1),
            'name' => $validated['name'],
            'timezone_id' => $validated['timezone_id'],
            'is_24x7' => $validated['is_24x7'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['business_hours'])) {
            foreach ($validated['business_hours'] as $hour) {
                $calendar->businessHours()->create([
                    'weekday' => $hour['weekday'],
                    'start_time' => $hour['start_time'],
                    'end_time' => $hour['end_time'],
                    'is_working' => $hour['is_working'] ?? true,
                ]);
            }
        }

        if (!empty($validated['holidays'])) {
            foreach ($validated['holidays'] as $holiday) {
                $calendar->holidays()->create([
                    'holiday_date' => $holiday['holiday_date'],
                    'name' => $holiday['name'],
                    'is_working_override' => $holiday['is_working_override'] ?? false,
                    'start_time' => $holiday['start_time'] ?? null,
                    'end_time' => $holiday['end_time'] ?? null,
                ]);
            }
        }

        $calendar->load(['businessHours', 'holidays']);

        return response()->json([
            'data' => new BusinessCalendarResource($calendar),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $calendar = BusinessCalendar::with(['businessHours', 'holidays'])->findOrFail($id);

        return response()->json([
            'data' => new BusinessCalendarResource($calendar),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $calendar = BusinessCalendar::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'timezone_id' => 'sometimes|required|integer|exists:timezones,id',
            'is_24x7' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'business_hours' => 'nullable|array',
            'business_hours.*.id' => 'nullable|integer|exists:business_hours,id',
            'business_hours.*.weekday' => 'required_with:business_hours|integer|between:0,6',
            'business_hours.*.start_time' => 'required_with:business_hours|date_format:H:i',
            'business_hours.*.end_time' => 'required_with:business_hours|date_format:H:i',
            'business_hours.*.is_working' => 'nullable|boolean',
            'holidays' => 'nullable|array',
            'holidays.*.id' => 'nullable|integer|exists:calendar_holidays,id',
            'holidays.*.holiday_date' => 'required_with:holidays|date',
            'holidays.*.name' => 'required_with:holidays|string|max:255',
            'holidays.*.is_working_override' => 'nullable|boolean',
            'holidays.*.start_time' => 'nullable|date_format:H:i',
            'holidays.*.end_time' => 'nullable|date_format:H:i',
        ]);

        $calendar->update($validated);

        if ($request->has('business_hours')) {
            $submittedIds = collect($validated['business_hours'])->pluck('id')->filter();
            $calendar->businessHours()->whereNotIn('id', $submittedIds)->delete();

            foreach ($validated['business_hours'] as $hour) {
                if (!empty($hour['id'])) {
                    BusinessHour::where('calendar_id', $calendar->id)
                        ->where('id', $hour['id'])
                        ->update([
                            'weekday' => $hour['weekday'],
                            'start_time' => $hour['start_time'],
                            'end_time' => $hour['end_time'],
                            'is_working' => $hour['is_working'] ?? true,
                        ]);
                } else {
                    $calendar->businessHours()->create([
                        'weekday' => $hour['weekday'],
                        'start_time' => $hour['start_time'],
                        'end_time' => $hour['end_time'],
                        'is_working' => $hour['is_working'] ?? true,
                    ]);
                }
            }
        }

        if ($request->has('holidays')) {
            $submittedIds = collect($validated['holidays'])->pluck('id')->filter();
            $calendar->holidays()->whereNotIn('id', $submittedIds)->delete();

            foreach ($validated['holidays'] as $holiday) {
                if (!empty($holiday['id'])) {
                    CalendarHoliday::where('calendar_id', $calendar->id)
                        ->where('id', $holiday['id'])
                        ->update([
                            'holiday_date' => $holiday['holiday_date'],
                            'name' => $holiday['name'],
                            'is_working_override' => $holiday['is_working_override'] ?? false,
                            'start_time' => $holiday['start_time'] ?? null,
                            'end_time' => $holiday['end_time'] ?? null,
                        ]);
                } else {
                    $calendar->holidays()->create([
                        'holiday_date' => $holiday['holiday_date'],
                        'name' => $holiday['name'],
                        'is_working_override' => $holiday['is_working_override'] ?? false,
                        'start_time' => $holiday['start_time'] ?? null,
                        'end_time' => $holiday['end_time'] ?? null,
                    ]);
                }
            }
        }

        $calendar->load(['businessHours', 'holidays']);

        return response()->json([
            'data' => new BusinessCalendarResource($calendar),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $calendar = BusinessCalendar::findOrFail($id);
        $calendar->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
