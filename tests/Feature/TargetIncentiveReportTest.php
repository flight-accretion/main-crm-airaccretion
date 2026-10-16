<?php

namespace Tests\Feature;

use App\Exports\SalesReportExport;
use App\Http\Controllers\TargetController;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetIncentiveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TargetIncentiveReportTest extends LeadListControllerIntegrationTest
{
    private array $people = [];
    private string $product;
    private array $saleIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->default(0);
            foreach (['service_ids','extra_service_ids','service_details'] as $field) $table->text($field)->nullable();
        });
        Schema::table('payment_audit_trail', fn (Blueprint $table) => $table->dateTime('paid_date')->nullable());
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('service_amount',15,2)->default(0); $table->decimal('fees_percent',8,2)->default(0);
        });
        Schema::create('extra_services', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('extra_service'); $table->string('service_id')->nullable();
            $table->decimal('extra_service_amount',15,2)->default(0);
        });
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('lead_followup_id'); $table->integer('status'); $table->decimal('refund_amount',15,2);
        });
        require_once base_path('database/migrations/2025_09_06_160536_create_targets_table.php');
        require_once base_path('database/migrations/2026_10_14_180000_add_incentive_settings_to_targets.php');
        (new \CreateTargetsTable())->up(); (new \AddIncentiveSettingsToTargets())->up();
        foreach (['manager'=>'manager','sales'=>'own','operations'=>'operations','unconfigured'=>'own'] as $name=>$role) {
            $this->people[$name]=User::create(['name'=>$name==='sales'?'=Sales Person':ucfirst($name).' Person','user_type_id'=>$role,'status'=>1]);
        }
        $this->product=(string)Str::uuid(); DB::table('products')->insert(['id'=>$this->product,'product'=>'Charter']);
        DB::table('sales_executive_assignments')->insert(['id'=>(string)Str::uuid(),'manager_id'=>$this->people['manager']->id,'sales_executive_id'=>$this->people['sales']->id,'status'=>1]);
        $this->saleIds['sales']=$this->sale('sales',1000,10,100);
        $this->saleIds['manager']=$this->sale('manager',500,0,0);
        foreach (['sales'=>'individual','manager'=>'team','operations'=>'company'] as $person=>$basis) {
            Target::create(['id'=>(string)Str::uuid(),'sales_executive_id'=>$this->people[$person]->id,'assigned_by'=>'hr','year'=>2026,'month'=>10,
                'target_amount'=>100000,'status'=>'active','incentive_enabled'=>true,'incentive_basis'=>$basis,
                'incentive_multiplier'=>0.002,'incentive_team_multiplier'=>$basis==='team'?0.005:null,
                'incentive_product_ids'=>$basis==='company'?[$this->product]:null]);
        }
        // Company rate applies to Charter; manager's own sale uses a different product.
        DB::table('leads')->where('id',$this->saleIds['manager'])->update(['product_ids'=>'[]']);
    }

    private function sale(string $person, float $gross, float $fee, float $refund, string $paid='2026-10-03', int $status=3): string
    {
        $id=(string)Str::uuid(); $followup=(string)Str::uuid();
        DB::table('clients')->insert(['id'=>$id,'name'=>'Client '.$person,'contact_number'=>'9876543210','email'=>'client@example.test']);
        DB::table('leads')->insert(['id'=>$id,'client_id'=>$id,'representative_user_id'=>$this->people[$person]->id,'product_ids'=>json_encode([$this->product]),'service_ids'=>'[]','created_at'=>'2026-11-02','updated_at'=>'2026-11-02']);
        DB::table('lead_followups')->insert(['id'=>$followup,'lead_id'=>$id,'status'=>$status,'total_amount'=>$gross,
            'service_ids'=>'[]','extra_service_ids'=>'[]','service_details'=>json_encode([['final_amount'=>$gross,'fees_percent'=>$fee]]),'created_at'=>'2026-11-02']);
        foreach ([1,2] as $payment) DB::table('payment_audit_trail')->insert(['id'=>(string)Str::uuid(),'lead_followup_id'=>$followup,'payment_status'=>1,'paid_amount'=>$gross/2,'paid_date'=>$paid]);
        if ($refund) DB::table('lead_refunds')->insert(['id'=>(string)Str::uuid(),'lead_followup_id'=>$followup,'refund_amount'=>$refund,'status'=>1]);
        return $id;
    }

    private function download(string $type='csv', ?string $person=null, int $month=10, string $role='hr'): string
    {
        auth()->setUser(User::findOrFail($role));
        $request=Request::create('/admin/targets/download-incentive','GET',['year'=>2026,'month'=>$month,'file_type'=>$type,'person_id'=>$person]);
        $request->setUserResolver(fn()=>auth()->user()); app()->instance('request',$request);
        $response=(new TargetController(new TargetIncentiveService()))->downloadIncentive($request);
        ob_start(); try { $response->sendContent(); return ob_get_contents(); } finally { ob_end_clean(); }
    }

    private function csv(?string $person=null, int $month=10): array
    {
        $csv=$this->download('csv',$person,$month);
        $stream=fopen('php://temp','r+'); fwrite($stream,$csv); rewind($stream); $rows=[];
        while (($row=fgetcsv($stream))!==false) $rows[]=$row;
        fclose($stream); return $rows;
    }

    private function amount($value): float { return (float) str_replace(',', '', (string) $value); }

    public function test_detail_preserves_sales_report_columns_net_amounts_and_unique_sales(): void
    {
        $rows=$this->csv();
        $this->assertSame((new SalesReportExport())->headings(),array_slice($rows[0],0,17));
        $this->assertSame(4,count($rows));
        $data=collect(array_slice($rows,1,-1))->keyBy(fn($row)=>$row[0]);
        $this->assertSame(800.0,(float)$data['Client sales'][7]);
        $this->assertSame(1.6,(float)$data['Client sales'][17]);
        $this->assertSame(4.0,(float)$data['Client sales'][19]);
        $this->assertSame(1.6,(float)$data['Client sales'][20]);
        $this->assertSame(7.2,(float)$data['Client sales'][21]);
        $this->assertSame(1.0,(float)$data['Client manager'][18]);
        $this->assertSame(0.0,(float)$data['Client manager'][17]);
        $this->assertSame(0.0,(float)$data['Client manager'][19]);
        $this->assertSame(true,str_contains($data['Client sales'][22],'Manager Person (Team: 4.00)'));
        $total=end($rows); $this->assertSame('TOTAL',$total[0]);
        $this->assertSame(1300.0,$this->amount($total[7])); $this->assertSame(8.2,$this->amount($total[21]));
        $this->assertSame(false,str_starts_with($total[21],'='));
        foreach ((new SalesReportExport(['year'=>2026,'month'=>10]))->generator() as $sale) {
            $expected=array_map(fn($value)=>$value===null?'':(string)$value,(new SalesReportExport())->map($sale));
            // Literal text beginning with '=' is escaped for spreadsheet safety only.
            if (str_starts_with($expected[8],'=')) $expected[8]="'".$expected[8];
            $this->assertSame($expected,array_slice($data[$sale->client_name],0,17));
        }
    }

    public function test_selected_person_uses_their_own_team_or_company_sales_without_other_payouts(): void
    {
        foreach (['sales'=>[1,1.6],'manager'=>[2,5.0],'operations'=>[1,1.6]] as $person=>[$count,$amount]) {
            $rows=$this->csv($this->people[$person]->id); $this->assertSame($count+2,count($rows));
            $total=end($rows); $this->assertSame($amount,(float)$total[21]);
        }
        $rows=$this->csv($this->people['unconfigured']->id); $this->assertSame(2,count($rows));
    }

    public function test_month_uses_approved_paid_date_and_retains_cancelled_net_sales(): void
    {
        $this->sale('sales',200,0,50,'2026-10-31 23:59:59',2);
        $this->sale('sales',10000,0,0,'2026-11-01 00:00:00');
        $rows=$this->csv(); $this->assertSame(5,count($rows));
        $this->assertSame(1450.0,$this->amount(end($rows)[7]));
        $rows=$this->csv(null,9); $this->assertSame(2,count($rows)); $this->assertSame(0.0,(float)end($rows)[21]);
    }

    public function test_fractional_paise_reconcile_to_monthly_target_incentive(): void
    {
        DB::table('payment_audit_trail')->delete();
        foreach (range(1,3) as $i) $this->sale('sales',1,0,0);
        Target::where('sales_executive_id',$this->people['sales']->id)->update(['incentive_multiplier'=>0.005]);
        $rows=$this->csv($this->people['sales']->id);
        $this->assertSame(0.02,(float)end($rows)[17]);
        $cents=array_map(fn($row)=>(int)round((float)$row[17]*100),array_slice($rows,1,-1)); sort($cents);
        $this->assertSame([0,1,1],$cents);
        $this->assertSame($rows,$this->csv($this->people['sales']->id));
    }

    public function test_xlsx_has_two_sheets_safe_text_formulas_and_reconciled_totals(): void
    {
        $bytes=$this->download('excel'); $path=tempnam(sys_get_temp_dir(),'incentive-report-');
        try {
            file_put_contents($path,$bytes);
            $directory=getenv('CRM_INCENTIVE_PREVIEW_DIR');
            if ($directory) { if (!is_dir($directory)) mkdir($directory,0777,true); file_put_contents($directory.'/incentives-verified.xlsx',$bytes); }
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder());
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $book->getCalculationEngine()->clearCalculationCache();
            $this->assertSame(['Sales Detail','Employee Summary'],$book->getSheetNames());
            $detail=$book->getSheet(0); $summary=$book->getSheet(1);
            $this->assertSame(8.2,(float)$detail->getCell('V4')->getOldCalculatedValue());
            $this->assertSame('=SUM(V2:V3)',$detail->getCell('V4')->getValue());
            $last=$summary->getHighestDataRow();
            $this->assertSame(['detail'=>8.2,'summary'=>8.2],['detail'=>(float)$detail->getCell('V4')->getCalculatedValue(),'summary'=>(float)$summary->getCell('K'.$last)->getCalculatedValue()]);
            $this->assertSame('A2',$detail->getFreezePane());
            $this->assertSame('4CAF50',$detail->getStyle('A1')->getFill()->getStartColor()->getRGB());
            $this->assertSame('#,##0.00',$detail->getStyle('V2')->getNumberFormat()->getFormatCode());
            $this->assertSame(23,count($detail->rangeToArray('A1:W1')[0]));
            $this->assertSame(true,str_contains($summary->getComment('I'.$last)->getText()->getPlainText(),'overlap'));
            foreach ($book->getAllSheets() as $sheet) foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) if ($cell->getDataType()==='f') {
                    $this->assertSame(true,str_starts_with($cell->getValue(),'=SUM('));
                    $this->assertSame($sheet->getHighestDataRow(),$cell->getRow());
                }
            }
            $book->disconnectWorksheets();
        } finally { @unlink($path); }
    }

    public function test_export_query_count_does_not_grow_per_employee(): void
    {
        $this->csv(); DB::enableQueryLog(); DB::flushQueryLog(); $this->csv(); $first=count(DB::getQueryLog());
        foreach (range(1,12) as $i) {
            $user=User::create(['name'=>'Extra '.$i,'user_type_id'=>'operations','status'=>1]);
            Target::create(['id'=>(string)Str::uuid(),'sales_executive_id'=>$user->id,'assigned_by'=>'hr','year'=>2026,'month'=>10,'target_amount'=>0,
                'status'=>'active','incentive_enabled'=>true,'incentive_basis'=>'company','incentive_multiplier'=>0.002]);
        }
        DB::flushQueryLog(); $this->csv(); $second=count(DB::getQueryLog()); DB::disableQueryLog();
        $this->assertSame($first,$second);
    }

    public function test_duplicate_active_targets_stop_export_instead_of_double_paying(): void
    {
        $target=Target::where('sales_executive_id',$this->people['sales']->id)->first();
        $copy=$target->replicate(); $copy->id=(string)Str::uuid(); $copy->save();
        $this->expectException(ValidationException::class); $this->download();
    }

    public function test_export_permissions_remain_hr_and_super_admin_only(): void
    {
        foreach (['hr','super'] as $role) $this->assertSame(true,str_contains($this->download('csv',null,10,$role),'Individual Incentive'));
        foreach (['admin','manager','own','operations'] as $role) {
            try { $this->download('csv',null,10,$role); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) { $this->assertSame(403,$error->getStatusCode()); continue; }
            $this->assertSame('export denied','export allowed');
        }
        $this->people['unconfigured']->status=0; $this->people['unconfigured']->save();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);
        $this->download('csv',$this->people['unconfigured']->id);
    }

    public function test_product_overlap_is_not_double_paid_and_all_products_includes_every_sale(): void
    {
        $second=(string)Str::uuid(); DB::table('products')->insert(['id'=>$second,'product'=>'Yacht']);
        DB::table('leads')->where('id',$this->saleIds['sales'])->update(['product_ids'=>json_encode([$this->product,$second])]);
        Target::where('sales_executive_id',$this->people['operations']->id)->update(['incentive_product_ids'=>json_encode([$this->product,$second])]);
        $rows=$this->csv($this->people['operations']->id); $this->assertSame(1.6,(float)end($rows)[20]);
        Target::where('sales_executive_id',$this->people['operations']->id)->update(['incentive_product_ids'=>null]);
        $rows=$this->csv($this->people['operations']->id); $this->assertSame(2.6,(float)end($rows)[20]);
        $this->assertSame(1300.0,$this->amount(end($rows)[7]));
    }

    public function test_unconfigured_employee_has_real_sales_but_no_invented_incentive(): void
    {
        $this->sale('unconfigured',100,0,0);
        $rows=$this->csv($this->people['unconfigured']->id);
        $this->assertSame(3,count($rows)); $this->assertSame(100.0,(float)$rows[1][7]);
        $this->assertSame(0.0,(float)$rows[1][21]); $this->assertSame('No configured incentive',$rows[1][22]);
    }

    public function test_chunk_boundaries_preserve_every_sale_once_and_reconcile_rounding(): void
    {
        foreach (range(1,251) as $i) $this->sale('sales',1,0,0);
        $rows=$this->csv($this->people['sales']->id);
        $this->assertSame(254,count($rows)); $total=end($rows);
        $this->assertSame(1051.0,$this->amount($total[7])); $this->assertSame(2.1,(float)$total[21]);
    }
}
