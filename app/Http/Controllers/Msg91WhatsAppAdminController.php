<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsApp\ManageMsg91SubscriptionRequest;
use App\Models\Msg91WhatsAppSender;
use App\Models\User;
use App\Services\WhatsApp\Msg91\Msg91CampaignAccess;
use Illuminate\Http\Request;

class Msg91WhatsAppAdminController extends Controller
{
    public function __construct(
        private Msg91CampaignAccess $access
    ) {}


    public function index(Request $request)
    {
        abort_unless(
            $this->access->canManage(
                $request->user()
            ),
            403
        );


        $users = User::with([
                'userType',
                'msg91CampaignSender',
            ])
            ->where('status', 1)
            ->whereHas(
                'userType',
                fn ($q) =>
                    $q->whereIn(
                        'user_type',
                        array_merge(
                            \App\Models\UserType::SALES_ROLES,
                            \App\Models\UserType::ADMIN_ROLES,
                            [
                                \App\Models\UserType::OWNER,
                            ]
                        )
                    )
            )
            ->orderBy('name')
            ->paginate(25);


        return view(
            'admin.pages.whatsapp.admin.index',
            compact('users')
        );
    }


    public function save(
        ManageMsg91SubscriptionRequest $request
    ) {
        abort_unless(
            $this->access->canManage(
                $request->user()
            ),
            403
        );


        $data =
            $request->validated();

        abort_unless(
            User::query()
                ->where('status', 1)
                ->whereKey($data['user_id'])
                ->exists(),
            422,
            'Sender access can only be managed for an active CRM user.'
        );


        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | MSG91 wallet, billing, and account validity are managed in MSG91.
        | This action manages CRM sender access only.
        |
        */


        $sender =
            Msg91WhatsAppSender::firstOrNew(
                [
                    'user_id' =>
                        $data['user_id'],
                ]
            );


        $sender->fill(
            [
                /*
                 * Store digits only.
                 */
                'integrated_number' =>
                    !empty(
                        $data[
                            'integrated_number'
                        ]
                    )
                        ? preg_replace(
                            '/\D+/',
                            '',
                            $data[
                                'integrated_number'
                            ]
                        )
                        : null,


                /*
                 * CRM sender access only.
                 */
                'status' =>
                    $data[
                        'sender_status'
                    ]
                    ??
                    'inactive',
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | KEEP EXISTING VERIFICATION
        |--------------------------------------------------------------------------
        |
        | Do not reset verified_at / verified_by when Admin changes
        | status or integrated number.
        |
        | Verification should remain controlled by your existing
        | verification/onboarding flow.
        |
        */


        $sender->save();


        return back()
            ->with(
                'success',
                'WhatsApp sender access updated successfully.'
            );
    }
}
