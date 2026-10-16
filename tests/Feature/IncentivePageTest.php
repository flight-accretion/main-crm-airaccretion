<?php

namespace Tests\Feature;

use App\Http\Controllers\TargetController;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetIncentiveService;
use Illuminate\Http\Request;

class IncentivePageTest extends TargetIncentiveReportTest
{
    private function page(array $input = [], string $role = 'hr')
    {
        $actor=User::findOrFail($role);auth()->setUser($actor);
        $request=Request::create('/admin/targets/incentives','GET',$input+['year'=>2026,'month'=>10]);
        $request->setUserResolver(fn()=>$actor);$request->setLaravelSession(app('session.store'));app()->instance('request',$request);
        return (new TargetController(new TargetIncentiveService()))->incentives($request);
    }

    public function test_page_separates_target_achievement_and_incentive_without_writes(): void
    {
        $before=Target::orderBy('id')->pluck('achieved_amount','id')->all();
        $data=$this->page()->getData();$rows=collect($data['rows'])->keyBy('name');
        $this->assertSame(100000.0,$rows['Manager Person']['target']);
        $this->assertSame(500.0,$rows['Manager Person']['achieved']);
        $this->assertSame(0.5,$rows['Manager Person']['achievement']);
        $this->assertSame(5.0,$rows['Manager Person']['incentive']);
        $this->assertSame(800.0,$rows['=Sales Person']['achieved']);
        $this->assertSame(1.6,$rows['=Sales Person']['incentive']);
        $this->assertSame(0.0,$rows['Operations Person']['achieved']);
        $this->assertSame(1.6,$rows['Operations Person']['incentive']);
        $this->assertSame(null,$rows['Unconfigured Person']['target']);
        $this->assertSame(null,$rows['Unconfigured Person']['incentive']);
        $this->assertSame($before,Target::orderBy('id')->pluck('achieved_amount','id')->all());
    }

    public function test_page_is_restricted_to_hr_and_super_admin(): void
    {
        foreach(['hr','super'] as $role)$this->assertSame('admin.targets.incentives',$this->page([],$role)->name());
        foreach(['admin','manager','own','operations'] as $role){
            $status=0;try{$this->page([],$role);}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$status=$e->getStatusCode();}
            $this->assertSame(403,$status);
        }
    }

    public function test_employee_month_search_and_pagination_filters_work(): void
    {
        $id=User::where('name','Manager Person')->value('id');
        $this->assertSame(1,count($this->page(['person_id'=>$id])->getData()['rows']));
        $this->assertSame(1,count($this->page(['search'=>'Manager Person'])->getData()['rows']));
        $empty=collect($this->page(['month'=>9,'person_id'=>$id])->getData()['rows'])->first();
        $this->assertSame(null,$empty['target']);$this->assertSame(0.0,$empty['achieved']);
        $this->assertSame(10,count($this->page(['per_page'=>10])->getData()['rows']));
        $bad=false;try{$this->page(['year'=>2027]);}catch(\Illuminate\Validation\ValidationException $e){$bad=true;}$this->assertSame(true,$bad);
    }

    public function test_download_controls_move_from_target_view_to_incentive_page(): void
    {
        $old=file_get_contents(resource_path('views/admin/targets/index.blade.php'));
        $this->assertSame(false,str_contains($old,'data-open="target-export"'));
        $this->assertSame(false,str_contains($old,'id="target-export"'));
        $data=$this->page()->getData();$data['errors']=new \Illuminate\Support\ViewErrorBag();
        $source=str_replace(["@extends('admin.layouts.header')","@section('content')","@endsection"],'',file_get_contents(resource_path('views/admin/targets/incentives.blade.php')));
        $html=\Illuminate\Support\Facades\Blade::render($source,$data);
        foreach(['Target Amount','Achieved Sales Amount','Achievement (%)','Earned Incentive','data-download-person','id="target-export"'] as $text)$this->assertSame(true,str_contains($html,$text));
        $this->assertSame(false,str_contains($html,'@selected'));
        $dir=getenv('CRM_INCENTIVE_PAGE_DIR');
        if($dir)file_put_contents($dir.'/incentives.html','<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#f4f5f7;font:14px Arial}.crm-targets{max-width:1440px;margin:auto}</style></head><body>'.$html.'</body></html>');
    }

    public function test_target_view_editor_and_table_still_render_without_export_controls(): void
    {
        $actor=User::findOrFail('hr');auth()->setUser($actor);
        $request=Request::create('/admin/targets','GET',['year'=>2026,'month'=>10]);$request->setUserResolver(fn()=>$actor);
        $request->setLaravelSession(app('session.store'));app()->instance('request',$request);
        $data=(new TargetController(new TargetIncentiveService()))->index($request)->getData();
        $data['errors']=new \Illuminate\Support\ViewErrorBag();
        $source=str_replace(["@extends('admin.layouts.header')","@section('content')","@endsection"],'',file_get_contents(resource_path('views/admin/targets/index.blade.php')));
        $html=\Illuminate\Support\Facades\Blade::render($source,$data);
        $this->assertSame(true,str_contains($html,'id="target-editor"'));
        $this->assertSame(true,str_contains($html,'id="target-search"'));
        $this->assertSame(false,str_contains($html,'id="target-export"'));
        $this->assertSame(false,str_contains($html,'Download Incentive'));
        $dir=getenv('CRM_INCENTIVE_PAGE_DIR');if($dir)file_put_contents($dir.'/targets.html','<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#f4f5f7;font:14px Arial}</style></head><body>'.$html.'</body></html>');
    }
}
