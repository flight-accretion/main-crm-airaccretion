{{-- Styles + scripts shared by the Leads-style Operations tables. Expects $tableId. --}}
<style>
    .lead-service-column {
        max-width: 320px;
    }

    .lead-service-preview {
        display: block;
        width: 320px;
        max-width: 100%;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    @media (max-width: 767.98px) {
        .lead-service-column {
            max-width: 220px;
        }

        .lead-service-preview {
            width: 220px;
        }
    }
</style>

@push('scripts')
    <script>
        $(document).ready(function () {
            // Filter card toggle (same behaviour as the Leads page).
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

            filterSection.hide();
            icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');

            $('#toggle-filters').on('click', function () {
                if (filterSection.is(':visible')) {
                    filterSection.slideUp();
                    icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
                } else {
                    filterSection.slideDown();
                    icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
                }
            });

            // "Show N entries" and the search box belong to the filter form.
            $('#per-page-select').on('change', function () {
                $('#filter-form').trigger('submit');
            });

            const table = $('#{{ $tableId }}');

            if ($.fn.DataTable && table.length && !$.fn.DataTable.isDataTable(table[0])) {
                if (table.find('tbody td[colspan]').length) {
                    return;
                }

                table.DataTable({
                    paging: false,
                    searching: false,
                    info: false,
                    lengthChange: false,
                    responsive: false,
                    scrollX: true,
                    autoWidth: false,
                    ordering: true,
                    columnDefs: [
                        { orderable: false, targets: -1 }
                    ]
                });
            }
        });
    </script>
@endpush
