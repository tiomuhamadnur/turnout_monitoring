<?php

namespace Database\Seeders;

use App\Models\Line;
use Illuminate\Database\Seeder;

class LineSeeder extends Seeder
{
    private const LINES = [
        ['code' => 'UT', 'name' => 'Up Track'],
        ['code' => 'DT', 'name' => 'Down Track'],
        ['code' => 'MT', 'name' => 'Middle Track'],
    ];

    public function run(): void
    {
        foreach (self::LINES as $line) {
            Line::updateOrCreate(
                ['code' => $line['code']],
                $line,
            );
        }
    }
}
