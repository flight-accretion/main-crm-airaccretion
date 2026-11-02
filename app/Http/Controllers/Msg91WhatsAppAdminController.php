<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsApp\ManageMsg91SubscriptionRequest;
use App\Models\Msg91WhatsAppSender;
use App\Models\Msg91WhatsAppSubscription;
use App\Models\User;
use App\Services\WhatsApp\Msg91\Msg91CampaignAccess;
use Illuminate\Http\Request;

class Msg91WhatsAppAdminController extends Controller
{
    public function __construct(private Msg91CampaignAccess $access) {}
    public function index(Request $request)
    {
        abort_unless($this->access->canManage($request->user()), 403);
        $users = User::with(['userType', 'msg91CampaignSubscription', 'msg91CampaignSender'])->whereHas('userType', fn ($q) => $q->whereIn('user_type', array_merge(\App\Models\UserType::SALES_ROLES, \App\Models\UserType::ADMIN_ROLES, [\App\Models\UserType::OWNER])))->orderBy('name')->paginate(25);
        return view('admin.pages.whatsapp.admin.index', compact('users'));
    }
    public function save(ManageMsg91SubscriptionRequest $request)
    {
        abort_unless($this->access->canManage($request->user()), 403);
        $data = $request->validated();
        $subscription = Msg91WhatsAppSubscription::firstOrNew(['user_id' => $data['user_id']]);
        $subscription->fill(['status' => $data['status'], 'expires_at' => $data['expires_at'] ?? null, 'payment_reference' => $data['payment_reference'] ?? null, 'activated_by' => $request->user()->id, 'audit_meta' => ['updated_at' => now()->toIso8601String()]]);
        $subscription->save();
        if (!empty($data['integrated_number'])) {
            $sender = Msg91WhatsAppSender::firstOrNew(['user_id' => $data['user_id']]);
            $sender->fill(['integrated_number' => preg_replace('/\D+/', '', $data['integrated_number']), 'status' => $data['sender_status'] ?? 'inactive', 'verified_at' => !empty($data['verified']) ? now() : null, 'verified_by' => !empty($data['verified']) ? $request->user()->id : null]);
            $sender->save();
        }
        return back()->with('success', 'Subscription and sender mapping saved.');
    }
}
