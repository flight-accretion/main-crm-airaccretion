<?php

namespace Tests\Feature;

use App\Models\KpiOutreachAssignment;
use App\Models\KpiOutreachPool;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class KpiOutreachViewTest extends TestCase
{
    public function test_summary_is_prefilled_in_editable_remark_without_a_separate_summary_column(): void
    {
        $row = new KpiOutreachAssignment([
            'id' => (string) Str::uuid(),
            'normalized_phone' => '6500009001',
            'assigned_at' => now(),
        ]);

        $row->exists = true;
        $row->setAttribute('latest_skyrec_summary', 'Skyrec call says customer asked for a quote.');
        $row->setAttribute('last_called_at_display', now());
        $row->setAttribute('last_product_display', 'Plane Ride in Mumbai');
        $row->setRelation('pool', new KpiOutreachPool([
            'display_name' => 'Test Customer',
        ]));

        $html = view('admin.pages.kpi.partials.outreach-table', [
            'rows' => new Collection([$row]),
            'locked' => false,
        ])->render();

        $this->assertStringNotContainsString('<th>Skyrec Summary</th>', $html);
        $this->assertStringNotContainsString('<th>Summary</th>', $html);
        $this->assertStringContainsString(
            'Skyrec call says customer asked for a quote.',
            $html
        );
        $this->assertStringContainsString('name="remark"', $html);
    }
}
