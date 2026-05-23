<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name'                    => 'Free',
            'slug'                    => 'free',
            'type'                    => 'free',
            'billing_period'          => 'monthly',
            'price'                   => 0,
            'currency'                => 'USD',
            'ai_messages_per_day'     => 5,
            'max_children'            => 1,
            'max_documents_per_child' => 3,
            'has_specialist_access'   => false,
            'has_voice_mode'          => false,
            'has_progress_reports'    => false,
            'is_active'               => true,
        ]);

        Plan::updateOrCreate(['slug' => 'pro-monthly'], [
            'name'                    => 'Pro Monthly',
            'slug'                    => 'pro-monthly',
            'type'                    => 'pro',
            'billing_period'          => 'monthly',
            'price'                   => 9.99,
            'currency'                => 'USD',
            'ai_messages_per_day'     => null,
            'max_children'            => null,
            'max_documents_per_child' => null,
            'has_specialist_access'   => true,
            'has_voice_mode'          => true,
            'has_progress_reports'    => true,
            'is_active'               => true,
        ]);

        Plan::updateOrCreate(['slug' => 'pro-yearly'], [
            'name'                    => 'Pro Yearly',
            'slug'                    => 'pro-yearly',
            'type'                    => 'pro',
            'billing_period'          => 'yearly',
            'price'                   => 99.99,
            'currency'                => 'USD',
            'ai_messages_per_day'     => null,
            'max_children'            => null,
            'max_documents_per_child' => null,
            'has_specialist_access'   => true,
            'has_voice_mode'          => true,
            'has_progress_reports'    => true,
            'is_active'               => true,
        ]);
    }
}
