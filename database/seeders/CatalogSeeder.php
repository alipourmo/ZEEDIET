<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ServiceKind;
use App\Models\Device;
use App\Models\Room;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Example catalog (spec section 15): three services, two devices with contraindications, two rooms.
 *
 * Idempotent: each item is found by its name, so running the seeder again changes nothing. The durations, device
 * contraindications and room names are examples. The clinic confirms them, and prices stay empty until it sets them.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['key' => 'nutrition_consult', 'kind' => ServiceKind::Consultation, 'duration' => 30, 'device' => false, 'specialist' => true],
            ['key' => 'body_analysis', 'kind' => ServiceKind::BodyAnalysis, 'duration' => 20, 'device' => false, 'specialist' => false],
            ['key' => 'device_slimming', 'kind' => ServiceKind::DeviceSession, 'duration' => 45, 'device' => true, 'specialist' => false],
        ];

        foreach ($services as $service) {
            Service::query()->firstOrCreate(
                ['name' => __('seeders.catalog.services.'.$service['key'])],
                [
                    'kind' => $service['kind'],
                    'default_duration_min' => $service['duration'],
                    'requires_device' => $service['device'],
                    'requires_specialist' => $service['specialist'],
                    'default_price' => null,
                    'is_active' => true,
                ],
            );
        }

        $devices = [
            ['key' => 'slimming_1', 'contraindications' => ['pacemaker', 'metal_implant']],
            ['key' => 'slimming_2', 'contraindications' => ['pregnancy', 'epilepsy', 'cancer_active']],
        ];

        foreach ($devices as $device) {
            Device::query()->firstOrCreate(
                ['name' => __('seeders.catalog.devices.'.$device['key'])],
                [
                    'model' => null,
                    'contraindications' => $device['contraindications'],
                    'is_active' => true,
                    'notes' => null,
                ],
            );
        }

        foreach (['consult', 'device'] as $key) {
            Room::query()->firstOrCreate(
                ['name' => __('seeders.catalog.rooms.'.$key)],
                ['is_active' => true],
            );
        }
    }
}
