<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = array_merge(
            $this->getHolidays2025(),
            $this->getHolidays2026()
        );

        foreach ($holidays as $holiday) {
            $existing = DB::table('public_holidays')
                ->where('holiday_date', $holiday['holiday_date'])
                ->where('source', 'gazetted')
                ->first();

            // Only insert if the gazetted holiday doesn't exist at this date
            // This preserves admin changes to holiday dates
            if (!$existing) {
                DB::table('public_holidays')->insert(
                    array_merge($holiday, [
                        'source' => 'gazetted',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );
            }
        }
    }

    private function getHolidays2025(): array
    {
        return [
            ['holiday_date' => '2025-01-01', 'name' => 'New Year\'s Day', 'year' => 2025, 'is_recurring' => true],
            ['holiday_date' => '2025-01-02', 'name' => 'Replacement Holiday (New Year)', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-01-14', 'name' => 'Israk and Mikraj', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-01-29', 'name' => 'Chinese New Year', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-01-30', 'name' => 'Chinese New Year (2nd Day)', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-02-01', 'name' => 'Federal Territory Day', 'year' => 2025, 'is_recurring' => true],
            ['holiday_date' => '2025-02-11', 'name' => 'Thaipusam', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-03-01', 'name' => 'Nuzul Al-Quran', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-03-30', 'name' => 'Hari Raya Aidilfitri', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-03-31', 'name' => 'Hari Raya Aidilfitri (2nd Day)', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-04-01', 'name' => 'Replacement Holiday (Hari Raya)', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-05-01', 'name' => 'Labour Day', 'year' => 2025, 'is_recurring' => true],
            ['holiday_date' => '2025-05-12', 'name' => 'Vesak Day', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-06-02', 'name' => 'Yang di-Pertuan Agong Birthday', 'year' => 2025, 'is_recurring' => true],
            ['holiday_date' => '2025-06-06', 'name' => 'Hari Raya Haji', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-06-07', 'name' => 'Hari Raya Haji (2nd Day)', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-06-27', 'name' => 'Awal Muharram', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-08-31', 'name' => 'Merdeka Day', 'year' => 2025, 'is_recurring' => true],
            ['holiday_date' => '2025-09-05', 'name' => 'Maulidur Rasul', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-09-16', 'name' => 'Malaysia Day', 'year' => 2025, 'is_recurring' => true],
            ['holiday_date' => '2025-10-20', 'name' => 'Deepavali', 'year' => 2025, 'is_recurring' => false],
            ['holiday_date' => '2025-12-25', 'name' => 'Christmas Day', 'year' => 2025, 'is_recurring' => true],
        ];
    }

    private function getHolidays2026(): array
    {
        return [
            ['holiday_date' => '2026-01-01', 'name' => 'New Year\'s Day', 'year' => 2026, 'is_recurring' => true],
            ['holiday_date' => '2026-01-03', 'name' => 'Israk and Mikraj', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-02-01', 'name' => 'Federal Territory Day', 'year' => 2026, 'is_recurring' => true],
            ['holiday_date' => '2026-02-17', 'name' => 'Chinese New Year', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-02-18', 'name' => 'Chinese New Year (2nd Day)', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-02-18', 'name' => 'Nuzul Al-Quran', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-03-20', 'name' => 'Hari Raya Aidilfitri', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-03-21', 'name' => 'Hari Raya Aidilfitri (2nd Day)', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-04-01', 'name' => 'Thaipusam', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-05-01', 'name' => 'Labour Day', 'year' => 2026, 'is_recurring' => true],
            ['holiday_date' => '2026-05-02', 'name' => 'Vesak Day', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-05-27', 'name' => 'Hari Raya Haji', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-05-28', 'name' => 'Hari Raya Haji (2nd Day)', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-06-01', 'name' => 'Yang di-Pertuan Agong Birthday', 'year' => 2026, 'is_recurring' => true],
            ['holiday_date' => '2026-06-16', 'name' => 'Awal Muharram', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-08-25', 'name' => 'Maulidur Rasul', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-08-31', 'name' => 'Merdeka Day', 'year' => 2026, 'is_recurring' => true],
            ['holiday_date' => '2026-09-16', 'name' => 'Malaysia Day', 'year' => 2026, 'is_recurring' => true],
            ['holiday_date' => '2026-11-08', 'name' => 'Deepavali', 'year' => 2026, 'is_recurring' => false],
            ['holiday_date' => '2026-12-25', 'name' => 'Christmas Day', 'year' => 2026, 'is_recurring' => true],
        ];
    }
}
