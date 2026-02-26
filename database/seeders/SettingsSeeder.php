<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'currency', 'value' => 'EGP', 'group' => 'general'],
            ['key' => 'whatsapp_enabled', 'value' => 'false', 'group' => 'whatsapp'],
            ['key' => 'email_enabled', 'value' => 'true', 'group' => 'email'],
            ['key' => 'fees_global_policy_id', 'value' => null, 'group' => 'fees'],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
