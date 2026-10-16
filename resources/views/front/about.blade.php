@extends('front.layout.app')

@section('title', 'About')

@section('content')
    <x-tkt.page-header title="About TKT House" sub="We are a techno events company focused on unforgettable nights and secure ticket booking." />

    <section class="tkt-section tkt-section--tight">
        <div class="tkt-container">
            <div class="tkt-prose" style="max-width:760px">
                <h2 class="tkt-h2" style="margin-top:0">Who we are</h2>
                <p>TKT House curates premium techno events featuring leading local and international DJs. From intimate warehouse sessions to large-scale festival productions, we design complete nightlife experiences for real electronic music fans.</p>
                <p>Our platform makes ticket booking simple, transparent, and safe. Guests can discover events, choose ticket tiers, and reserve spots instantly with clear event details and trusted entry management.</p>
            </div>

            <div class="tkt-about-grid">
                <div class="tkt-card"><div class="tkt-card__body">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <h3 class="tkt-h4">Discover</h3><p class="tkt-text-muted tkt-text-sm" style="margin-top:6px">Find upcoming events with clear dates, venues and lineups.</p>
                </div></div>
                <div class="tkt-card"><div class="tkt-card__body">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9z"/></svg>
                    <h3 class="tkt-h4">Book in minutes</h3><p class="tkt-text-muted tkt-text-sm" style="margin-top:6px">Choose your ticket tier and reserve your spot instantly.</p>
                </div></div>
                <div class="tkt-card"><div class="tkt-card__body">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <h3 class="tkt-h4">Trusted entry</h3><p class="tkt-text-muted tkt-text-sm" style="margin-top:6px">Secure ticketing and managed entry for every event.</p>
                </div></div>
            </div>
        </div>
    </section>
@endsection
