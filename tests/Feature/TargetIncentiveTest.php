<?php

namespace Tests\Feature;

use App\Models\Target;
use App\Services\TargetIncentiveService;
use Tests\TestCase;

class TargetIncentiveTest extends TestCase
{
    public function test_disabled_and_inactive_targets_do_not_earn_incentives(): void
    {
        $service = new TargetIncentiveService();
        foreach ([['incentive_enabled' => false, 'status' => 'active'], ['incentive_enabled' => true, 'status' => 'inactive']] as $settings) {
            $target = new Target(array_merge(['sales_executive_id' => 'person', 'incentive_basis' => 'individual', 'incentive_multiplier' => '0.002'], $settings));
            $this->assertEquals(0, $service->calculate($target, collect([['owner' => 'person', 'products' => [], 'amount' => 1000000]]))['incentive']);
        }
    }

    public function test_individual_incentive_is_on_every_sale_not_only_target_excess(): void
    {
        $target = new Target(['sales_executive_id' => 'person', 'status' => 'active', 'target_amount' => 2000000, 'incentive_enabled' => true, 'incentive_basis' => 'individual', 'incentive_multiplier' => '0.002']);
        $result = (new TargetIncentiveService())->calculate($target, collect([['owner' => 'person', 'products' => [], 'amount' => 1000000], ['owner' => 'other', 'products' => [], 'amount' => 500000]]));
        $this->assertEquals(1000000, $result['sales_amount']);
        $this->assertEquals(2000, $result['incentive']);
    }

    public function test_company_products_count_a_matching_sale_only_once(): void
    {
        $target = new Target(['status' => 'active', 'incentive_enabled' => true, 'incentive_basis' => 'company', 'incentive_multiplier' => '0.005', 'incentive_product_ids' => ['charter', 'yacht']]);
        $result = (new TargetIncentiveService())->calculate($target, collect([['owner' => 'any', 'products' => ['charter', 'yacht'], 'amount' => 100000], ['owner' => 'other', 'products' => ['helicopter'], 'amount' => 900000]]));
        $this->assertEquals(100000, $result['sales_amount']);
        $this->assertEquals(500, $result['incentive']);
    }

    public function test_manager_receives_own_and_team_sales_at_separate_rates(): void
    {
        $target = new Target(['sales_executive_id' => 'manager', 'status' => 'active', 'incentive_enabled' => true, 'incentive_basis' => 'team', 'incentive_multiplier' => '0.002', 'incentive_team_multiplier' => '0.005']);
        $service = new class extends TargetIncentiveService {
            protected function team(string $manager): array { return ['manager', 'member']; }
        };
        $result = $service->calculate($target, collect([['owner'=>'manager','products'=>[],'amount'=>500000],['owner'=>'member','products'=>[],'amount'=>800000],['owner'=>'outsider','products'=>[],'amount'=>900000]]));
        $this->assertEquals(1000, $result['own_incentive']);
        $this->assertEquals(4000, $result['team_incentive']);
        $this->assertEquals(5000, $result['incentive']);
    }

    public function test_exported_text_is_not_an_executable_formula(): void
    {
        $export = new \App\Exports\TargetIncentiveExport(collect());
        $sheet = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        foreach (['=1+1', ' @SUM(1)', "\t=1+1"] as $value) {
            $cell = $sheet->getCell('A1'); $export->bindValue($cell, $value);
            $this->assertEquals(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cell->getDataType());
            $this->assertEquals("'".$value, $cell->getValue());
        }
    }
}
