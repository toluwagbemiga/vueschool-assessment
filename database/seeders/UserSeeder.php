<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $timezones = ['CET', 'CST', 'GMT+1'];
        for ($i = 0; $i < 20; $i++) {
            User::create([
                'name' => 'First' . $i,
                'email' => 'user' . $i . '@example.com',
                'password' => Hash::make('password'), // Default password for all users
                'timezone' => $timezones[array_rand($timezones)],
                'email_verified_at' => Carbon::now(), // Set the email as verified
            ]);
        }
    }
}
