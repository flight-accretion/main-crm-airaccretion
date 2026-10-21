<?php

namespace Tests\Unit;

use App\Models\BookingEmailTemplate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class BookingEmailTemplateDesignTest extends TestCase
{
    public function test_editor_keeps_existing_save_contract_and_safe_preview(): void
    {
        $template = new BookingEmailTemplate([
            'subject'=>'Booking for {{customer_name}}',
            'body'=>'Hello {{customer_name}} <script>alert(1)</script>',
        ]);
        view()->share('errors', new ViewErrorBag());
        $source = str_replace("@extends('admin.layouts.header')", '', file_get_contents(resource_path('views/admin/pages/email-template/booking-confirmation.blade.php')));
        $html = Blade::render($source."\n@yield('content')\n@stack('scripts')", [
            'template'=>$template, 'variables'=>BookingEmailTemplate::variables(),
        ]);

        foreach (['booking-email-page','booking-template-form','name="_method" value="PUT"','name="subject"','name="body"','booking-preview-body','data-booking-variable','booking-discard','Sample booking','Version history','booking-send-test'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('Booking Reminder (24h)', $html);
    }
}
