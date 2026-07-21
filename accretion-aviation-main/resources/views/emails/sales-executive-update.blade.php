{{--
    Template : emails.sales-executive-update
    Subject  : Your Daily Sales Update – {{ $data['month_name'] }} | Accretion Aviation
    Variables passed via $data:
        executive_name      → Sales executive's first name
        month_name          → e.g. "March 2026"
        session             → "Morning" or "Evening"
        sales_completed     → Total sales closed this month (numeric)
        monthly_target      → Monthly target amount (numeric)
        target_achieved_pct → round((sales_completed / monthly_target) * 100)
        followups_today     → Count of follow-ups scheduled for today
        sale_to_achieve     → (monthly_target - sales_completed) / days_remaining
        days_remaining      → Working days left in the month
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Update</title>
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
.sicon{width:40px;height:40px;background:#2563eb;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;line-height:1}
.stitle{font-size:15px;font-weight:700;color:#1e3a8a}
.ssub{font-size:12px;color:#64748b;margin-top:2px}

/* Body */
.body{background:#fff;padding:36px 40px}
.greeting{font-size:14px;line-height:1.8;color:#374151;margin-bottom:24px}
.greeting strong{color:#1e3a8a}

/* Section title */
.stl{font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#94a3b8;margin:24px 0 10px;padding-bottom:8px;border-bottom:1.5px solid #e2e8f0}

/* Table */
.tbl{width:100%;border-collapse:collapse}
.tbl tr td{padding:11px 0;font-size:13px;border-bottom:1px solid #f1f5f9;vertical-align:top}
.tbl tr:last-child td{border-bottom:none}
.tbl td:first-child{color:#6b7280;font-weight:500;width:55%}
.tbl td:last-child{color:#111827;font-weight:600;text-align:right}
.tbl tr.highlight td{color:#1e3a8a;font-weight:700;font-size:14px}

/* Progress bar */
.prog-wrap{margin:20px 0}
.prog-label{display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:6px}
.prog-label strong{color:#1e3a8a}
.prog-track{height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden}
.prog-fill{height:8px;border-radius:999px;background:linear-gradient(90deg,#2563eb,#16a34a)}

.divider{height:1px;background:#f1f5f9;margin:24px 0}
.note{font-size:12px;color:#9ca3af;line-height:1.8;margin-top:16px}

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
        <div class="hdr-sub">Daily Sales Update · {{ $data['month_name'] ?? '' }}</div>
    </div>

    {{-- Status Bar --}}
    <div class="sbar">
        <div class="sicon">{{ ($data['session'] ?? 'Morning') === 'Morning' ? '☀️' : '🌙' }}</div>
        <div>
            <div class="stitle">{{ $data['session'] ?? 'Morning' }} Update</div>
            <div class="ssub">Your performance summary for {{ $data['month_name'] ?? 'this month' }}</div>
        </div>
    </div>

    {{-- Body --}}
    <div class="body">

        <p class="greeting">
            Dear <strong>{{ $data['executive_name'] ?? 'Team' }}</strong>,<br><br>
            Please find your sales performance summary below.
        </p>

        {{-- Progress Bar --}}
        <div class="prog-wrap">
            <div class="prog-label">
                <span>Monthly Target Progress</span>
                <strong>{{ $data['target_achieved_pct'] ?? 0 }}% Achieved</strong>
            </div>
            <div class="prog-track">
                <div class="prog-fill" style="width:{{ min($data['target_achieved_pct'] ?? 0, 100) }}%"></div>
            </div>
        </div>

        {{-- Stats Table --}}
        <div class="stl">Performance Summary</div>
        <table class="tbl">
            <tr>
                <td>Sales Completed Till Now</td>
                <td>₹{{ number_format($data['sales_completed'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Target for {{ $data['month_name'] ?? 'the Month' }}</td>
                <td>₹{{ number_format($data['monthly_target'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Follow-ups for Today</td>
                <td>{{ $data['followups_today'] ?? 0 }} leads</td>
            </tr>
            <tr>
                <td>Working Days Left</td>
                <td>{{ $data['days_remaining'] ?? '—' }} days</td>
            </tr>
            <tr class="highlight">
                <td>Sale to Be Achieved Today</td>
                <td>₹{{ number_format($data['sale_to_achieve'] ?? 0, 2) }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <p class="note">
            This is an automated {{ strtolower($data['session'] ?? 'daily') }} update from the Accretion Aviation sales system.<br>
            For any queries, please contact your manager.
        </p>

    </div>

    {{-- Footer --}}
    <div class="ftr">
        <div class="ftr-brand">Accretion Aviation Pvt. Ltd.</div>
        <p>
            This is an automated notification. Please do not reply directly to this email.<br>
            For support, reach us at confirm@accretionaviation.com
        </p>
    </div>

</div>
</body>
</html>