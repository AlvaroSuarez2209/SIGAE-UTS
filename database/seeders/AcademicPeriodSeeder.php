<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use Database\Seeders\Concerns\RefusesInProduction;
use Illuminate\Database\Seeder;

class AcademicPeriodSeeder extends Seeder
{
    use RefusesInProduction;

    public function run(): void
    {
        if ($this->abortIfProduction()) {
            return;
        }

        AcademicPeriod::firstOrCreate(
            ['name' => '2025-2'],
            [
                'status' => AcademicPeriodStatus::Archived,
                'start_date' => '2025-07-15',
                'end_date' => '2025-12-05',
            ]
        );

        AcademicPeriod::firstOrCreate(
            ['name' => '2026-1'],
            [
                'status' => AcademicPeriodStatus::Active,
                'start_date' => '2026-01-20',
                'end_date' => '2026-06-15',
            ]
        );
    }
}
