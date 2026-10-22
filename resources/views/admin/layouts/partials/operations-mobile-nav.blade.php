<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/operations-mobile-nav.css') }}?v=1">
<div data-operations-mobile-navigation>
    <section class="operations-mobile-more" id="operations-mobile-more" role="dialog" aria-labelledby="operations-mobile-more-title" hidden>
        <header><h2 id="operations-mobile-more-title">More</h2><button type="button" data-close-operations-more aria-label="Close navigation" title="Close"><i class="ri-close-line" aria-hidden="true"></i></button></header>
        <nav data-operations-more-links aria-label="More Operations tools"></nav>
    </section>
    <nav class="operations-mobile-nav" aria-label="Operations mobile navigation">
        <a href="{{ route('admin.operations.index') }}" data-operations-primary><i class="ri-checkbox-circle-line" aria-hidden="true"></i><span>Work Done</span></a>
        <a href="{{ route('admin.operations.queue', 'review') }}" data-operations-primary><i class="ri-star-line" aria-hidden="true"></i><span>Review</span></a>
        <a href="{{ route('admin.rides.upcoming') }}" data-operations-primary><i class="ri-calendar-event-line" aria-hidden="true"></i><span>Rides</span></a>
        <button type="button" data-toggle-operations-more aria-expanded="false" aria-controls="operations-mobile-more" aria-haspopup="dialog"><i class="ri-apps-line" aria-hidden="true"></i><span>More</span></button>
    </nav>
</div>
<script src="{{ asset('assets/admin/js/crm/operations-mobile-nav.js') }}?v=1" defer></script>
