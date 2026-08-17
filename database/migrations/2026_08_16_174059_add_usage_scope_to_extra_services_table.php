<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUsageScopeToExtraServicesTable extends Migration
{
    public function up()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            $table->string('usage_scope', 20)
                ->default('customer')
                ->index();
        });

        /*
        |--------------------------------------------------------------------------
        | Preserve historical/vendor mappings
        |--------------------------------------------------------------------------
        |
        | Existing ExtraServices referenced by Vendors or existing
        | LeadVendorPaymentDetail rows remain usable by BOTH sides.
        |
        */

        $vendorUsedIds = [];

        $decodeIds = function ($value) {

            if (is_array($value)) {
                return $value;
            }

            if (!is_string($value) || trim($value) === '') {
                return [];
            }

            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            if (is_string($decoded)) {
                $decodedAgain = json_decode($decoded, true);

                return is_array($decodedAgain)
                    ? $decodedAgain
                    : [];
            }

            return [];
        };

        /*
         * Existing Vendor.extra_service_ids mappings.
         */
        DB::table('vendors')
            ->select('extra_service_ids')
            ->whereNotNull('extra_service_ids')
            ->get()
            ->each(function ($vendor) use (&$vendorUsedIds, $decodeIds) {

                foreach ($decodeIds($vendor->extra_service_ids) as $id) {
                    if (!empty($id)) {
                        $vendorUsedIds[] = (string) $id;
                    }
                }
            });

        /*
         * Existing voucher/vendor-payment extra-service history.
         */
        $detailIds = DB::table('lead_vendor_payment_details')
            ->where('is_extra_service', true)
            ->whereNotNull('service_id')
            ->pluck('service_id')
            ->filter()
            ->map(function ($id) {
                return (string) $id;
            })
            ->all();

        $vendorUsedIds = array_values(
            array_unique(
                array_merge(
                    $vendorUsedIds,
                    $detailIds
                )
            )
        );

        if (!empty($vendorUsedIds)) {
            DB::table('extra_services')
                ->whereIn('id', $vendorUsedIds)
                ->update([
                    'usage_scope' => 'both',
                ]);
        }
    }

    public function down()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            $table->dropColumn('usage_scope');
        });
    }
}