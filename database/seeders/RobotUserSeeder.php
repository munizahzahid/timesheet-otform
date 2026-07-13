<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RobotUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['staff_no' => 'ROBOT'],
            [
                'staff_no' => 'ROBOT',
                'name' => 'ROBOT',
                'password' => Hash::make('robot'),
                'is_active' => true,
            ]
        );
    }
}
