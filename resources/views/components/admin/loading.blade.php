{{-- <x-admin.loading text="Loading…" /> — inline loading state; for buttons use data-a-loading on the form --}}
@props(['text' => 'Loading…'])
<div class="a-loading" role="status" aria-live="polite">{{ $text }}</div>
