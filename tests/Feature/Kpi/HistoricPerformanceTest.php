<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\HistoricPerformanceController;
use App\Models\KpiMonthlySnapshot;
use App\Models\Target;
use App\Models\UserType;
use App\Services\Kpi\HistoricPerformanceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricPerformanceTest extends KpiFeatureTestCase
{
    private $employee;
    private $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('kpi_monthly_snapshots', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('user_id'); $table->string('template_id');
            $table->integer('year'); $table->integer('month'); $table->float('overall_score');
            $table->float('weighted_raw_score'); $table->timestamp('finalized_at')->nullable(); $table->timestamps();
        });
        $this->employee = $this->createUserWithRole('Historic Sales', UserType::SALES_EXECUTIVE);
        $this->hr = $this->createUserWithRole('Historic HR', UserType::HR);
    }

    private function request(array $input = [], $actor = null): Request
    {
        $request = Request::create('/admin/kpi-history', 'GET', $input);
        $request->setUserResolver(fn () => $actor ?: $this->hr);
        return $request;
    }

    public function test_snapshot_scores_preserve_missing_months_and_hide_unassigned_targets(): void
    {
        KpiMonthlySnapshot::create(['user_id'=>$this->employee->id,'template_id'=>'historic','year'=>2026,'month'=>7,'overall_score'=>4,'weighted_raw_score'=>3.8,'finalized_at'=>now()]);
        $rows = app(HistoricPerformanceService::class)->rows(collect([$this->employee]), Carbon::parse('2026-07-01'), Carbon::parse('2026-08-01'));
        $this->assertSame(4, $rows[0]['score']);
        $this->assertSame('Finalized', $rows[0]['source']);
        $this->assertSame(null, $rows[1]['score']);
        $this->assertSame(null, $rows[0]['target']);
        $this->assertSame(2, count($rows));
    }

    public function test_live_target_uses_selected_period_not_carried_target_month_and_never_writes(): void
    {
        $target = Target::create(['id'=>'carry-test','sales_executive_id'=>$this->employee->id,'year'=>2026,'month'=>7,'target_amount'=>1000,'achieved_amount'=>77,'status'=>'active']);
        $owner = $this->employee->id;
        app()->instance(\App\Services\TargetIncentiveService::class, new class($owner) extends \App\Services\TargetIncentiveService {
            public function __construct(private string $owner) {}
            public function ledgerForPeriod(Carbon $from, Carbon $to): \Illuminate\Support\Collection
            {
                if ($from->toDateString() !== '2026-09-01' || $to->toDateString() !== '2026-09-17') throw new \RuntimeException('Wrong selected sales period');
                return collect([['owner'=>$this->owner,'amount'=>600],['owner'=>'other','amount'=>900]]);
            }
        });
        $result = app(\App\Services\Kpi\SalesTargetKpiResolver::class)->resolve($this->employee, new \App\Models\KpiMetric(), Carbon::parse('2026-09-17'), 22, Carbon::parse('2026-09-01'));
        $this->assertSame(600.0, $result['actual_value']);
        $this->assertSame(1000.0, $result['target_value']);
        $this->assertSame(60.0, $result['achievement_percent']);
        $this->assertSame(77.0, (float) $target->fresh()->achieved_amount);
    }

    public function test_targets_use_real_net_sales_without_modifying_target_records(): void
    {
        $target = Target::create(['id'=>'historic-target','sales_executive_id'=>$this->employee->id,'year'=>2026,'month'=>7,'target_amount'=>1000,'achieved_amount'=>77,'status'=>'active']);
        app()->instance(\App\Services\TargetIncentiveService::class, new class extends \App\Services\TargetIncentiveService {
            public function ledger(int $year, int $month): \Illuminate\Support\Collection
            {
                return collect([['owner'=>\App\Models\User::where('name','Historic Sales')->value('id'),'amount'=>600,'products'=>[]]]);
            }
        });
        $rows = app(HistoricPerformanceService::class)->rows(collect([$this->employee]), Carbon::parse('2026-07-01'), Carbon::parse('2026-08-01'));
        $this->assertSame(1000.0, $rows[0]['target']); $this->assertSame(600.0, $rows[0]['achieved']);
        $this->assertSame(60.0, $rows[0]['achievement']); $this->assertSame(null, $rows[1]['target']);
        $this->assertSame(77.0, (float) $target->fresh()->achieved_amount);
    }

    public function test_period_sales_refresh_after_payment_approval_and_exclude_other_dates(): void
    {
        $lead = $this->createLeadFor($this->employee);
        DB::table('lead_followups')->insert(['id'=>'live-sale','lead_id'=>$lead,'status'=>3,'total_amount'=>1000,'created_at'=>'2026-09-10']);
        DB::table('payment_audit_trail')->insert(['id'=>'live-payment','lead_followup_id'=>'live-sale','paid_amount'=>1000,'paid_date'=>'2026-09-10','payment_status'=>0]);
        $service = app(\App\Services\TargetIncentiveService::class);
        $from = Carbon::parse('2026-09-01'); $to = Carbon::parse('2026-09-17');
        $this->assertSame(0, $service->ledgerForPeriod($from,$to)->count());
        DB::table('payment_audit_trail')->where('id','live-payment')->update(['payment_status'=>1]);
        $this->assertSame(1, $service->ledgerForPeriod($from,$to)->count());
        $this->assertSame(0, $service->ledgerForPeriod(Carbon::parse('2026-09-11'),$to)->count());
        DB::table('payment_audit_trail')->where('id','live-payment')->update(['paid_date'=>'2026-09-18']);
        $this->assertSame(0, $service->ledgerForPeriod($from,$to)->count());
    }

    public function test_conversion_and_response_exclude_activity_after_selected_end(): void
    {
        $leadId = $this->createLeadFor($this->employee, 'Period Customer', '2026-09-10 10:00:00');
        $lead = \App\Models\Lead::findOrFail($leadId);
        Schema::create('call_summary_integrations', function (Blueprint $table) {
            $table->string('id'); $table->string('lead_id'); $table->string('agent_user_id');
            $table->string('direction'); $table->timestamp('call_start_at');
        });
        DB::table('call_summary_integrations')->insert(['id'=>'later-call','lead_id'=>$leadId,'agent_user_id'=>$this->employee->id,'direction'=>'outgoing','call_start_at'=>'2026-09-18 10:00:00']);
        $communication = app(\App\Services\Kpi\SalesKpiCommunicationService::class);
        $this->assertSame(null, $communication->firstResponseAt($lead,$this->employee,Carbon::parse('2026-09-10'),Carbon::parse('2026-09-17')->endOfDay()));
        $this->assertSame('2026-09-18', $communication->firstResponseAt($lead,$this->employee,Carbon::parse('2026-09-10'))->toDateString());
        DB::table('lead_followups')->insert(['id'=>'converted','lead_id'=>$leadId,'status'=>3,'created_at'=>'2026-09-18']);
        DB::table('payment_audit_trail')->insert(['id'=>'conversion-payment','lead_followup_id'=>'converted','paid_amount'=>1000,'paid_date'=>'2026-09-18','payment_status'=>1]);
        $resolver=app(\App\Services\Kpi\SalesLeadConversionKpiResolver::class);
        $result=$resolver->resolve($this->employee,new \App\Models\KpiMetric(),Carbon::parse('2026-09-17'),22,Carbon::parse('2026-09-01'));
        $this->assertSame(0,$result['actual_value']);
        DB::table('payment_audit_trail')->where('id','conversion-payment')->update(['paid_date'=>'2026-09-16']);
        $result=$resolver->resolve($this->employee,new \App\Models\KpiMetric(),Carbon::parse('2026-09-17'),22,Carbon::parse('2026-09-01'));
        $this->assertSame(1,$result['actual_value']);
    }

    public function test_only_hr_and_super_admin_can_open_or_export(): void
    {
        $controller=app(HistoricPerformanceController::class);
        foreach ([UserType::ADMIN,UserType::SALES_EXECUTIVE,UserType::OPERATIONS_EXECUTIVE] as $role) {
            $actor=$this->createUserWithRole('Forbidden '.$role,$role);
            foreach (['index','download'] as $action) {
                $status=0;
                try {$controller->$action($this->request([], $actor));} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {$status=$e->getStatusCode();}
                $this->assertSame(403,$status);
            }
        }
        $data=$controller->index($this->request(['employee_id'=>$this->employee->id]))->getData();
        $this->assertSame($this->employee->id,$data['selectedEmployee']?->id);
        $super=$this->createUserWithRole('History Super',UserType::SUPER_ADMIN);
        $this->assertSame(12,count($controller->index($this->request([], $super))->getData()['rows']));
    }

    public function test_invalid_ranges_future_months_and_foreign_employee_are_rejected(): void
    {
        foreach ([['from'=>'2026-09','to'=>'2026-07'],['to'=>'2026-10'],['from'=>'2020-01'],['employee_id'=>'not-an-employee']] as $input) {
            $failed=false;
            try {app(HistoricPerformanceController::class)->index($this->request($input));} catch (\Illuminate\Validation\ValidationException $e) {$failed=true;}
            $this->assertSame(true,$failed);
        }
    }

    public function test_exports_keep_missing_values_and_do_not_treat_scores_as_formulas(): void
    {
        $sheet=new \App\Exports\HistoricPerformanceSheet(collect([['employee'=>'=HYPERLINK(1)','department'=>'sales','period'=>'2026-07','score'=>null,'raw_score'=>null,'source'=>'Not available','target'=>null,'achieved'=>null,'achievement'=>null,'period_status'=>'Completed month']]),'kpi');
        $this->assertSame(null,$sheet->collection()[0][3]);
        $book=new \App\Exports\HistoricPerformanceWorkbook(collect(),['kpi','target']);
        $this->assertSame(2,count($book->sheets()));
        $cell=(new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet()->getCell('A1');
        $sheet->bindValue($cell,'=HYPERLINK(1)');
        $this->assertSame('s',$cell->getDataType());
        $this->assertSame("'=HYPERLINK(1)",$cell->getValue());
    }

    public function test_recalculation_uses_existing_scoring_without_writing_targets(): void
    {
        $template=\App\Models\KpiTemplate::create(['name'=>'History Sales','department'=>'sales','active'=>true]);
        \App\Models\KpiMetric::create(['template_id'=>$template->id,'code'=>'monthly_target','name'=>'Target','weightage'=>100,'measurement_type'=>'automatic','source_key'=>'sales_target','direction'=>'higher_better','score_rules'=>[5=>100,4=>90,3=>80,2=>70,1=>60],'active'=>true]);
        \App\Models\KpiUserAssignment::create(['user_id'=>$this->employee->id,'template_id'=>$template->id,'effective_from'=>'2026-07-01','active'=>true]);
        $target=Target::create(['id'=>'readonly-target','sales_executive_id'=>$this->employee->id,'year'=>2026,'month'=>7,'target_amount'=>1000,'achieved_amount'=>77,'status'=>'active']);
        $rows=app(HistoricPerformanceService::class)->rows(collect([$this->employee]),Carbon::parse('2026-07-01'),Carbon::parse('2026-07-01'));
        $this->assertSame(1,$rows[0]['score']); $this->assertSame('Recalculated',$rows[0]['source']);
        $this->assertSame(77.0,(float)$target->fresh()->achieved_amount);
    }

    public function test_rendered_views_and_downloads_use_same_real_rows(): void
    {
        KpiMonthlySnapshot::create(['user_id'=>$this->employee->id,'template_id'=>'historic','year'=>2026,'month'=>7,'overall_score'=>4,'weighted_raw_score'=>3.8,'finalized_at'=>now()]);
        $input=['employee_id'=>$this->employee->id,'from'=>'2026-07','to'=>'2026-09'];
        $controller=app(HistoricPerformanceController::class);
        $data=$controller->index($this->request($input))->getData();
        $path=resource_path('views/admin/pages/kpi/history.blade.php');
        $source=str_replace(["@extends('admin.layouts.header')","@section('content')","@endsection","@push('scripts')","@endpush"],'',file_get_contents($path));
        $compiled=sys_get_temp_dir().'/history-view-'.\Illuminate\Support\Str::uuid();mkdir($compiled);
        config(['view.compiled'=>$compiled]);app()->forgetInstance('blade.compiler');app('view.engine.resolver')->forget('blade');
        try {
            $data['errors']=new \Illuminate\Support\ViewErrorBag();
            $html=\Illuminate\Support\Facades\Blade::render($source,$data);
            $this->assertSame(false,str_contains($html,'data-history-chart="target"'));
            $this->assertSame(true,str_contains($html,'hp-single-chart'));
            $this->assertSame(true,str_contains($html,'Not scored'));
            $this->assertSame(false,str_contains($html,'@selected'));
            $dir=getenv('CRM_HISTORY_CHECK_DIR');
            if($dir)file_put_contents($dir.'/history-no-target.html','<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:24px;background:#f4f5f7;font:14px Arial}.crm-history{max-width:1300px;margin:auto}button,input,select{font:inherit}</style></head><body>'.$html.'</body></html>');
            $target=Target::create(['id'=>'render-target','sales_executive_id'=>$this->employee->id,'year'=>2026,'month'=>7,'target_amount'=>500000,'achieved_amount'=>0,'status'=>'active']);
            $data=$controller->index($this->request($input))->getData();$data['errors']=new \Illuminate\Support\ViewErrorBag();
            $html=\Illuminate\Support\Facades\Blade::render($source,$data);
            $this->assertSame(true,str_contains($html,'data-history-chart="target"'));
            $this->assertSame(true,str_contains($html,'Target vs achieved'));
            $this->assertSame(true,str_contains($html,'hp-person-range'));
            $this->assertSame(true,str_contains($html,'data-label="Achievement"'));
            if($dir)file_put_contents($dir.'/history.html','<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:24px;background:#f4f5f7;font:14px Arial}.crm-history{max-width:1300px;margin:auto}button,input,select{font:inherit}</style></head><body>'.$html.'</body></html>');
            foreach(['xlsx','csv'] as $format) {
                $response=$controller->download($this->request($input+['format'=>$format,'dataset'=>$format==='csv'?'kpi':'both']));
                $file=tempnam(sys_get_temp_dir(),'history-export-');file_put_contents($file,$response->getContent());
                \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder());
                $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($file);
                $this->assertSame($format==='xlsx'?2:1,$book->getSheetCount());
                $this->assertSame('Historic Sales',$book->getSheet(0)->getCell('A2')->getValue());
                $this->assertSame(4,(int)$book->getSheet(0)->getCell('D2')->getValue());
                if($format==='xlsx')$this->assertSame(500000.0,(float)$book->getSheet(1)->getCell('D2')->getValue());
                $book->disconnectWorksheets();unlink($file);
            }
        } finally {(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($compiled);}
    }
}
