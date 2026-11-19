<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Device;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of weekly working hours (spec sections 6.2 and 8.2).
 */
final class ScheduleService
{
    /**
     * The resource, found by its type and id. Specialists only, for users.
     */
    public function owner(ScheduleResource $type, int $id): Model
    {
        return match ($type) {
            ScheduleResource::User => User::query()
                ->where('role', StaffRole::Specialist->value)
                ->whereKey($id)
                ->firstOrFail(),
            ScheduleResource::Device => Device::query()->whereKey($id)->firstOrFail(),
            ScheduleResource::Room => Room::query()->whereKey($id)->firstOrFail(),
        };
    }

    /**
     * The saved hours of one resource, keyed by weekday.
     *
     * @return array<int, array{enabled: bool, start: string, end: string}>
     */
    public function daysFor(ScheduleResource $type, int $id): array
    {
        $days = [];

        foreach (Schedule::query()->where('resource_type', $type->value)->where('resource_id', $id)->get() as $row) {
            $days[$row->weekday] = [
                'enabled' => true,
                'start' => substr((string) $row->start_time, 0, 5),
                'end' => substr((string) $row->end_time, 0, 5),
            ];
        }

        return $days;
    }

    /**
     * Replaces all hours of a resource in one transaction. Hard delete is allowed here: schedules are not
     * medical or financial records.
     *
     * @param  list<array{weekday: int, start_time: string, end_time: string}>  $rows
     */
    public function replace(ScheduleResource $type, int $id, array $rows): void
    {
        DB::transaction(function () use ($type, $id, $rows): void {
            Schedule::query()
                ->where('resource_type', $type->value)
                ->where('resource_id', $id)
                ->delete();

            foreach ($rows as $row) {
                Schedule::query()->create([
                    'resource_type' => $type->value,
                    'resource_id' => $id,
                    'weekday' => $row['weekday'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                ]);
            }
        });
    }

    /**
     * Everything that can have hours, with the number of weekdays already set. Two queries in total.
     *
     * @return list<array{type: ScheduleResource, label: string, items: list<array{id: int, name: string, days: int}>}>
     */
    public function groups(): array
    {
        $counts = Schedule::query()
            ->selectRaw('resource_type, resource_id, COUNT(*) AS days')
            ->groupBy('resource_type', 'resource_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->resource_type->value.':'.$row->resource_id => (int) $row->days]);

        $specialists = User::query()->where('role', StaffRole::Specialist->value)->orderBy('name')->get();
        $devices = Device::query()->orderBy('name')->get();
        $rooms = Room::query()->orderBy('name')->get();

        $build = fn (ScheduleResource $type, iterable $models): array => [
            'type' => $type,
            'label' => __('scheduling.resource_types.'.$type->value),
            'items' => collect($models)->map(fn (Model $model): array => [
                'id' => (int) $model->getKey(),
                'name' => (string) ($model->getAttribute('name')),
                'days' => $counts[$type->value.':'.$model->getKey()] ?? 0,
            ])->values()->all(),
        ];

        return [
            $build(ScheduleResource::User, $specialists),
            $build(ScheduleResource::Device, $devices),
            $build(ScheduleResource::Room, $rooms),
        ];
    }
}
