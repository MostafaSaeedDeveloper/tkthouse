@extends('admin.master')
@section('title', 'Search')

@section('content')
<x-admin.page-header title="Search" :subtitle="$q !== '' ? $total.' result'.($total === 1 ? '' : 's').' for “'.$q.'”' : 'Search events, orders, customers and tickets.'" />

@if(mb_strlen($q) < 2)
    <x-admin.card><x-admin.empty icon="fa-magnifying-glass" title="Type at least 2 characters" text="Use the search box in the top bar." /></x-admin.card>
@elseif($total === 0)
    <x-admin.card><x-admin.empty icon="fa-magnifying-glass" title="Nothing found" text="Try an order number, an email address, a name or an event." /></x-admin.card>
@else
    @if($results['events']->isNotEmpty())
        <x-admin.card title="Events" flush>
            <x-admin.table caption="Event results"><tbody>
                @foreach($results['events'] as $e)
                    <tr><td><a class="a-table__main" href="{{ route('admin.events.show', $e) }}">{{ $e->name }}</a><span class="a-table__sub">{{ $e->event_date?->format('d M Y') }} · {{ $e->location }}</span></td><td class="a-num"><x-admin.status :status="$e->status" kind="event" /></td></tr>
                @endforeach
            </tbody></x-admin.table>
        </x-admin.card>
    @endif
    @if($results['orders']->isNotEmpty())
        <x-admin.card title="Orders" flush>
            <x-admin.table caption="Order results"><tbody>
                @foreach($results['orders'] as $o)
                    <tr><td><a class="a-table__main" href="{{ route('admin.orders.show', $o) }}">#{{ $o->order_number }}</a><span class="a-table__sub">{{ trim(($o->customer?->first_name ?? '').' '.($o->customer?->last_name ?? '')) }} · {{ $o->customer?->email }}</span></td><td class="a-num">{{ number_format((float) $o->total_amount, 2) }} EGP</td><td class="a-num"><x-admin.status :status="$o->status" /></td></tr>
                @endforeach
            </tbody></x-admin.table>
        </x-admin.card>
    @endif
    @if($results['customers']->isNotEmpty())
        <x-admin.card title="Customers" flush>
            <x-admin.table caption="Customer results"><tbody>
                @foreach($results['customers'] as $c)
                    <tr><td><a class="a-table__main" href="{{ route('admin.customers.show', $c) }}">{{ trim($c->first_name.' '.$c->last_name) }}</a><span class="a-table__sub">{{ $c->email }} · {{ $c->phone }}</span></td></tr>
                @endforeach
            </tbody></x-admin.table>
        </x-admin.card>
    @endif
    @if($results['tickets']->isNotEmpty())
        <x-admin.card title="Tickets" flush>
            <x-admin.table caption="Ticket results"><tbody>
                @foreach($results['tickets'] as $t)
                    <tr><td><a class="a-table__main" href="{{ route('admin.tickets.show', $t) }}">#{{ $t->ticket_number }}</a><span class="a-table__sub">{{ $t->holder_name }} · {{ $t->name }}</span></td><td class="a-num"><x-admin.status :status="$t->status" kind="ticket" /></td></tr>
                @endforeach
            </tbody></x-admin.table>
        </x-admin.card>
    @endif
@endif
@endsection
