<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\AiModelProfile;
use App\Models\LeadAiScoringSetting;
use App\Models\WhatsAppAiAgentSetting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AiAgentDesignViewTest extends TestCase
{
    public function test_overview_preserves_actions_and_escapes_prompts(): void
    {
        $profile = new AiModelProfile(['id'=>'profile-1','name'=>'Careful','provider'=>'gemini','model'=>'test-model','enabled'=>true]);
        $agents = collect([
            new AiAgent(['id'=>'agent-1','name'=>'Booking','agent_type'=>'whatsapp','prompt'=>'Confirm <script>alert(1)</script>','enabled'=>true]),
            new AiAgent(['id'=>'agent-2','name'=>'Qualifier','agent_type'=>'lead_scoring','prompt'=>'Ask dates','enabled'=>false]),
        ]);
        $profile->setRelation('agents', $agents);
        foreach ($agents as $agent) $agent->setRelation('modelProfile', $profile);

        $html = $this->renderPage($agents, collect([$profile]));

        foreach (['ai-agent-health','ai-overview','data-label="Agent Prompt"','data-label="Last run"','No recorded runs','edit-ai-agent','test-existing-ai-model','whatsapp-settings','lead-scoring-settings','1 <small>/ 2</small>','1 paused'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_empty_overview_does_not_invent_health_metrics(): void
    {
        $html = $this->renderPage(collect(), collect());
        $this->assertStringContainsString('0 <small>/ 0</small>', $html);
        $this->assertStringContainsString('No AI Agents found.', $html);
        $this->assertStringContainsString('No AI Model Profiles found.', $html);
        $this->assertStringContainsString('Tracking setup required', $html);
    }

    private function renderPage($agents, $profiles): string
    {
        $source = str_replace("@extends('admin.layouts.header')", '', file_get_contents(resource_path('views/admin/pages/whatsapp/ai-agent.blade.php')));
        view()->share('errors', new ViewErrorBag());

        return Blade::render($source."\n@yield('content')", [
            'aiAgents'=>$agents, 'aiModelProfiles'=>$profiles,
            'aiHealth'=>['available'=>false, 'handled'=>null, 'handoffs'=>null, 'failures'=>null, 'agents'=>collect()],
            'setting'=>new WhatsAppAiAgentSetting(), 'leadScoringSetting'=>new LeadAiScoringSetting(),
        ]);
    }
}
