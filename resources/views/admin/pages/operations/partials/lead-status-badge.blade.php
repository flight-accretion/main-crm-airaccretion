{{-- Same lead status badges as the Leads page. Expects $status (int|null). --}}
@if ($status === 0)
    <span class="badge bg-secondary/10 text-secondary">Initiated</span>
@elseif ($status === 1)
    <span class="badge bg-success/10 text-success">Active</span>
@elseif ($status === 2)
    <span class="badge bg-danger/10 text-danger">Cancelled</span>
@elseif ($status === 3)
    <span class="badge bg-primary/10 text-primary">Full Payment Received</span>
@elseif ($status === 4)
    <span class="badge bg-warning/10 text-warning">Partial Payment Received</span>
@elseif ($status === 5)
    <span class="badge bg-info/10 text-info">Confirmed</span>
@elseif ($status === 6)
    <span class="badge bg-default/10 text-default">Pending</span>
@elseif ($status === 7)
    <span class="badge bg-light/10 text-light">Rescheduled</span>
@elseif ($status === 8)
    <span class="badge bg-warning/10 text-warning">Approved</span>
@elseif ($status === 9)
    <span class="badge bg-danger/10 text-danger">Rejected</span>
@else
    <span class="badge bg-default/10 text-default">N/A</span>
@endif
