@php
    use App\Services\Payments\ProviderCapabilities as C;
    $rows = ['list_transactions', 'search_transactions', 'transaction_details', 'status_inquiry', 'refund', 'partial_refund', 'void', 'cancel', 'fees', 'export'];
    $badge = fn (string $s) => match ($s) { C::API => 'success', C::ACT => 'warning', C::MAN => 'info', default => 'default' };
@endphp
<details class="a-card" style="margin-bottom:12px"><summary class="a-card__head" style="cursor:pointer;list-style:none"><div><h2 class="a-card__title">What each provider's official API can do</h2><p class="a-card__sub">Buttons only appear for what is really supported. Neither provider offers an API to list all transactions.</p></div></summary>
<div class="a-card__body a-card__body--flush">
<x-admin.table stack compact caption="Provider capabilities">
    <thead><tr><th>Function</th><th>Paymob</th><th>Fawaterak</th></tr></thead>
    <tbody>
    @foreach($rows as $cap)
        <tr>
            <td data-label="Function"><span class="a-table__main">{{ C::CAPABILITIES[$cap] }}</span></td>
            @foreach(['paymob', 'fawaterak'] as $p)
                @php [$st, $sbx, $note] = C::matrix()[$cap][$p]; @endphp
                <td data-label="{{ ucfirst($p) }}" class="a-wrap"><x-admin.badge :variant="$badge($st)">{{ C::LABELS[$st] }}</x-admin.badge>@if($sbx)<x-admin.badge>Verify in sandbox</x-admin.badge>@endif<span class="a-table__sub">{{ $note }}</span></td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</x-admin.table></div></details>
