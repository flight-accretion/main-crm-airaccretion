{{--
    Template : emails.refund-vendor
    Subject  : Payment Notification – {CustomerName} – {ServiceName}
    Variables passed via $data:
        vendor_name            → {VendorName}
        client_name            → {CustomerName}
        service                → {ServiceName}
        service_date           → {ServiceDate}
        paid_amount            → {Paid}
        pending_amount         → {Pending}
        balance_amount         → {Balance}
        refund_date            → {PaymentDate}
        refund_type            → {Mode}
        refund_reason          → optional remarks
        vendor_payment_amount  → fallback for paid_amount
    Attachment: refund proof screenshot / PDF (via RefundMail)

    ── Controller $vendorData update needed ─────────────────────────────────
    Add these 3 fields to $vendorData in sendRefundEmail():
        'paid_amount'    => $refund->refund_amount ?? 0,
        'pending_amount' => 0,   // or calculate from vendor total
        'balance_amount' => 0,   // or calculate from vendor total
    ─────────────────────────────────────────────────────────────────────────
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Notification</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{background:#f0f4f8;font-family:'Inter',Arial,sans-serif;color:#2d3748;padding:32px 16px}
.wrap{max-width:580px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10)}

/* Header */
.hdr{background:linear-gradient(135deg,#1a365d 0%,#2563eb 100%);padding:32px 40px;text-align:center}
.hdr-brand{font-size:20px;font-weight:800;color:#fff;letter-spacing:.5px;text-transform:uppercase}
.hdr-sub{font-size:10px;color:#bfdbfe;letter-spacing:3px;text-transform:uppercase;margin-top:4px}

/* Status bar */
.sbar{display:flex;align-items:center;gap:14px;background:#f0fdf4;border-left:5px solid #16a34a;padding:16px 32px}
.sicon{width:40px;height:40px;background:#16a34a;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sicon svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.stitle{font-size:15px;font-weight:700;color:#14532d}
.ssub{font-size:12px;color:#64748b;margin-top:2px}

/* Body */
.body{background:#fff;padding:36px 40px}
.greeting{font-size:14px;line-height:1.8;color:#374151;margin-bottom:20px}
.greeting strong{color:#14532d}

/* 3-box summary */
.boxes{display:table;width:100%;border-spacing:0;margin:20px 0}
.boxes-inner{display:flex;gap:10px}
.box{flex:1;border-radius:10px;padding:16px 12px;text-align:center;border:1px solid #e2e8f0;background:#f8fafc}
.box.green{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-color:#86efac}
.box.yellow{background:#fffbeb;border-color:#fde68a}
.box.blue{background:#eff6ff;border-color:#bfdbfe}
.box-lbl{font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:6px}
.box.green  .box-lbl{color:#15803d}
.box.yellow .box-lbl{color:#b45309}
.box.blue   .box-lbl{color:#1d4ed8}
.box-amt{font-size:17px;font-weight:800}
.box.green  .box-amt{color:#14532d}
.box.yellow .box-amt{color:#92400e}
.box.blue   .box-amt{color:#1e3a8a}
.box-amt small{font-size:11px;font-weight:600;margin-right:1px;opacity:.7}

/* Section title */
.stl{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#94a3b8;margin:28px 0 10px;padding-bottom:8px;border-bottom:1.5px solid #e2e8f0}

/* Table */
.tbl{width:100%;border-collapse:collapse}
.tbl tr td{padding:10px 0;font-size:13px;border-bottom:1px solid #f8fafc;vertical-align:top}
.tbl tr:last-child td{border-bottom:none}
.tbl td:first-child{color:#6b7280;font-weight:500;width:48%}
.tbl td:last-child{color:#111827;font-weight:600;text-align:right}
.pill{display:inline-block;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;letter-spacing:.5px;padding:3px 10px;border-radius:20px}
.pill-blue{display:inline-block;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:700;letter-spacing:.5px;padding:3px 10px;border-radius:20px}

/* Remarks */
.remark{background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;padding:14px 18px;margin:16px 0;font-size:13px;color:#78350f;line-height:1.7}
.remark strong{display:block;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#b45309;margin-bottom:4px}

.divider{height:1px;background:#f1f5f9;margin:24px 0}

/* Attachment note */
.anote{display:flex;align-items:center;gap:10px;background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:8px;padding:14px 18px;font-size:13px;color:#475569;margin:16px 0}
.anote svg{width:18px;height:18px;flex-shrink:0;fill:none;stroke:#16a34a;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

.note{font-size:13px;color:#9ca3af;line-height:1.8;margin-top:16px}

/* Footer */
.ftr{background:#1e3a8a;padding:28px 40px;text-align:center}
.ftr-brand{font-size:13px;font-weight:700;color:#fff;margin-bottom:6px}
.ftr p{font-size:11px;color:#93c5fd;line-height:1.8}
</style>
</head>
<body>
<div class="wrap">

    {{-- Header --}}
    <div class="hdr">
        <div class="hdr-brand">Accretion Aviation</div>
    </div>

    {{-- Status Bar --}}
    <div class="sbar">
        <div class="sicon">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
            <div class="stitle">Payment Processed</div>
            <div class="ssub">Payment has been made to your account.</div>
        </div>
    </div>

    {{-- Body --}}
    <div class="body">

        <p class="greeting">
            Dear <strong>{{ $data['vendor_name'] ?? 'Vendor' }}</strong>,<br><br>
            This is to inform you that a payment has been processed for the service
            provided to <strong>{{ $data['client_name'] ?? 'Customer' }}</strong>.
            Please find the complete payment details below.
        </p>

        {{-- Paid / Pending / Balance boxes --}}
        <div class="boxes-inner">
            <div class="box green">
                <div class="box-lbl">Paid</div>
                <div class="box-amt"><small>Rs</small>{{ number_format($data['paid_amount'] ?? $data['vendor_payment_amount'] ?? 0, 2) }}</div>
            </div>
            <div class="box yellow">
                <div class="box-lbl">Pending</div>
                <div class="box-amt"><small>Rs</small>{{ number_format($data['pending_amount'] ?? 0, 2) }}</div>
            </div>
            <div class="box blue">
                <div class="box-lbl">Balance</div>
                <div class="box-amt"><small>Rs</small>{{ number_format($data['balance_amount'] ?? 0, 2) }}</div>
            </div>
        </div>

        {{-- Service Details --}}
        <div class="stl">Service Details</div>
        <table class="tbl">
            <tr>
                <td>Vendor Name</td>
                <td>{{ $data['vendor_name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Customer Name</td>
                <td>{{ $data['client_name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Service</td>
                <td>{{ $data['service'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Service Date</td>
                <td>{{ $data['service_date'] ?? 'N/A' }}</td>
            </tr>
        </table>

        {{-- Payment Details --}}
        <div class="stl">Payment Details</div>
        <table class="tbl">
            <tr>
                <td>Amount Paid</td>
                <td>Rs {{ number_format($data['paid_amount'] ?? $data['vendor_payment_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Pending Amount</td>
                <td>Rs {{ number_format($data['pending_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Balance Amount</td>
                <td>Rs {{ number_format($data['balance_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Payment Date</td>
                <td>{{ $data['refund_date'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Payment Mode</td>
                <td><span class="pill">{{ $data['refund_type'] ?? 'N/A' }}</span></td>
            </tr>
        </table>

        {{-- Remarks (optional) --}}
        @if (!empty($data['refund_reason']))
        <div class="remark">
            <strong>Remarks</strong>
            {{ $data['refund_reason'] }}
        </div>
        @endif

        <div class="divider"></div>

        {{-- Attachment note --}}
        <div class="anote">
            <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            The payment receipt has been attached to this email for your records.
        </div>

        <p class="note">
            Please verify the above details. For any discrepancies, contact us immediately.
            Thank you for your continued partnership with Accretion Aviation.
        </p>

    </div>

    {{-- Footer --}}
    <div class="ftr">
        <div class="ftr-brand">Accretion Aviation Pvt. Ltd.</div>
        <p>
            This is an automated notification. Please do not reply directly to this email.<br>
            For support, reach us at support@accretion.in
        </p>
    </div>

</div>
</body>
</html>