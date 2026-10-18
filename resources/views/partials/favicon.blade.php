{{-- The one favicon for every page (Frontend, Admin, Auth, Scanner). A custom one set in Settings → Branding wins; otherwise the TKT House icon at /favicon.ico. --}}
@php($tktFavicon = ($f = \App\Support\SystemSettings::get('site_favicon')) ? asset('storage/'.$f) : asset('favicon.ico'))
<link rel="icon" href="{{ $tktFavicon }}" sizes="any">
<link rel="apple-touch-icon" href="{{ $tktFavicon }}">
