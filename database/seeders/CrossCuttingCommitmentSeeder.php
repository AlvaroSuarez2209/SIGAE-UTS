<?php

namespace Database\Seeders;

use App\Models\CrossCuttingCommitment;
use Illuminate\Database\Seeder;

class CrossCuttingCommitmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Capacitación institucional', 'Bienestar y desarrollo humano'] as $name) {
            CrossCuttingCommitment::firstOrCreate(['name' => $name]);
        }
    }
}
