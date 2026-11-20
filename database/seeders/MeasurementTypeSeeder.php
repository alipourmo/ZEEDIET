<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MeasurementType;
use Illuminate\Database\Seeder;

/**
 * The measurement types of spec section 6.4. Idempotent: an existing key keeps its label, order and active flag, so an
 * admin's changes survive a re-seed.
 */
class MeasurementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['key' => 'weight', 'unit' => 'kg'],
            ['key' => 'waist', 'unit' => 'cm'],
            ['key' => 'abdomen', 'unit' => 'cm'],
            ['key' => 'upper_arm', 'unit' => 'cm'],
            ['key' => 'thigh', 'unit' => 'cm'],
            ['key' => 'hip', 'unit' => 'cm'],
            ['key' => 'chest', 'unit' => 'cm'],
            ['key' => 'neck', 'unit' => 'cm'],
            ['key' => 'body_fat_percent', 'unit' => '%'],
            ['key' => 'muscle_mass', 'unit' => 'kg'],
        ];

        foreach ($types as $index => $type) {
            MeasurementType::query()->firstOrCreate(
                ['key' => $type['key']],
                [
                    'label_key' => 'measurements.types.'.$type['key'],
                    'unit' => $type['unit'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }
}
