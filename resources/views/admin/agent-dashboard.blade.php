@extends('admin.master')
@section('title', 'My dashboard')
@section('content')
@php $money = fn ($n) => number_format((float) $n, 2).' EGP'; @endphp
<x-admin.page-header title="My dashboard" :subtitle="'Your orders and sales — '.$from->format('d M').' to '.$to->format('d M Y')" :crumbs="['My dashboard']">
    <a class="a-btn a-btn--outline" href="{{ route('admin.reports.agents') }}"><i class="fa fa-chart-column"></i> My report</a>
    <a class="a-btn a-btn--primary" href="{{ route('admin.orders.index') }}"><i class="fa fa-receipt"></i> My orders</a>
</x-admin.page-header>
<x-admin.flash />

<h2 class="a-m-section__title">Orders assigned to me (now)</h2>
<div class="a-grid a-grid--4">
    <x-admin.kpi label="My orders" :value="number_format($totals['all'])" icon="fa-receipt" />
    <x-admin.kpi label="Pending" :value="number_format($totals['pending'])" icon="fa-hourglass-half" hint="Awaiting approval or payment" />
    <x-admin.kpi label="Paid" :value="number_format($totals['paid'])" icon="fa-circle-check" />
    <x-admin.kpi label="Canceled" :value="number_format($totals['canceled'])" icon="fa-circle-xmark" hint="Canceled or rejected" />
</div>

<h2 class="a-m-section__title" style="margin-top:24px">My sales this month</h2>
<div class="a-grid a-grid--3">
    <x-admin.kpi label="My sales (collected)" :value="$money($row['collected'])" icon="fa-coins" hint="Orders paid while assigned to me" />
    <x-admin.kpi label="Tickets sold" :value="number_format($row['tickets'])" icon="fa-ticket" />
    <x-admin.kpi label="Win rate" :value="$row['win_rate'] === null ? '—' : $row['win_rate'].'%'" icon="fa-bullseye" hint="Paid ÷ (paid + canceled)" />
</div>
<p class="a-hint" style="margin-top:12px">Sales are attributed when payment is confirmed and do not move if an order is reassigned later.</p>
@endsection
