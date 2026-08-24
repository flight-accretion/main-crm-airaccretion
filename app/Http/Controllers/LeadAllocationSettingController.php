<?php

namespace App\Http\Controllers;

use App\Models\EmailLeadProductUserAssignment;
use App\Models\LeadAllocationSetting;
use App\Models\Product;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadAllocationSettingController extends Controller
{
    /**
     * Show Lead Allocation Settings.
     */
    public function edit()
    {
        $this->ensureSuperAdmin();

        $settings = LeadAllocationSetting::getActiveSettings();

        /*
        |--------------------------------------------------------------------------
        | All active sales users
        |--------------------------------------------------------------------------
        |
        | We are NOT restricting this to Sales Managers.
        |
        | Sales Executive
        | Sales Manager
        | Senior Sales Manager
        |
        | can all have email products mapped to them.
        |
        */

        $salesUsers = User::query()
            ->with('userType')
            ->where('status', 1)
            ->whereHas('userType', function ($query) {
                $query->whereIn(
                    'user_type',
                    UserType::SALES_ROLES
                );
            })
            ->orderBy('name')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */

        $products = Product::query()
            ->where('status', 1)
            ->orderBy('product')
            ->get([
                'id',
                'product',
            ]);


        /*
        |--------------------------------------------------------------------------
        | Existing Lead Product Assignments
        |--------------------------------------------------------------------------
        |
        | Result:
        |
        | user-A => [product-1, product-2, product-3]
        | user-B => [product-1, product-4]
        |
        */

        $emailProductAssignments =
            EmailLeadProductUserAssignment::query()
                ->where('is_active', true)
                ->get()
                ->groupBy('user_id')
                ->map(function ($rows) {
                    return $rows
                        ->pluck('product_id')
                        ->map(function ($id) {
                            return (string) $id;
                        })
                        ->values()
                        ->all();
                });


        return view(
            'admin.pages.lead-allocation.settings',
            compact(
                'settings',
                'salesUsers',
                'products',
                'emailProductAssignments'
            )
        );
    }


    /**
     * Save settings.
     */
    public function update(Request $request)
    {
        $this->ensureSuperAdmin();

        $settings = LeadAllocationSetting::getActiveSettings();


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'office_start_time' => [
                'required',
                'date_format:H:i',
            ],

            'office_end_time' => [
                'required',
                'date_format:H:i',
            ],

            'allocation_method' => [
                'required',
                Rule::in([
                    'balanced',
                    'random',
                ]),
            ],

            'auto_allocation_enabled' => [
                'nullable',
                'boolean',
            ],


            /*
            |--------------------------------------------------------------------------
            | Lead Product Mapping
            |--------------------------------------------------------------------------
            */

            'email_product_assignments' => [
                'nullable',
                'array',
            ],

            'email_product_assignments.*' => [
                'nullable',
                'array',
            ],

            'email_product_assignments.*.*' => [
                'uuid',
                'exists:products,id',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Separate Lead Product Mapping
        |--------------------------------------------------------------------------
        |
        | This field does NOT belong to
        | lead_allocation_settings.
        |
        */

        $emailProductAssignments =
            $validated['email_product_assignments']
            ?? [];

        unset(
            $validated['email_product_assignments']
        );


        /*
         * Checkbox isn't submitted when unchecked.
         */
        $validated['auto_allocation_enabled'] =
            $request->boolean(
                'auto_allocation_enabled'
            );


        DB::transaction(function () use (
            $settings,
            $validated,
            $emailProductAssignments
        ) {

            /*
            |--------------------------------------------------------------------------
            | Save normal allocation settings
            |--------------------------------------------------------------------------
            */

            $settings->update(
                $validated
            );


            /*
            |--------------------------------------------------------------------------
            | Rebuild Lead Product Mapping
            |--------------------------------------------------------------------------
            */

            EmailLeadProductUserAssignment::query()
                ->delete();


            foreach (
                $emailProductAssignments
                as $userId => $productIds
            ) {

                /*
                 * Security check:
                 * user must still be active and have a sales role.
                 */

                $user = User::query()
                    ->where('id', $userId)
                    ->where('status', 1)
                    ->whereHas(
                        'userType',
                        function ($query) {
                            $query->whereIn(
                                'user_type',
                                UserType::SALES_ROLES
                            );
                        }
                    )
                    ->first();


                if (!$user) {
                    continue;
                }


                /*
                 * Remove duplicates/empty values.
                 */

                $productIds = array_values(
                    array_unique(
                        array_filter(
                            $productIds
                        )
                    )
                );


                foreach ($productIds as $productId) {

                    EmailLeadProductUserAssignment::create([
                        'user_id' => $user->id,
                        'product_id' => $productId,
                        'is_active' => true,
                    ]);

                }
            }
        });


        return redirect()
            ->route(
                'admin.lead-allocation.settings.edit'
            )
            ->with(
                'success',
                'Lead allocation and product assignments updated successfully.'
            );
    }


    /**
     * Super Admin only.
     */
    private function ensureSuperAdmin(): void
    {
        $user = auth()->user();

        if (
            !$user ||
            !$user->userType ||
            $user->userType->user_type
                !== UserType::SUPER_ADMIN
        ) {
            abort(403);
        }
    }
}
