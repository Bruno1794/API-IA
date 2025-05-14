<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        /*if (!Settings::where('time_cobranca', '=','08:30')->exists()) {
            Settings::create([
                'time_cobranca' => "08:30",
                'user_id' => "1",

            ]);
        }*/

        if (!Settings::where('time_cobranca', '=','08:35')->exists()) {
            Settings::create([
                'time_cobranca' => "08:35",
                'user_id' => "2",

            ]);
        }
    }
}
