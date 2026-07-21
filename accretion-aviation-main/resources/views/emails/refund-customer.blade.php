{{--
    Template : emails.refund-customer
    Subject  : Refund Processed for Your Booking – {ServiceName}
    Variables passed via $data:
        client_name    → {CustomerName}
        service        → {ServiceName}
        service_date   → {ServiceDate}
        refund_amount  → {RefundAmount}
        refund_date    → {RefundDate}
        refund_type    → payment mode
        refund_reason  → optional
    Attachment: refund proof screenshot / PDF (via RefundMail)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Refund Processed</title>
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
.sbar{display:flex;align-items:center;gap:14px;background:#eff6ff;border-left:5px solid #2563eb;padding:16px 32px}
.sicon{width:40px;height:40px;background:#2563eb;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sicon svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.stitle{font-size:15px;font-weight:700;color:#1e3a8a}
.ssub{font-size:12px;color:#64748b;margin-top:2px}

/* Body */
.body{background:#fff;padding:36px 40px}
.greeting{font-size:14px;line-height:1.8;color:#374151;margin-bottom:20px}
.greeting strong{color:#1e3a8a}

/* Amount box */
.abox{background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:12px;padding:22px;text-align:center;margin:20px 0}
.abox-lbl{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#2563eb;margin-bottom:8px}
.abox-amt{font-size:34px;font-weight:800;color:#1e3a8a}
.abox-amt sup{font-size:16px;font-weight:600;color:#3b82f6;vertical-align:super;margin-right:2px}
.abox-mode{font-size:12px;color:#6b7280;margin-top:6px}
.abox-mode span{display:inline-block;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;letter-spacing:.5px;padding:2px 10px;border-radius:20px}

/* Section title */
.stl{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#94a3b8;margin:28px 0 10px;padding-bottom:8px;border-bottom:1.5px solid #e2e8f0}

/* Table */
.tbl{width:100%;border-collapse:collapse}
.tbl tr td{padding:10px 0;font-size:13px;border-bottom:1px solid #f8fafc;vertical-align:top}
.tbl tr:last-child td{border-bottom:none}
.tbl td:first-child{color:#6b7280;font-weight:500;width:48%}
.tbl td:last-child{color:#111827;font-weight:600;text-align:right}
.pill{display:inline-block;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:700;letter-spacing:.5px;padding:3px 10px;border-radius:20px}

/* Reason */
.reason{background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;padding:14px 18px;margin:16px 0;font-size:13px;color:#78350f;line-height:1.7}
.reason strong{display:block;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#b45309;margin-bottom:4px}

.divider{height:1px;background:#f1f5f9;margin:24px 0}

/* Attachment note */
.anote{display:flex;align-items:center;gap:10px;background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:8px;padding:14px 18px;font-size:13px;color:#475569;margin:16px 0}
.anote svg{width:18px;height:18px;flex-shrink:0;fill:none;stroke:#2563eb;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

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
            <div class="stitle">Refund Successfully Processed</div>
        </div>
    </div>

    {{-- Body --}}
    <div class="body">

        <p class="greeting">
            Dear <strong>{{ $data['client_name'] ?? 'Customer' }}</strong>,<br><br>
            We are pleased to confirm that your refund has been successfully processed.
            Please find the complete refund details below.
        </p>

        {{-- Refund Amount Highlight --}}
        <div class="abox">
            <div class="abox-lbl">Refund Amount</div>
            <div class="abox-amt"><sup>Rs</sup>{{ number_format($data['refund_amount'] ?? 0, 2) }}</div>
            <div class="abox-mode">Payment via <span>{{ $data['refund_type'] ?? 'N/A' }}</span></div>
        </div>

        {{-- Booking Details --}}
        <div class="stl">Booking Details</div>
        <table class="tbl">
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

        {{-- Refund Details --}}
        <div class="stl">Refund Details</div>
        <table class="tbl">
            <tr>
                <td>Refund Amount</td>
                <td>Rs {{ number_format($data['refund_amount'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Refund Date</td>
                <td>{{ $data['refund_date'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Payment Mode</td>
                <td><span class="pill">{{ $data['refund_type'] ?? 'N/A' }}</span></td>
            </tr>
        </table>

        {{-- Reason (optional) --}}
        {{-- @if (!empty($data['refund_reason']))
        <div class="reason">
            <strong>Reason for Refund</strong>
            {{ $data['refund_reason'] }}
        </div>
        @endif --}}

        <div class="divider"></div>

        {{-- Attachment note --}}
        <div class="anote">
            <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            The refund receipt has been attached to this email for your records.
        </div>

        <p class="note">
            For any queries regarding this refund, please contact our support team.
            We value your trust and look forward to serving you again.
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