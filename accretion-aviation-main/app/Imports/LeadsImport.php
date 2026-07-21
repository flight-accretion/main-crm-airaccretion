<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadRide;
use App\Models\LeadFollowup;
use App\Models\Country;
use App\Models\City;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class LeadsImport implements ToCollection, WithHeadingRow
{
    private $errors = [];
    private $imported = 0;
    private $skipped = 0;

    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            $rowNumber = $index + 2; // +2 because index starts at 0 and we have header row
            
            // Skip completely empty rows
            if ($this->isRowEmpty($row)) {
                continue;
            }
            
            try {
                // Manual validation for each row
                $validationErrors = $this->validateRow($row, $rowNumber);
                if (!empty($validationErrors)) {
                    // Only log errors for rows that have some meaningful data
                    $hasMinimalData = !empty(trim($row['full_name'] ?? '')) || 
                                     !empty(trim($row['email_address'] ?? '')) || 
                                     !empty(trim($row['phone_number'] ?? ''));
                    
                    if ($hasMinimalData) {
                        foreach ($validationErrors as $error) {
                            $this->errors[] = "Row {$rowNumber}: {$error}";
                        }
                        $this->skipped++;
                    }
                    continue;
                }

                DB::beginTransaction();
                $this->processRow($row, $rowNumber);
                DB::commit();
                $this->imported++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
                $this->skipped++;
                Log::error("Import error on row {$rowNumber}: " . $e->getMessage(), [
                    'row_data' => $row->toArray()
                ]);
            }
        }
    }

    /**
     * Return array of service ids associated with product ids.
     */
    private function getServicesForProducts($productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        try {
            $services = [];
            $dbType = config('database.default');

            foreach ($productIds as $productId) {
                if ($dbType === 'pgsql') {
                    // PostgreSQL JSON operators - check if the JSON array contains the product ID
                    $productServices = DB::table('services')
                        ->select('id')
                        ->whereRaw('product_ids::jsonb @> ?', [json_encode([$productId])])
                        ->pluck('id')
                        ->toArray();
                } else {
                    // MySQL JSON_CONTAINS function
                    $productServices = DB::table('services')
                        ->select('id')
                        ->whereRaw('JSON_CONTAINS(product_ids, ?)', ['"' . $productId . '"'])
                        ->pluck('id')
                        ->toArray();
                }

                // Fallback: if no services found with JSON operators, try text search
                if (empty($productServices)) {
                    $productServices = DB::table('services')
                        ->select('id')
                        ->where('product_ids', 'like', '%"' . $productId . '"%')
                        ->pluck('id')
                        ->toArray();
                }

                $services = array_merge($services, $productServices);
            }

            return array_unique($services);
        } catch (\Exception $e) {
            // If any error occurs, return empty array (no services)
            Log::warning('Failed to fetch services for products ' . implode(',', $productIds) . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get product names for given product IDs
     */
    private function getProductNames($productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        
        try {
            return Product::whereIn('id', $productIds)->pluck('product')->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to fetch product names: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return array of service ids associated with a product id (if any).
     * @deprecated Use getServicesForProducts instead
     */
    private function getServicesForProduct($productId)
    {
        return $this->getServicesForProducts([$productId]);
    }

    /**
     * Check if a row is completely empty or contains only whitespace
     */
    private function isRowEmpty($row)
    {
        // Convert the row to array and check if all values are empty
        $rowArray = $row->toArray();
        
        foreach ($rowArray as $value) {
            if (!empty(trim($value))) {
                return false;
            }
        }
        
        return true;
    }

    private function validateRow($row, $rowNumber)
    {
        $errors = [];
        
        // Required fields validation
        if (empty(trim($row['full_name'] ?? ''))) {
            $errors[] = "Full Name is required";
        }
        
        // if (empty(trim($row['email_address'] ?? ''))) {
        //     $errors[] = "Email Address is required";
        // } elseif (!filter_var(trim($row['email_address']), FILTER_VALIDATE_EMAIL)) {
        //     $errors[] = "Invalid Email Address format";
        // }
        
        if (empty(trim($row['phone_number'] ?? ''))) {
            $errors[] = "Phone Number is required";
        }
        
        // Country is now optional - removed validation
        // if (empty(trim($row['country'] ?? ''))) {
        //     $errors[] = "Country is required";
        // }
        
        // if (empty(trim($row['service'] ?? ''))) {
        //     $errors[] = "Service is required";
        // }
        
        // Product validation - make it optional, support multiple products
        if (!empty(trim($row['product'] ?? ''))) {
            $productNames = array_map('trim', preg_split('/[,;]/', trim($row['product'])));
            foreach ($productNames as $productName) {
                if (!empty($productName)) {
                    $product = Product::where('product', 'LIKE', '%' . $productName . '%')->first();
                    if (!$product) {
                        $errors[] = "Product '{$productName}' not found";
                    } else {
                        // Check if the product has any services mapped to it
                        $productServices = $this->getServicesForProducts([$product->id]);
                        if (empty($productServices)) {
                            $errors[] = "Product '{$productName}' has no services mapped to it";
                        }
                    }
                }
            }
        }
        
        // if (empty(trim($row['staff_representative'] ?? ''))) {
        //     $errors[] = "Staff Representative is required";
        // }
        
        if (empty($row['number_of_passengers'] ?? '')) {
            $errors[] = "Number of Passengers is required";
        } elseif (!is_numeric($row['number_of_passengers']) || (int)$row['number_of_passengers'] < 1) {
            $errors[] = "Number of Passengers must be a positive number";
        }

        if (isset($row['from_date']) && !empty($row['from_date'])) {
            try {
                Carbon::parse($row['from_date']);
            } catch (\Exception $e) {
                $errors[] = "Invalid From Date format in row {$rowNumber}";
            }
        }
        
        if (isset($row['to_date']) && !empty($row['to_date'])) {
            try {
                Carbon::parse($row['to_date']);
            } catch (\Exception $e) {
                $errors[] = "Invalid To Date format in row {$rowNumber}";
            }
        }
        if (isset($row['from_place']) && empty(trim($row['from_place'] ?? ''))) {
            $errors[] = "From Place is required";
        }

        if (isset($row['to_place']) && empty(trim($row['to_place'] ?? ''))) {
            $errors[] = "To Place is required";
        }
        return $errors;
    }

    private function processRow($row, $rowNumber)
    {
        // Parse phone numbers to remove country codes for validation
        $contactNumber = $this->parsePhoneNumber($row['phone_number'] ?? '');
        $whatsappNumber = $this->parsePhoneNumber($row['whatsapp_number'] ?? '');
        
        // Find or create country (only if provided)
        $country = null;
        if (!empty(trim($row['country'] ?? ''))) {
            $country = Country::where('name', 'LIKE', '%' . trim($row['country']) . '%')->first();
            if (!$country) {
                throw new \Exception("Country '{$row['country']}' not found");
            }
        }

        // Find or create city (only if country is provided)
        $city = null;
        if (!empty($row['city']) && $country) {
            $city = City::where('name', 'LIKE', '%' . trim($row['city']) . '%')
                       ->where('country_id', $country->id)
                       ->first();
            
            // if (!$city) {
            //     try {
            //         // Try to create new city if not found
            //         $city = City::create([
            //             'id' => Str::uuid(),
            //             'name' => trim($row['city']),
            //             'country_id' => $country->id,
            //             'status' => 1
            //         ]);
            //     } catch (\Exception $e) {
            //         // If city creation fails, continue without city
            //         Log::warning("Could not create city '{$row['city']}' for country '{$country->name}': " . $e->getMessage());
            //         $city = null;
            //     }
            // }
        }

        // Find service
        $service = Service::where('service', 'LIKE', '%' . trim($row['service'] ?? '') . '%')->first();
        if (!$service) {
            throw new \Exception("Service '{$row['service']}' not found");
        }

        // Find product(s) (optional, support multiple products)
        $productIds = [];
        if (!empty(trim($row['product'] ?? ''))) {
            $productNames = array_map('trim', preg_split('/[,;]/', trim($row['product'])));
            foreach ($productNames as $productName) {
                if (!empty($productName)) {
                    $product = Product::where('product', 'LIKE', '%' . $productName . '%')->first();
                    if ($product) {
                        // Check if the product has any services mapped to it
                        $productServices = $this->getServicesForProducts([$product->id]);
                        if (empty($productServices)) {
                            throw new \Exception("Product '{$productName}' has no services mapped to it. Please map services to this product before importing.");
                        }
                        $productIds[] = $product->id;
                    }
                }
            }
            // Remove duplicates
            $productIds = array_unique($productIds);
        }

        // Find staff representative
        $staff = User::where('name', 'LIKE', '%' . trim($row['staff_representative'] ?? '') . '%')->first();
        if (!$staff) {
            throw new \Exception("Staff representative '{$row['staff_representative']}' not found");
        }

        // Check if client already exists
        $client = Client::where('email', trim($row['email_address']))->first();
        
        if (!$client) {
            // Create new client
            $client = Client::create([
                'id' => Str::uuid(),
                'name' => trim($row['full_name']),
                'email' => trim($row['email_address']),
                'contact_number' => $contactNumber['formatted'],
                'alternate_number' => $whatsappNumber['formatted'] ?: null,
                'date_of_birth' => $this->parseDate($row['date_of_birth'] ?? null),
                'address' => trim($row['address'] ?? ''),
                'city_id' => $city ? $city->id : null,
                'country_id' => $country ? $country->id : null,
                'status' => 1,
                'created_by' => auth()->id(),
            ]);
        }

        // Create lead
        $serviceIds = [$service->id];
        
        // Add services associated with selected products and validate consistency
        if (!empty($productIds)) {
            $productServices = $this->getServicesForProducts($productIds);
            
            // Optional: Check if the selected service is compatible with the selected products
            // This ensures that the service specified in the Excel row is actually associated with the products
            $isServiceCompatible = in_array($service->id, $productServices);
            if (!$isServiceCompatible) {
                $productNames = $this->getProductNames($productIds);
                Log::warning("Service '{$service->service}' is not mapped to the selected products (" . implode(', ', $productNames) . "), but proceeding with import", [
                    'service_id' => $service->id,
                    'service_name' => $service->service,
                    'product_ids' => $productIds,
                    'product_names' => $productNames,
                    'product_services' => $productServices
                ]);
            }
            
            $serviceIds = array_unique(array_merge($serviceIds, $productServices));
        }

        $lead = Lead::create([
            'id' => Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $staff->id,
            'service_ids' => json_encode($serviceIds),
            'product_ids' => !empty($productIds) ? json_encode($productIds) : null,
            'number_of_passengers' => (int)($row['number_of_passengers'] ?? 1),
            'description' => "Imported lead from Excel",
        ]);

        // Create trip segment
        $fromDate = $this->parseDateTime($row['from_date'] ?? null);
        $toDate = $this->parseDateTime($row['to_date'] ?? null);
        
        // Create a ride segment if at least a fromDate exists. If toDate missing, use fromDate as fallback so the lead is considered to have a ride segment.
        if ($fromDate) {
            $computedToDate = $toDate ?: $fromDate;
            LeadRide::create([
                'id' => Str::uuid(),
                'lead_id' => $lead->id,
                'from_date' => $fromDate,
                'to_date' => $computedToDate,
                'from_place' => trim($row['from_place'] ?? ''),
                'to_place' => trim($row['to_place'] ?? ''),
            ]);
        }

        // Create follow-up
        $nextFollowUp = $this->parseDateTime($row['next_follow_up'] ?? null);
        if (!$nextFollowUp) {
            $nextFollowUp = now()->addDays(7)->format('Y-m-d H:i:s');
        }

        LeadFollowup::create([
            'id' => Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => $nextFollowUp,
            'followup_note' => 'Lead imported from Excel file',
            'followed_by' => auth()->id(),
            'status' => '1',
        ]);
    }

    private function parsePhoneNumber($phoneNumber)
    {
        if (empty($phoneNumber)) {
            return ['formatted' => null, 'number' => null, 'code' => null];
        }

        // Trim and normalize common unicode hyphens and invisible chars
        $s = trim($phoneNumber);
        $s = preg_replace('/[\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2212}]/u', '-', $s);
        // Remove all non-digit and non-plus characters (Unicode-aware)
        $clean = preg_replace('/[^\p{N}\+]/u', '', $s);

        // If it starts with +, extract country code (1-3 digits) and the rest as number
        if (str_starts_with($clean, '+')) {
            if (preg_match('/^\+(\d{1,3})(\d{4,15})$/u', $clean, $m)) {
                $countryCode = '+' . $m[1];
                $number = $m[2];
                return [
                    'formatted' => $countryCode . '-' . $number,
                    'number' => $number,
                    'code' => $countryCode
                ];
            }
        } else {
            // No leading +, assume local number — apply default country code +91
            if (preg_match('/^(\d{4,15})$/u', $clean, $m)) {
                $number = $m[1];
                return [
                    'formatted' => '+91-' . $number,
                    'number' => $number,
                    'code' => '+91'
                ];
            }
        }

        // Last resort: strip everything except digits, then apply default +91
        $digits = preg_replace('/[^\d]/', '', $phoneNumber);
        if (!empty($digits)) {
            return [
                'formatted' => '+91-' . $digits,
                'number' => $digits,
                'code' => '+91'
            ];
        }

        return ['formatted' => null, 'number' => null, 'code' => null];
    }

    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            // If it's already a DateTime (PhpSpreadsheet may return a DateTime object)
            $appTz = config('app.timezone') ?: date_default_timezone_get();
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->setTimezone($appTz)->format('Y-m-d');
            }

            // If Excel stores it as a numeric serial (eg. 45000...), convert it
            if (is_numeric($date)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $date);
                    // Treat the Excel date/time as local (app) time to avoid timezone shifts.
                    $appTz = config('app.timezone') ?: date_default_timezone_get();
                    $local = Carbon::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), $appTz);
                    return $local->format('Y-m-d');
                } catch (\Exception $e) {
                    // fall-through to string parsing
                }
            }

            // Try a few common formats first (day-first formats are common in the sheet)
            $formats = [
                'd-m-Y H:i', 'd-m-Y H:i:s', 'd-m-Y',
                'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d',
                'd/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y',
                'm/d/Y H:i', 'm/d/Y',
            ];

            foreach ($formats as $fmt) {
                try {
                    $c = Carbon::createFromFormat($fmt, trim((string)$date));
                    if ($c !== false) {
                        return $c->setTimezone($appTz)->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    // try next format
                }
            }

            // Last resort: let Carbon try to parse intelligently
            return Carbon::parse((string)$date)->setTimezone($appTz)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDateTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            // If it's already a DateTime object
            $appTz = config('app.timezone') ?: date_default_timezone_get();
            if ($datetime instanceof \DateTimeInterface) {
                return Carbon::instance($datetime)->setTimezone($appTz)->format('Y-m-d H:i:s');
            }

            // If Excel provided a numeric serial date, convert it
            if (is_numeric($datetime)) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject((float) $datetime);
                    // Treat Excel date/time as local (app) time to avoid timezone shifts
                    $appTz = config('app.timezone') ?: date_default_timezone_get();
                    $local = Carbon::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d H:i:s'), $appTz);
                    return $local->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // fall-through to string parsing
                }
            }

            // Try common formats (day-first then ISO)
            $formats = [
                'd-m-Y H:i', 'd-m-Y H:i:s', 'd-m-Y',
                'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d',
                'd/m/Y H:i', 'd/m/Y H:i:s',
                'm/d/Y H:i', 'm/d/Y',
            ];

            foreach ($formats as $fmt) {
                try {
                    $c = Carbon::createFromFormat($fmt, trim((string)$datetime));
                    if ($c !== false) {
                        // If the parsed value had no time, ensure time is 00:00:00
                        return $c->setTimezone($appTz)->format('Y-m-d H:i:s');
                    }
                } catch (\Exception $e) {
                    // try next
                }
            }

            // Last attempt: flexible parsing
            return Carbon::parse((string)$datetime)->setTimezone($appTz)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getImported()
    {
        return $this->imported;
    }

    public function getSkipped()
    {
        return $this->skipped;
    }
}
