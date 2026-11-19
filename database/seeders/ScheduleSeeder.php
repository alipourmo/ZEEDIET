<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Weekday;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Default working hours (Saturday to Thursday, from config) for every active specialist, device and room,
 * and the holidays listed in config/clinic.php. Idempotent: a resource that already has hours is left alone.
 */
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $hours = config('clinic.working_hours');

        $owners = [
            [ScheduleResource::User, User::query()->where('role', StaffRole::Specialist->value)->where('is_active', true)->get()],
            [ScheduleResource::Device, Device::query()->where('is_active', true)->get()],
            [ScheduleResource::Room, Room::query()->where('is_active', true)->get()],
        ];

        foreach ($owners as [$type, $models]) {
            foreach ($models as $model) {
                $this->seedHours($type, $model, (string) $hours['start'], (string) $hours['end']);
            }
        }

        foreach ((array) config('clinic.holidays', []) as $holiday) {
            Holiday::query()->firstOrCreate(
                ['date' => $holiday['date']],
                ['title' => $holiday['title']],
            );
        }
    }

    private function seedHours(ScheduleResource $type, Model $owner, string $start, string $end): void
    {
        $exists = Schedule::query()
            ->where('resource_type', $type->value)
            ->where('resource_id', $owner->getKey())
            ->exists();

        if ($exists) {
            return;
        }

        // Saturday (0) to Thursday (5). Friday is the weekend by config.
        for ($weekday = 0; $weekday < Weekday::COUNT - 1; $weekday++) {
            Schedule::query()->create([
                'resource_type' => $type->value,
                'resource_id' => $owner->getKey(),
                'weekday' => $weekday,
                'start_time' => $start.':00',
                'end_time' => $end.':00',
            ]);
        }
    }
}
