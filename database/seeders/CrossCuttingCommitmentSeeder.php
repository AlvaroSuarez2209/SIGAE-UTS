<?php

namespace Database\Seeders;

use App\Models\CrossCuttingCommitment;
use Database\Seeders\Concerns\RefusesInProduction;
use Illuminate\Database\Seeder;

class CrossCuttingCommitmentSeeder extends Seeder
{
    use RefusesInProduction;

    public function run(): void
    {
        if ($this->abortIfProduction()) {
            return;
        }

        foreach (['Capacitación institucional', 'Bienestar y desarrollo humano'] as $name) {
            CrossCuttingCommitment::firstOrCreate(['name' => $name]);
        }
    }
}
