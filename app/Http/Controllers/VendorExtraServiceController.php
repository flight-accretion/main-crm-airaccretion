<?php

namespace App\Http\Controllers;

use App\Models\ExtraService;
use App\Models\LeadVendorPaymentDetail;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VendorExtraServiceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Add Vendor Extra Service
    |--------------------------------------------------------------------------
    |
    | No customer selling amount.
    |
    */

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => [
                    'required',
                    'string',
                    'max:100',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $name = preg_replace(
            '/\s+/u',
            ' ',
            trim($request->name)
        );

        /*
        |--------------------------------------------------------------------------
        | Duplicate check ONLY inside Vendor master
        |--------------------------------------------------------------------------
        |
        | Customer and Vendor can intentionally have same wording.
        |
        */

        $existing = ExtraService::query()
            ->where(
                'usage_scope',
                ExtraService::SCOPE_VENDOR
            )
            ->whereRaw(
                'LOWER(extra_service) = ?',
                [mb_strtolower($name)]
            )
            ->first();

        if ($existing) {

            if (!$existing->status) {
                $existing->update([
                    'status' => 1,
                ]);
            }

            return response()->json([
                'success' => true,
                'existing' => true,
                'extra_service' => [
                    'id' => $existing->id,
                    'name' => $existing->extra_service,
                ],
            ]);
        }

        $extraService = ExtraService::create([
            'id' => (string) Str::uuid(),

            'extra_service' => $name,

            /*
             * Vendor-only service has no customer selling price.
             */
            'extra_service_amount' => 0,

            'description' => null,

            'usage_scope' =>
                ExtraService::SCOPE_VENDOR,

            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'existing' => false,
            'extra_service' => [
                'id' => $extraService->id,
                'name' => $extraService->extra_service,
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Vendor Extra Service
    |--------------------------------------------------------------------------
    |
    | NO edit functionality.
    |
    */

    public function destroy($id)
    {
        $extraService =
            ExtraService::query()
                ->vendorOnly()
                ->findOrFail($id);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Already used in a Voucher?
            |--------------------------------------------------------------------------
            |
            | Do not physically delete accounting history.
            |
            */

            $used =
                LeadVendorPaymentDetail::query()
                    ->where(
                        'service_id',
                        $extraService->id
                    )
                    ->where(
                        'is_extra_service',
                        true
                    )
                    ->exists();

            if ($used) {

                $extraService->update([
                    'status' => 0,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'deleted' => false,
                    'deactivated' => true,
                    'message' =>
                        'Vendor Extra Service has historical usage and was deactivated.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Remove accidental Vendor mappings if any
            |--------------------------------------------------------------------------
            */

            Vendor::query()
                ->whereNotNull('extra_service_ids')
                ->get()
                ->each(function ($vendor) use ($extraService) {

                    $ids =
                        $vendor->extra_service_ids
                        ?? [];

                    if (
                        in_array(
                            $extraService->id,
                            $ids
                        )
                    ) {

                        $vendor->extra_service_ids =
                            array_values(
                                array_filter(
                                    $ids,
                                    function ($id) use ($extraService) {
                                        return
                                            (string) $id
                                            !==
                                            (string) $extraService->id;
                                    }
                                )
                            );

                        $vendor->save();
                    }
                });

            $extraService->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'deleted' => true,
                'deactivated' => false,
                'message' =>
                    'Vendor Extra Service deleted successfully.',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to delete Vendor Extra Service.',
            ], 500);
        }
    }
}