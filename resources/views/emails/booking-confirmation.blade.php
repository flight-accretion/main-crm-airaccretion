@component('mail::message')
@php
    $escapedBody = e($body);
    $linkedBody = preg_replace_callback(
        '~https?://[^\s<]+~',
        function (array $matches): string {
            $url = $matches[0];
            $href = rtrim($url, '.,);]');
            $suffix = substr($url, strlen($href));

            return '<a href="' . $href . '">' . $href . '</a>' . $suffix;
        },
        $escapedBody
    );
@endphp
{!! nl2br($linkedBody) !!}
@endcomponent
