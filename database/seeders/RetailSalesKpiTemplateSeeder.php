<?php

namespace Database\Seeders;

use App\Models\KpiMetric;
use App\Models\KpiTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RetailSalesKpiTemplateSeeder extends Seeder
{
    public function run()
    {
        $template = KpiTemplate::firstOrCreate(
            [
                'name' => 'Retail Sales KPI',
                'department' => 'sales',
            ],
            [
                'id' => (string) Str::uuid(),
                'working_days_per_month' => 22,
                'active' => true,
                'effective_from' => now()->startOfMonth()->toDateString(),
            ]
        );

        $metrics = [
            [
                'code' => 'daily_outreach',
                'name' => 'Daily Outreach',
                'description' => 'Make 50 calls to customers to generate leads.',
                'weightage' => 25,
                'measurement_type' => 'automatic',
                'source_key' => 'sales_outreach',
                'target_value' => 50,
                'direction' => 'higher_better',
                'score_rules' => ['5' => 100, '4' => 90, '3' => 80, '2' => 70, '1' => 0],
                'sort_order' => 10,
            ],
            [
                'code' => 'lead_conversion',
                'name' => 'Lead Conversion Rate',
                'description' => 'Convert incoming leads into successful bookings.',
                'weightage' => 15,
                'measurement_type' => 'automatic',
                'source_key' => 'sales_conversion',
                'target_value' => 30,
                'direction' => 'higher_better',
                'score_rules' => ['5' => 40, '4' => 35, '3' => 30, '2' => 25, '1' => 0],
                'sort_order' => 20,
            ],
            [
                'code' => 'monthly_target',
                'name' => 'Monthly Sales Target',
                'description' => 'Achieve the monthly target set by manager.',
                'weightage' => 40,
                'measurement_type' => 'automatic',
                'source_key' => 'sales_target',
                'target_value' => null,
                'direction' => 'higher_better',
                'score_rules' => ['5' => 100, '4' => 90, '3' => 80, '2' => 70, '1' => 0],
                'sort_order' => 30,
            ],
            [
                'code' => 'response_time',
                'name' => 'Response Time',
                'description' => 'Respond to client inquiries within the first 15 minutes.',
                'weightage' => 3,
                'measurement_type' => 'automatic',
                'source_key' => 'sales_response_time',
                'target_value' => 15,
                'direction' => 'lower_better',
                'score_rules' => ['5' => 15, '4' => 20, '3' => 30, '2' => 40, '1' => 999999],
                'sort_order' => 40,
            ],
            [
                'code' => 'followup_sla',
                'name' => 'Follow-Up SLA',
                'description' => 'Ensure follow-up on pending cases within 4 hours.',
                'weightage' => 2,
                'measurement_type' => 'automatic',
                'source_key' => 'sales_followup_sla',
                'target_value' => 100,
                'direction' => 'higher_better',
                'score_rules' => ['5' => 100, '4' => 90, '3' => 80, '2' => 70, '1' => 0],
                'sort_order' => 50,
            ],
            [
                'code' => 'payment_collection',
                'name' => 'Payment Collection & Follow-Up',
                'description' => 'Collect customer payments and keep partial balances under follow-up until full payment.',
                'weightage' => 5,
                'measurement_type' => 'automatic',
                'source_key' => 'sales_payment_collection',
                'target_value' => 100,
                'direction' => 'higher_better',
                'score_rules' => ['5' => 100, '4' => 90, '3' => 80, '2' => 70, '1' => 0],
                'sort_order' => 60,
            ],
            [
                'code' => 'attendance',
                'name' => 'Attendance / Punctuality',
                'description' => 'Achieve a punctuality rate of 95% for scheduled shifts.',
                'weightage' => 10,
                'measurement_type' => 'manual_numeric',
                'source_key' => null,
                'target_value' => 100,
                'direction' => 'higher_better',
                'score_rules' => ['5' => 100, '4' => 90, '3' => 80, '2' => 70, '1' => 0],
                'sort_order' => 70,
            ],
        ];

        foreach ($metrics as $row) {
            $metric = KpiMetric::firstOrNew([
                'template_id' => $template->id,
                'code' => $row['code'],
            ]);

            if (!$metric->exists) {
                $metric->id = (string) Str::uuid();
            }

            $metric->fill($row);
            $metric->active = true;
            $metric->save();
        }
    }
}
