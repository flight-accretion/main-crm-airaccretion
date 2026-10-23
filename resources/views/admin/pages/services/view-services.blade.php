@extends('admin.layouts.header')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/shared/kimi-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/services/services.css') }}">
    <div class="crm-kimi sc-page">
        <header class="sc-heading">
            <h1>Service details</h1><a class="k-ghost" href="{{ route('admin.services.index') }}">Back to services</a>
        </header>
        <section class="sc-list">@include('admin.pages.services.partials.details')</section>
    </div>
@endsection