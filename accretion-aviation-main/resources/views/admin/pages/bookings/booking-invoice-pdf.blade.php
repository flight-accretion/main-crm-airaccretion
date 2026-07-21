<!DOCTYPE html>
<html>

<head>
    <title>Invoice #8140-2099</title>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 15px;
            color: #333;
            line-height: 1.3;
        }

        .header-logo {
            height: 60px;
            width: 100px;
            object-fit: contain;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .invoice-header h1 {
            font-size: 18px;
            margin: 5px 0;
        }

        .address-section {
            display: flex;
            width: 100%;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 11px;
        }

        .print-button {
            padding: 6px 12px;
            background-color: #3b82f6;
            /* Dark gray */
            color: #fff;
            font-size: 12px;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: background-color 0.2s ease;
        }

        .print-button:hover {
            background-color: #374151;
            /* Slightly darker on hover */
        }

        .address-box {
            width: 48%;
        }

        .address-box h3 {
            font-size: 12px;
            margin: 8px 0 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background-color: #f8f9fa;
        }

        .text-primary {
            color: #3b82f6;
        }

        .text-success {
            color: #22c55e;
        }

        .totals-table {
            width: 100%;
            margin-top: 10px;
            font-size: 11px;
        }

        .totals-table td {
            padding: 4px 8px;
        }

        .notes-section {
            margin-top: 15px;
            font-size: 11px;
        }

        .notes-section p {
            margin: 5px 0;
        }

        .invoice-details table {
            margin: 5px 0 10px;
        }

        .invoice-details td {
            padding: 4px 6px;
        }

        h2 {
            font-size: 14px;
            margin: 10px 0 5px;
        }
    </style>
</head>

<body>
    <div class="invoice-header">
        <div style="flex: 1;">
           @if(app('request')->has('pdf'))
    <img src="{{ public_path('assets/admin/images/logo.png') }}" alt="Logo" style="width: 100px;">
@else
    <img src="{{ asset('assets/admin/images/logo.png') }}" alt="Logo" style="width: 100px;">
@endif
        </div>
        <div style="flex: 2; text-align: center;">
            <h1>BOOKING INVOICE</h1>
            <p class="text-primary">#8140-2099</p>
        </div>
        <div style="flex: 1; text-align: right;">
            <button type="button" title="Ctrl+P" onclick="window.print();" class="print-button no-print">
                <i class="ri-printer-line text-base"></i>
                Print Invoice
            </button>
        </div>
    </div>

    <div class="address-section">
        <div class="address-box">
            <h3>Billing From:</h3>
            <p><strong>Komal wadkar</strong></p>
            <p>Mig-1-11, Manroe street<br>
                Georgetown, Washington D.C, USA, 200071</p>
            <p>sprukotrust.ynex@gmail.com</p>
            <p>(555) 555-1234</p>
        </div>

        <div class="address-box">
            <h3>Billing To:</h3>
            <p><strong>Json Taylor</strong></p>
            <p>Lig-22-1, 20 Covington Place<br>
                New Castle, DE, United States, 19320</p>
            <p>jsontaylor2134@gmail.com</p>
            <p>+1 202-918-2132</p>
        </div>
    </div>

    <div class="invoice-details">
        <table>
            <tr>
                <td><strong>Invoice ID:</strong></td>
                <td>#SPK120219890</td>
                <td><strong>Booking ID:</strong></td>
                <td>MP65327</td>
            </tr>
            <tr>
                <td><strong>Invoice Date:</strong></td>
                <td>29, Nov 2022 - 12:42PM</td>
                <td><strong>Booking Date:</strong></td>
                <td>29, Dec 2022</td>
            </tr>
        </table>
    </div>

    <h2>Product Information</h2>
    <table>
        <thead>
            <tr>
                <th>PRODUCT NAME</th>
                <th>DESCRIPTION</th>
                <th>PAYMENT MODE</th>
                <th>AMOUNT</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Dapzem & Co (Sweatshirt)</td>
                <td>Branded hoodie ethnic style</td>
                <td>UPI</td>
                <td>$60</td>
                <td>$120</td>
            </tr>
            <tr>
                <td>Denim Winjo (Jacket)</td>
                <td>Vintage pure leather Jacket</td>
                <td>CASH ON DELIVERY</td>
                <td>$249</td>
                <td>$249</td>
            </tr>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td width="70%">Sub Total:</td>
            <td>$2,364</td>
        </tr>
        <tr>
            <td>Avail Discount:</td>
            <td>$29.98</td>
        </tr>
        <tr>
            <td>Coupon Discount (10%):</td>
            <td>$236.40</td>
        </tr>
        <tr>
            <td>Vat (20%):</td>
            <td>$472.80</td>
        </tr>
        <tr>
            <td>Due Till Date:</td>
            <td>$0</td>
        </tr>
        <tr>
            <td><strong>Total:</strong></td>
            <td class="text-success"><strong>$2,570.42</strong></td>
        </tr>
    </table>

    <div class="notes-section">
        <p><strong>Note:</strong> Once the invoice has been verified by the accounts payable team and recorded, the only
            task left is to send it for approval before releasing the payment.</p>
    </div>
</body>

</html>
