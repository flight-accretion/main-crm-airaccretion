<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsApp\CreateMsg91CampaignRequest;
use App\Models\Msg91WhatsAppCampaign;
use App\Services\WhatsApp\Msg91\Msg91CampaignAccess;
use App\Services\WhatsApp\Msg91\Msg91CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Msg91CampaignController extends Controller
{
    public function __construct(private Msg91CampaignAccess $access, private Msg91CampaignService $campaigns) {}

    public function index(Request $request)
    {
        abort_unless($this->access->canUseModule($request->user()), 403);
        $query = Msg91WhatsAppCampaign::query()->with(['creator:id,name', 'sender:id,integrated_number', 'template:id,name,language']);
        if (!$this->access->canViewCompany($request->user())) {
            $ids = $this->access->visibleRepresentativeIds($request->user()) ?? [];
            $query->whereIn('created_by', $ids);
        }
        if ($request->filled('status')) $query->where('status', (string) $request->input('status'));
        if ($request->filled('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower((string) $request->input('search')) . '%']);
        }
        $campaigns = $query->latest()->paginate(min(100, max(10, (int) $request->input('per_page', 10))))->withQueryString();
        $canManageMsg91 = $this->access->canManage($request->user());
        return view('admin.pages.whatsapp.campaigns.index', compact('campaigns', 'canManageMsg91'));
    }

    public function show(Request $request, Msg91WhatsAppCampaign $campaign)
    {
        abort_unless($this->access->canViewCampaign($request->user(), $campaign), 403);
        $campaign->load(['creator:id,name', 'sender:id,integrated_number', 'template:id,name,language']);
        $recipients = $campaign->recipients()->with(['lead:id,client_id', 'lead.client:id,name'])->latest()->paginate(25)->withQueryString();
        return view('admin.pages.whatsapp.campaigns.show', compact('campaign', 'recipients'));
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['lead_ids' => ['required', 'array', 'min:1', 'max:5000'], 'lead_ids.*' => ['uuid']]);
        $preview = $this->campaigns->preview($request->user(), $data['lead_ids']);
        return response()->json([
            'selected_count' => $preview['selected_count'], 'eligible_count' => $preview['recipients']->count(), 'excluded' => $preview['excluded'],
            'sender' => ['masked_number' => $this->mask($preview['sender']->integrated_number)],
            'templates' => $preview['templates'],
            'sample' => data_get($preview['recipients']->first(), 'variables', []),
        ]);
    }

    public function store(CreateMsg91CampaignRequest $request)
    {
        $campaign = $this->campaigns->create(
            $request->user(),
            (string) $request->input('name'),
            (string) $request->input('template_id'),
            $request->input('lead_ids')
        );
        if ($request->expectsJson()) return response()->json(['message' => 'Campaign queued.', 'campaign_id' => $campaign->id], 201);
        return redirect()->route('admin.msg91.campaigns.show', $campaign)->with('success', 'Campaign queued safely. Delivery status will update after MSG91 reports it.');
    }

    public function cancel(Request $request, Msg91WhatsAppCampaign $campaign)
    {
        $this->campaigns->cancel($request->user(), $campaign);
        return back()->with('success', 'Queued campaign recipients were cancelled. Accepted messages remain in the delivery history.');
    }

    private function mask(string $number): string { return strlen($number) <= 4 ? '••••' : str_repeat('•', max(0, strlen($number) - 4)) . substr($number, -4); }
}
