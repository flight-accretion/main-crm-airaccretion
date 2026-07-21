<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SendMessageController extends Controller
{
    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via Interakt
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsAppMessage($type, $template, $data, $whatsAppNumber, $filePath = null)
    {
        // Split number safely
        $whatsAppNumber = explode('-', (string) $whatsAppNumber);
        $code  = $whatsAppNumber[0] ?? '+91';
        $phone = $whatsAppNumber[1] ?? $whatsAppNumber[0];

        $url   = "https://api.interakt.ai/v1/public/message/";
        $token = "dnhrUkcxNk5UWU9TWGRDRkRMbjZNVkkzVTI4UklfOTdFUGdBQW5YWUV3QTo=";

        $template = $this->sanitizeWhatsAppField($template);

        if (!is_array($data)) {
            $data = [$data];
        }

        $sanitizedData = [];
        foreach ($data as $v) {
            $sanitizedData[] = $this->sanitizeWhatsAppField($v);
        }

        $payload = [
            "countryCode"  => $this->sanitizeWhatsAppField($code),
            "phoneNumber"  => $this->sanitizeWhatsAppField($phone),
            "callbackData" => "some text here",
            "type"         => "Template",
            "template"     => [
                "name"         => $template,
                "languageCode" => "en",
                "bodyValues"   => $sanitizedData,
            ]
        ];

        if ($type == 2 && $filePath) {
            $payload["template"]["headerValues"] = [$filePath];

            $parsedPath = parse_url($filePath, PHP_URL_PATH);
            $baseName   = $parsedPath ? basename($parsedPath) : basename($filePath);

            if (strlen($baseName) > 100) {
                $ext        = pathinfo($baseName, PATHINFO_EXTENSION);
                $nameOnly   = pathinfo($baseName, PATHINFO_FILENAME);
                $maxNameLen = 100 - ($ext ? strlen($ext) + 1 : 0);
                $baseName   = substr($nameOnly, 0, $maxNameLen) . ($ext ? '.' . $ext : '');
            }

            $payload["template"]["fileName"] = $baseName;
        }

        if ($type == 3) {
            try {
                $payload["template"]["buttonValues"] = ["1" => [$filePath]];
            } catch (\Exception $e) {
                $payload["template"]["buttonValues"] = [$filePath];
            }
        }

        $post_data = json_encode($payload);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $post_data,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "Authorization: Basic {$token}",
            ],
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('SENDING WHATSAPP MESSAGE ERROR: ' . $err);
            return ['error' => 'Unable to send message', 'details' => $err];
        }

        return json_decode($response, true);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MSG91 Email
    // ═════════════════════════════════════════════════════════════════════════
    public function sendMsg91Email($templateId, $toEmail, $toName, array $variables)
    {
        $url     = "https://control.msg91.com/api/v5/email/send";
        $authKey = "auth_key";
        $payload = [
            "recipients" => [
                [
                    "to"        => [["email" => $toEmail, "name" => $toName]],
                    "variables" => $variables,
                ]
            ],
            "from"        => [
                "email" => "confirm@accretionaviation.com",
                "name"  => "Accretion Aviation",
            ],
            "domain"      => "accretion.in",
            "template_id" => $templateId,
        ];

        Log::info('MSG91 EMAIL: sending', [
            'template' => $templateId,
            'to'       => $toEmail,
            'vars'     => $variables,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "authkey: {$authKey}",
            ],
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('MSG91 EMAIL ERROR: ' . $err);
            return ['error' => 'Unable to send email', 'details' => $err];
        }

        $result = json_decode($response, true);
        Log::info('MSG91 EMAIL RESPONSE', [
            'template' => $templateId,
            'to'       => $toEmail,
            'result'   => $result,
        ]);
        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MSG91 SMS
    // ═════════════════════════════════════════════════════════════════════════
    public function sendSmsMessage($template, $data, $phoneNumber)
    {
        $phoneNumber = str_replace(['+', '-', ' '], '', $phoneNumber);

        $url     = "https://control.msg91.com/api/v5/flow";
        $authKey = "auth_key";

        $payload = [
            "template_id" => $template,
            "recipients"  => [
                array_merge(
                    ['mobiles' => $phoneNumber],
                    $data
                )
            ]
        ];

        $post_data = json_encode($payload);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $post_data,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                "accept: application/json",
                "content-type: application/json",
                "authkey: {$authKey}",
            ],
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('SENDING MSG91 MESSAGE ERROR: ' . $err);
            return ['error' => 'Unable to send message', 'details' => $err];
        }

        return json_decode($response, true);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via Meta Cloud API (graph.facebook.com)
    // Template  : sales_update
    // Header    : {{1}} → salesperson_name
    // Body      : {{1}} sales_completed  {{2}} monthly_target
    //             {{3}} followups_today  {{4}} sale_to_achieve
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsAppMetaMessage(
        string $toNumber,           // recipient phone with country code, no '+' e.g. "918411026436"
        string $salespersonName,    // header {{1}}
        string $salesCompleted,     // body {{1}}
        string $monthlyTarget,      // body {{2}}
        string $followupsToday,     // body {{3}}
        string $saleToAchieve       // body {{4}}
    ) {
        $phoneNumberId = config('services.meta_whatsapp.phone_number_id'); // META_WHATSAPP_PHONE_NUMBER_ID
        $token         = config('services.meta_whatsapp.token');            // META_WHATSAPP_TOKEN
        $url           = "https://graph.facebook.com/v25.0/{$phoneNumberId}/messages";

        // Clean the recipient number — remove +, spaces, dashes
        $toNumber = preg_replace('/[^0-9]/', '', $toNumber);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $toNumber,
            'type'              => 'template',
            'template'          => [
                'name'     => 'sales_update',
                'language' => ['code' => 'en'],
                'components' => [
                    // Header — salesperson name
                    [
                        'type'       => 'header',
                        'parameters' => [
                            ['type' => 'text', 'text' => $salespersonName],
                        ],
                    ],
                    // Body — 4 variables
                    [
                        'type'       => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $salesCompleted],
                            ['type' => 'text', 'text' => $monthlyTarget],
                            ['type' => 'text', 'text' => $followupsToday],
                            ['type' => 'text', 'text' => $saleToAchieve],
                        ],
                    ],
                ],
            ],
        ];

        Log::info('META WHATSAPP: Sending message', [
            'to'              => $toNumber,
            'salesperson'     => $salespersonName,
            'sales_completed' => $salesCompleted,
            'monthly_target'  => $monthlyTarget,
            'followups_today' => $followupsToday,
            'sale_to_achieve' => $saleToAchieve,
            'payload'         => $payload,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$token}",
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('META WHATSAPP: cURL error', [
                'to'    => $toNumber,
                'error' => $err,
            ]);
            return ['success' => false, 'error' => $err];
        }

        $result = json_decode($response, true);

        if ($httpCode === 200 && isset($result['messages'][0]['id'])) {
            Log::info('META WHATSAPP: Message sent successfully', [
                'to'         => $toNumber,
                'message_id' => $result['messages'][0]['id'],
                'http_code'  => $httpCode,
            ]);
        } else {
            Log::error('META WHATSAPP: Failed to send message', [
                'to'        => $toNumber,
                'http_code' => $httpCode,
                'response'  => $result,
            ]);
        }

        return $result;
    }



    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via MSG91 Bulk API
    // Template  : vendor_payment
    // Header    : document (receipt file)
    // Body      : {{1}} vendor_name  {{2}} customer_name  {{3}} service
    //             {{4}} service_date {{5}} amount_paid    {{6}} payment_date
    //             {{7}} payment_method
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsAppMsg91Message(
        string $toNumber,
        string $templateName,
        array  $bodyValues,      // [vendor_name, customer_name, service, service_date, amount_paid, payment_date, payment_method]
        ?string $documentUrl = null,
        ?string $documentFilename = null
    ) {
        $authKey          = config('services.msg91.auth_key');
        $integratedNumber = config('services.msg91.whatsapp_integrated');
        $url              = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';

        // Clean the recipient number — remove +, spaces, dashes
        $toNumber = preg_replace('/[^0-9]/', '', $toNumber);

        // Build components mapping
        $components = [];

        // Header — document (receipt file)
        if ($documentUrl) {
            $components['header_1'] = [
                'type'     => 'document',
                'value'    => $documentUrl,
                'filename' => $documentFilename ?? 'receipt.pdf',
            ];
        }

        // Body variables — map array values to body_1 through body_N
        foreach ($bodyValues as $index => $value) {
            $key = 'body_' . ($index + 1);
            $components[$key] = [
                'type'  => 'text',
                'value' => (string) ($value ?? ''),
            ];
        }

        $payload = [
            'integrated_number' => $integratedNumber,
            'content_type'      => 'template',
            'payload'           => [
                'messaging_product' => 'whatsapp',
                'type'              => 'template',
                'template'          => [
                    'name'      => $templateName,
                    'language'  => [
                        'code'   => 'en',
                        'policy' => 'deterministic',
                    ],
                    'namespace' => 'b22ea365_3143_4998_bdd7_a14f73bcef15',
                    'to_and_components' => [
                        [
                            'to'         => [$toNumber],
                            'components' => $components,
                        ],
                    ],
                ],
            ],
        ];

        Log::info('MSG91 WHATSAPP: Sending ' . $templateName, [
            'to'      => $toNumber,
            'payload' => $payload,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "authkey: {$authKey}",
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('MSG91 WHATSAPP: cURL error', [
                'to'    => $toNumber,
                'error' => $err,
            ]);
            return ['success' => false, 'error' => $err];
        }

        $result = json_decode($response, true);

        Log::info('MSG91 WHATSAPP: Response', [
            'to'        => $toNumber,
            'http_code' => $httpCode,
            'response'  => $result,
        ]);

        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via WhatsCRM (web.airaccretion.com — Templet API)
    // vendor_payment     → PDF  (document header)
    // vendor_payment_img → Image (JPG/PNG header)
    // exampleArr: [vendor_name, customer_name, service, service_date,
    //              amount_paid, payment_date, payment_method]
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsCrmMessage(
        string  $toNumber,
        array   $bodyValues,
        ?string $fileUrl  = null,
        ?string $filename = null
    ) {
        $apiUrl   = config('services.whatscrm.api_url');   // WHATSCRM_API_URL
        $apiToken = config('services.whatscrm.api_token'); // WHATSCRM_API_TOKEN

        // Digits only with country code e.g. 919405059038
        //$toNumber = preg_replace('/[^0-9]/', '', $toNumber);
        // WhatsCRM expects + prefix e.g. +919405059038
        $toNumber = '+' . preg_replace('/[^0-9]/', '', $toNumber);

        // Pick template based on file extension (auto-detection for vendor payments)
        $ext      = strtolower(pathinfo($filename ?? '', PATHINFO_EXTENSION));
        $isImage  = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $isPdf    = ($ext === 'pdf');
        $template = $isImage ? 'vendor_payment_img' : 'vendor_payment';

        // WhatsCRM Templet API payload — matches their exact API format
        $payload = [
            'sendTo'      => $toNumber,
            'templetName' => $template,
            'exampleArr'  => array_map('strval', $bodyValues),
            'token'       => $apiToken,
            'mediaUri'    => $fileUrl ?? '',
        ];

        // Validate PDF URL is reachable before sending (18:28 test logic)
        if ($isPdf && $fileUrl) {
            $curlCheck = curl_init();
            curl_setopt_array($curlCheck, [
                CURLOPT_URL            => $fileUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'HEAD',
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ]);
            curl_exec($curlCheck);
            $httpCodeCheck = curl_getinfo($curlCheck, CURLINFO_HTTP_CODE);
            curl_close($curlCheck);

            if ($httpCodeCheck !== 200) {
                Log::warning('WHATSCRM ► PDF URL UNREACHABLE', [
                    'To'       => $toNumber,
                    'URL'      => $fileUrl,
                    'Headers'  => "HTTP/1.1 {$httpCodeCheck}",
                    'Fallback' => 'Sending without PDF attachment',
                ]);
            }
        }

        Log::info('WHATSCRM ► SENDING', [
            'Template' => $template,
            'To'       => $toNumber,
            'File'     => $fileUrl ?? 'none',
            'FileType' => $isImage ? 'IMAGE' : 'PDF',
            'Vendor'   => $bodyValues[0] ?? '',
            'Customer' => $bodyValues[1] ?? '',
            'Service'  => $bodyValues[2] ?? '',
            'Amount'   => $bodyValues[4] ?? '',
            'Date'     => $bodyValues[5] ?? '',
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiToken}",
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('WHATSCRM: cURL error', ['to' => $toNumber, 'error' => $err]);
            return ['success' => false, 'error' => $err];
        }

        $result = json_decode($response, true);
        // Log::info('WHATSCRM: Response', [
        //     'to'        => $toNumber,
        //     'template'  => $template,
        //     'http_code' => $httpCode,
        //     'response'  => $result,
        // ]);

        // 18:28 test logic: PDFs show warning status
        if ($isPdf) {
            $statusMsg = ($result['success'] ?? false) ? '⚠ ACCEPTED (PDF sent but may not be delivered)' : '✗ FAILED';
            $details   = 'PDF Messages: WhatsCRM accepts but may not deliver. Monitor delivery.';
        } else {
            $statusMsg = ($result['success'] ?? false) ? '✓ SUCCESS' : '✗ FAILED';
            $details   = $result['metaResponse']['messages'][0]['message_status'] ?? 'unknown';
        }

        Log::info('WHATSCRM ► RESULT', [
            'Status'   => $statusMsg,
            'Template' => $template,
            'FileType' => $isImage ? 'IMAGE' : 'PDF',
            'To'       => $toNumber,
            'HTTP'     => $httpCode,
            'MsgID'    => $result['metaResponse']['messages'][0]['id'] ?? 'N/A',
            'Details'  => $details,
        ]);

        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via WhatsCRM (SEPARATE ACCOUNT FOR VOUCHERS)
    // Dedicated method for voucher sending with separate business credentials
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsCrmVoucherMessage(
        string  $toNumber,
        array   $bodyValues,
        ?string $fileUrl  = null,
        ?string $filename = null,
        ?string $templateName = 'customer_whatsapp_msg_ke'
    ) {
        $apiUrl           = config('services.whatscrm_vouchers.api_url');            // WHATSCRM_VOUCHERS_API_URL
        $apiToken         = config('services.whatscrm_vouchers.api_token');          // WHATSCRM_VOUCHERS_API_TOKEN
        $businessId       = config('services.whatscrm_vouchers.business_id');        // WHATSCRM_VOUCHERS_BUSINESS_ID
        $whatsappPhoneId  = config('services.whatscrm_vouchers.whatsapp_phone_id');  // WHATSCRM_VOUCHERS_WHATSAPP_PHONE_ID
        $appId            = config('services.whatscrm_vouchers.app_id');             // WHATSCRM_VOUCHERS_APP_ID

        // WhatsCRM expects + prefix e.g. +919405059038
        $toNumber = '+' . preg_replace('/[^0-9]/', '', $toNumber);

        // Detect file type
        $ext      = strtolower(pathinfo($filename ?? '', PATHINFO_EXTENSION));
        $isImage  = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $isPdf    = ($ext === 'pdf');

        // WhatsCRM Templet API payload — matches their exact API format
        $payload = [
            'sendTo'      => $toNumber,
            'templetName' => $templateName,
            'exampleArr'  => array_map('strval', $bodyValues),
            'token'       => $apiToken,
            'mediaUri'    => $fileUrl ?? '',
        ];

        // Optional: Include business credentials in payload if provided
        if ($businessId) {
            $payload['businessId'] = $businessId;
        }
        if ($whatsappPhoneId) {
            $payload['whatsappPhoneId'] = $whatsappPhoneId;
        }
        if ($appId) {
            $payload['appId'] = $appId;
        }

        // Validate PDF URL is reachable before sending
        if ($isPdf && $fileUrl) {
            $curlCheck = curl_init();
            curl_setopt_array($curlCheck, [
                CURLOPT_URL            => $fileUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'HEAD',
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ]);
            curl_exec($curlCheck);
            $httpCodeCheck = curl_getinfo($curlCheck, CURLINFO_HTTP_CODE);
            curl_close($curlCheck);

            if ($httpCodeCheck !== 200) {
                Log::warning('WHATSCRM VOUCHERS ► PDF URL UNREACHABLE', [
                    'To'       => $toNumber,
                    'URL'      => $fileUrl,
                    'Headers'  => "HTTP/1.1 {$httpCodeCheck}",
                    'Fallback' => 'Sending without PDF attachment',
                ]);
            }
        }

        Log::info('WHATSCRM VOUCHERS ► SENDING', [
            'Template'          => $templateName,
            'To'                => $toNumber,
            'File'              => $fileUrl ?? 'none',
            'FileType'          => $isImage ? 'IMAGE' : 'PDF',
            'ClientName'        => $bodyValues[0] ?? '',
            'Service'           => $bodyValues[1] ?? '',
            'PickupDate'        => $bodyValues[2] ?? '',
            'PickupTime'        => $bodyValues[3] ?? '',
            'Product'           => $bodyValues[4] ?? '',
            'Location'          => $bodyValues[5] ?? '',
            'ContactPerson'     => $bodyValues[6] ?? '',
            'ContactNumber'     => $bodyValues[7] ?? '',
            'MapLink'           => $bodyValues[8] ?? '',
            'Narration'         => $bodyValues[9] ?? '',
            'BusinessID'        => $businessId ?? 'N/A',
            'WhatsAppPhoneID'   => $whatsappPhoneId ?? 'N/A',
            'AppID'             => $appId ?? 'N/A',
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiToken}",
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('WHATSCRM VOUCHERS: cURL error', ['to' => $toNumber, 'error' => $err]);
            return ['success' => false, 'error' => $err];
        }

        $result = json_decode($response, true);

        // Status determination: PDFs may have partial delivery
        if ($isPdf) {
            $statusMsg = ($result['success'] ?? false) ? '⚠ ACCEPTED (PDF sent but may not be delivered)' : '✗ FAILED';
            $details   = 'PDF Messages: WhatsCRM accepts but may not deliver. Monitor delivery.';
        } else {
            $statusMsg = ($result['success'] ?? false) ? '✓ SUCCESS' : '✗ FAILED';
            $details   = $result['metaResponse']['messages'][0]['message_status'] ?? 'unknown';
        }

        Log::info('WHATSCRM VOUCHERS ► RESULT', [
            'Status'   => $statusMsg,
            'Template' => $templateName,
            'FileType' => $isImage ? 'IMAGE' : 'PDF',
            'To'       => $toNumber,
            'HTTP'     => $httpCode,
            'MsgID'    => $result['metaResponse']['messages'][0]['id'] ?? 'N/A',
            'Details'  => $details,
        ]);

        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via WhatsCRM (FOR REFUNDS - Dynamic Template Selection)
    // refund_cust_notify_v2_img → Image (JPG/PNG header)
    // refund_cust_notify_v2     → PDF  (document header)
    // exampleArr: [customer_name, service_name, service_date, 
    //              refund_amount, refund_date] (5 variables)
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsCrmRefundMessage(
        string  $toNumber,
        array   $bodyValues,
        ?string $fileUrl  = null,
        ?string $filename = null,
        ?string $templateName = 'refund_cust_notify_v2'
    ) {
        $apiUrl   = config('services.whatscrm_vouchers.api_url');   // WHATSCRM_API_URL
        $apiToken = config('services.whatscrm_vouchers.api_token'); // WHATSCRM_API_TOKEN

        // WhatsCRM expects + prefix e.g. +919405059038
        $toNumber = '+' . preg_replace('/[^0-9]/', '', $toNumber);

        // Detect file type and select template accordingly
        $ext      = strtolower(pathinfo($filename ?? '', PATHINFO_EXTENSION));
        $isImage  = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
        $isPdf    = ($ext === 'pdf');

        // Direct template selection based on file type
        // IMAGE: refund_cust_notify_v2_img
        // PDF/TEXT: refund_cust_notify_v2
        if ($isImage) {
            $template = 'refund_cust_notify_v2_img';
        } else {
            $template = 'refund_cust_notify_v2';
        }

        // WhatsCRM Templet API payload
        $payload = [
            'sendTo'      => $toNumber,
            'templetName' => $template,
            'exampleArr'  => array_map('strval', $bodyValues),
            'token'       => $apiToken,
            'mediaUri'    => $fileUrl ?? '',
        ];

        // Validate PDF URL is reachable before sending
        if ($isPdf && $fileUrl) {
            $curlCheck = curl_init();
            curl_setopt_array($curlCheck, [
                CURLOPT_URL            => $fileUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'HEAD',
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ]);
            curl_exec($curlCheck);
            $httpCodeCheck = curl_getinfo($curlCheck, CURLINFO_HTTP_CODE);
            curl_close($curlCheck);

            if ($httpCodeCheck !== 200) {
                Log::warning('WHATSCRM REFUNDS ► PDF URL UNREACHABLE', [
                    'To'       => $toNumber,
                    'URL'      => $fileUrl,
                    'Headers'  => "HTTP/1.1 {$httpCodeCheck}",
                    'Fallback' => 'Sending without PDF attachment',
                ]);
            }
        }

        Log::info('WHATSCRM REFUNDS ► SENDING', [
            'Template'       => $template,
            'FileType'       => $isImage ? 'IMAGE' : ($isPdf ? 'PDF' : 'TEXT'),
            'To'             => $toNumber,
            'File'           => $fileUrl ?? 'none',
            'CustomerName'   => $bodyValues[0] ?? '',
            'ServiceName'    => $bodyValues[1] ?? '',
            'ServiceDate'    => $bodyValues[2] ?? '',
            'RefundAmount'   => $bodyValues[3] ?? '',
            'RefundDate'     => $bodyValues[4] ?? '',
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiToken}",
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('WHATSCRM REFUNDS ► cURL error', [
                'to'       => $toNumber,
                'template' => $template,
                'error'    => $err,
            ]);
            return ['success' => false, 'error' => $err];
        }

        $result = json_decode($response, true);

        // Status determination: PDFs may have partial delivery
        if ($isPdf) {
            $statusMsg = ($result['success'] ?? false) ? '⚠ ACCEPTED (PDF sent but may not be delivered)' : '✗ FAILED';
            $details   = 'PDF Messages: WhatsCRM accepts but may not deliver. Monitor delivery.';
        } else {
            $statusMsg = ($result['success'] ?? false) ? '✓ SUCCESS' : '✗ FAILED';
            $details   = $result['metaResponse']['messages'][0]['message_status'] ?? 'unknown';
        }

        Log::info('WHATSCRM REFUNDS ► RESULT', [
            'Status'   => $statusMsg,
            'Template' => $template,
            'FileType' => $isImage ? 'IMAGE' : ($isPdf ? 'PDF' : 'TEXT'),
            'To'       => $toNumber,
            'HTTP'     => $httpCode,
            'MsgID'    => $result['metaResponse']['messages'][0]['id'] ?? 'N/A',
            'Details'  => $details,
        ]);

        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via WhatsCRM — Registration Links (With Dynamic Button)
    // registration_link_final → Template with dynamic button for registration
    // exampleArr: [id_part, client_name, full_link] (3 variables)
    // Body: "Hello {{2}}, ... {{3}}"
    // Button: https://airaccretion.com/voucher/register/{{1}}
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsCrmRegistrationLinkMessage(
        string $toNumber,
        array  $bodyValues
    ) {
        $apiUrl   = config('services.whatscrm_vouchers.api_url');   // WHATSCRM_VOUCHERS_API_URL (has registration_link_final template)
        $apiToken = config('services.whatscrm_vouchers.api_token'); // WHATSCRM_VOUCHERS_API_TOKEN

        // WhatsCRM expects + prefix e.g. +919405059038
        $toNumber = '+' . preg_replace('/[^0-9]/', '', $toNumber);

        $template = 'registration_link_final';

        // Extract variables: [client_name, link]
        $clientName = $bodyValues[0] ?? '';
        $registrationLink = $bodyValues[1] ?? '';

        // Extract ID part from URL for button dynamic variable
        // Full URL: https://airaccretion.com/voucher/register/ab866379-7a76-4fa9-8000-761a25cedc7d/AJW4cyQq46GdgEaAw5UHBKCS8i9wffC60eR4Eciw
        // Extract: ab866379-7a76-4fa9-8000-761a25cedc7d/AJW4cyQq46GdgEaAw5UHBKCS8i9wffC60eR4Eciw
        $idPart = substr($registrationLink, strpos($registrationLink, '/register/') + strlen('/register/'));

        // Template uses 3 variables:
        // {{1}} = ID Part (button: https://airaccretion.com/voucher/register/{{1}})
        // {{2}} = Client Name (body: "Hello {{2}},")
        // {{3}} = Full Registration URL (body: "copy this link: {{3}}")
        $templateVariables = [
            $idPart,
            $clientName,
            $registrationLink,
        ];

        // WhatsCRM Templet API payload
        $payload = [
            'sendTo'      => $toNumber,
            'templetName' => $template,
            'exampleArr'  => array_map('strval', $templateVariables),
            'token'       => $apiToken,
            'mediaUri'    => '',
        ];

        Log::info('WHATSCRM REGISTRATION ► SENDING', [
            'Template'   => $template,
            'To'         => $toNumber,
            'IDPart'     => $idPart,
            'ClientName' => $clientName,
            'FullLink'   => $registrationLink,
            'Variables'  => 3,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiToken}",
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('WHATSCRM REGISTRATION ► cURL error', [
                'to'    => $toNumber,
                'error' => $err,
            ]);
            return ['success' => false, 'error' => $err];
        }

        $result = json_decode($response, true);

        // Debug: Log full response to understand structure
        Log::debug('WHATSCRM REGISTRATION ► FULL RESPONSE', [
            'HTTP'     => $httpCode,
            'Response' => $result,
        ]);

        // Status determination: Check HTTP 200 + response success flag
        if ($httpCode === 200 && ($result['success'] ?? false)) {
            $statusMsg = '✓ SUCCESS';
            $details   = $result['metaResponse']['messages'][0]['message_status'] ?? 'sent';
            $msgId     = $result['metaResponse']['messages'][0]['id'] ?? 'N/A';
        } elseif ($httpCode === 200) {
            // HTTP 200 but success flag not set - message likely sent
            $statusMsg = '✓ SUCCESS (HTTP 200)';
            $details   = $result['metaResponse']['messages'][0]['message_status'] ?? 'pending';
            $msgId     = $result['metaResponse']['messages'][0]['id'] ?? $result['id'] ?? 'N/A';
        } else {
            $statusMsg = '✗ FAILED';
            $details   = $result['error'] ?? $result['metaResponse']['messages'][0]['message_status'] ?? 'unknown';
            $msgId     = 'N/A';
        }

        Log::info('WHATSCRM REGISTRATION ► RESULT', [
            'Status'   => $statusMsg,
            'Template' => $template,
            'To'       => $toNumber,
            'HTTP'     => $httpCode,
            'MsgID'    => $msgId,
            'Details'  => $details,
        ]);

        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // WhatsApp via WhatsCRM — Ride Reminders
    // whatsapp_reminder → Template for ride reminders 5 and 1 hour before
    // exampleArr: [name, service, time, location, extra, service_date] (6 variables)
    // ═════════════════════════════════════════════════════════════════════════
    public function sendWhatsCrmRideReminderMessage(
        string $toNumber,
        array  $bodyValues
    ) {
        $apiUrl   = config('services.whatscrm_vouchers.api_url');   // WHATSCRM_API_URL (main account)
        $apiToken = config('services.whatscrm_vouchers.api_token'); // WHATSCRM_API_TOKEN

        // WhatsCRM expects + prefix e.g. +919405059038
        $toNumber = '+' . preg_replace('/[^0-9]/', '', $toNumber);

        $template = 'whatsapp_reminder';

        // Template variables: {{1}} name, {{2}} service, {{3}} time, {{4}} location, {{5}} extra, {{6}} service_date
        $name         = $bodyValues[0] ?? '';
        $service      = $bodyValues[1] ?? '';
        $time         = $bodyValues[2] ?? '';
        $location     = $bodyValues[3] ?? '';
        $extra        = $bodyValues[4] ?? '';
        $service_date = $bodyValues[5] ?? '';

        // WhatsCRM Templet API payload
        $payload = [
            'sendTo'      => $toNumber,
            'templetName' => $template,
            'exampleArr'  => array_map('strval', $bodyValues),
            'token'       => $apiToken,
            'mediaUri'    => '',
        ];

        Log::info('WHATSCRM RIDE REMINDERS ► SENDING', [
            'Template'    => $template,
            'To'          => $toNumber,
            'Name'        => $name,
            'Service'     => $service,
            'Time'        => $time,
            'Location'    => $location,
            'ServiceDate' => $service_date,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiToken}",
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Log::error('WHATSCRM RIDE REMINDERS ► cURL error', [
                'to'       => $toNumber,
                'template' => $template,
                'error'    => $err,
            ]);
            return ['success' => false, 'error' => $err];
        }

        $result = json_decode($response, true);

        // Status determination
        $statusMsg = ($result['success'] ?? false) ? '✓ SUCCESS' : '✗ FAILED';
        $details   = $result['metaResponse']['messages'][0]['message_status'] ?? ($result['error'] ?? 'unknown');
        $msgId     = $result['metaResponse']['messages'][0]['id'] ?? 'N/A';

        Log::info('WHATSCRM RIDE REMINDERS ► RESULT', [
            'Status'   => $statusMsg,
            'Template' => $template,
            'To'       => $toNumber,
            'HTTP'     => $httpCode,
            'MsgID'    => $msgId,
            'Details'  => $details,
        ]);

        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════════════════
    private function sanitizeWhatsAppField($value)
    {
        if (is_null($value)) return null;
        $s = (string) $value;
        $s = str_replace(["\t", "\r\n", "\r", "\n"], ' ', $s);
        $s = preg_replace('/ {3,}/', '  ', $s);
        $s = preg_replace('/[ ]{2,}/', '  ', $s);
        return trim($s);
    }
}
