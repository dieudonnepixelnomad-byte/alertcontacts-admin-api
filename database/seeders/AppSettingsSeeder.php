<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AppSetting;

class AppSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppSetting::updateOrCreate(
            ['key' => 'android_min_version'],
            ['value' => '3.1.0']
        );

        AppSetting::updateOrCreate(
            ['key' => 'android_store_url'],
            ['value' => 'https://play.google.com/store/apps/details?id=com.alertcontacts.alertcontacts']
        );

        AppSetting::updateOrCreate(
            ['key' => 'ios_min_version'],
            ['value' => '3.1.0']
        );

        AppSetting::updateOrCreate(
            ['key' => 'ios_store_url'],
            ['value' => 'https://apps.apple.com/app/alert-contacts/id1649934420']
        );
    }
}
