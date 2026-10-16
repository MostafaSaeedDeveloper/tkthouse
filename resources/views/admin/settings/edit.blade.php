@extends('admin.master')
@section('title', 'General Settings')
@section('content')
<x-admin.page-header title="General Settings" subtitle="Branding, WhatsApp ticket sending and payment timeout." :crumbs="['Dashboard' => route('admin.dashboard'), 'Settings']">
    <a class="a-btn a-btn--outline" href="{{ route('admin.payment-methods.index') }}"><i class="fa fa-credit-card"></i> Payment Methods</a>
</x-admin.page-header>
<x-admin.flash />
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" data-a-loading>
    @csrf
    @method('PUT')
    <x-admin.card class="a-m-narrow">
        <x-admin.form-section title="Brand" description="Site name and colors." cols="3">
            <x-admin.field class="is-full" label="Site Name" name="site_name" for="s-site-name" required><input id="s-site-name" type="text" class="form-control" name="site_name" value="{{ old('site_name', $settings['site_name'] ?? 'TKT House') }}" required></x-admin.field>
            <x-admin.field label="Primary Color" name="primary_color" for="s-primary"><input id="s-primary" type="color" class="form-control form-control-color a-m-color" name="primary_color" value="{{ old('primary_color', $settings['primary_color'] ?? '#f5b800') }}"></x-admin.field>
            <x-admin.field label="Secondary Color" name="secondary_color" for="s-secondary"><input id="s-secondary" type="color" class="form-control form-control-color a-m-color" name="secondary_color" value="{{ old('secondary_color', $settings['secondary_color'] ?? '#111111') }}"></x-admin.field>
        </x-admin.form-section>

        <x-admin.form-section title="Logos" description="Upload only the logos you want to replace." cols="3">
            <x-admin.field label="Logo Light" name="logo_light" for="s-logo-light"><input id="s-logo-light" type="file" class="form-control" name="logo_light" accept="image/*"></x-admin.field>
            <x-admin.field label="Logo Dark" name="logo_dark" for="s-logo-dark"><input id="s-logo-dark" type="file" class="form-control" name="logo_dark" accept="image/*"></x-admin.field>
            <x-admin.field label="Logo Footer" name="logo_footer" for="s-logo-footer"><input id="s-logo-footer" type="file" class="form-control" name="logo_footer" accept="image/*"></x-admin.field>
        </x-admin.form-section>

        <x-admin.form-section title="Orders and messaging" cols="2">
            <div class="a-m-switch form-check form-switch">
                <input class="form-check-input" type="checkbox" id="whatsapp_ticket_sending_enabled" name="whatsapp_ticket_sending_enabled" value="1" @checked(old('whatsapp_ticket_sending_enabled', data_get($settings, 'whatsapp_ticket_sending_enabled', true)))>
                <label class="form-check-label" for="whatsapp_ticket_sending_enabled">
                    <strong>Enable sending tickets via WhatsApp</strong>
                    <span class="a-hint d-block">Disable this to stop WhatsApp sending from admin and post-payment flows.</span>
                </label>
            </div>
            <x-admin.field label="Pending Payment Timeout (minutes)" name="pending_payment_timeout_minutes" for="s-timeout" hint="Default is 2880 minutes (2 days). Orders still pending payment after this period are auto-canceled.">
                <input id="s-timeout" type="number" min="1" max="10080" class="form-control" name="pending_payment_timeout_minutes" value="{{ old('pending_payment_timeout_minutes', data_get($settings, 'pending_payment_timeout_minutes', 2880)) }}">
            </x-admin.field>
        </x-admin.form-section>
        <div class="a-form-actions"><button class="a-btn a-btn--primary" type="submit">Save Settings</button></div>
    </x-admin.card>
</form>
@endsection
