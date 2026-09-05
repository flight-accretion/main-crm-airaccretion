@php
    $serviceText = (
        !empty($serviceNames)
        && is_array($serviceNames)
    )
        ? implode(', ', $serviceNames)
        : 'N/A';
@endphp

<span class="lead-service-preview">{{ $serviceText }}</span>
