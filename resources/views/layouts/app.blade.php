<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voucher Registration</title>
    <!-- Style Css -->
    <link rel="stylesheet" href="/assets/admin/css/style.css?v=1.2">
    <link rel="stylesheet" href="/assets/admin/css/responsive.css?v=1.1">
    <link rel="stylesheet" href="/assets/admin/css/custom.css?v=2.0">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">

    <style>
        body {
            background: #faf8ff;
        }
    </style>
</head>

<body>
    <main>
        @yield('content')
    </main>

    <!-- Jquery Cdn -->
    <script src="https://code.jquery.com/jquery-3.6.1.min.js"
        integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>

    <!-- Select2 Cdn -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Internal Select-2.js -->
    <script src="/assets/admin/js/select2.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <!-- Main JS -->
    <script src="/assets/admin/js/main.js"></script>

    <!-- Custom JS -->
    <script src="/assets/admin/js/custom.js"></script>

    <script>
        $(document).ready(function() {
            if (!$.fn.DataTable.isDataTable('.table-datatable')) {
                $('.table-datatable').DataTable({
                    responsive: false, // No plus icon
                    scrollX: true, // Horizontal scroll
                    columnDefs: [{
                        orderable: false,
                        targets: 0 // S.No. column — make non-sortable
                    }],
                    order: [
                        [0, 'asc']
                    ], // Sort by S.No.
                    drawCallback: function(settings) {
                        var api = this.api();
                        api.rows({
                            page: 'current'
                        }).every(function(rowIdx) {
                            var cell = this.cell(rowIdx, 0)
                        .node(); // Set S.No. in first column (index 0)
                            $(cell).html(rowIdx + 1);
                        });
                    }
                });
            }
        });
    </script>

    {{-- Allow child views to push additional scripts here (use @push('scripts') in views) --}}
    @stack('scripts')
</body>

</html>
