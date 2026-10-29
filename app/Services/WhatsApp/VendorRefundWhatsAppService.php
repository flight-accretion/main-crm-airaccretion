<?php

namespace App\Services\WhatsApp;

class VendorRefundWhatsAppService
{
    public function __construct(
        private WhatsAppTemplateService $templates
    ) {
    }

    public function send(
        string $toNumber,
        array $bodyValues,
        ?string $fileUrl = null,
        ?string $filename = null
    ): array {
        $extension = strtolower(pathinfo($filename ?? '', PATHINFO_EXTENSION));
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
        $template = $isImage
            ? config('services.whatscrm.vendor_refund_image_template', 'refund_vendor_notify_v2_img')
            : config('services.whatscrm.vendor_refund_template', 'refund_vendor_notify_v2');

        return $this->templates->sendTemplate(
            $toNumber,
            $template,
            $bodyValues,
            $fileUrl
        );
    }
}
