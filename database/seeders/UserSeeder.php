<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        if (!User::where('username', '=','bruno1020')->exists()) {
           $user =  User::create([
                'name' => "Bruno",
                'username' => "bruno1020",
                'phone' => "44991665359",
                'validate' => "2025-05-20",
                'password' => Hash::make('051161', ['rounds' => 12]),
            ]);

            $user->settings()->create([
                'time_cobranca' => "08:30",
            ]);
        }

        if (!User::where('username', '=','loja1020')->exists()) {
          $user =   User::create([
                'name' => "Loja",
                'username' => "loja1020",
                'phone' => "554498212815",
                'validate' => "2025-05-20",
                'password' => Hash::make('051161', ['rounds' => 12]),
            ]);
            $user->settings()->create([
                'time_cobranca' => "10:30",
            ]);
        }
    }
}
