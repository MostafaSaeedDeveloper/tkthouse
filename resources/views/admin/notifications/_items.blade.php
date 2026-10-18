@forelse($items as $n)
    <a class="a-notif__item{{ $n->read_at ? '' : ' is-unread' }}" href="{{ route('admin.notifications.open', $n->id) }}">
        <span class="a-notif__icon" aria-hidden="true"><i class="fa {{ $n->data['icon'] ?? 'fa-bell' }}"></i></span>
        <span class="a-notif__text">
            <b>{{ $n->data['title'] ?? 'Notification' }}</b>
            @if(! empty($n->data['body']))<small>{{ $n->data['body'] }}</small>@endif
        </span>
        <time class="a-notif__time" datetime="{{ $n->created_at->toIso8601String() }}" title="{{ $n->created_at->format('d-m-Y h:i A') }}">{{ $n->created_at->diffForHumans(null, true, true) }}</time>
        @unless($n->read_at)<span class="a-notif__dot" aria-label="Unread"></span>@endunless
    </a>
@empty
    <p class="a-notif__empty"><i class="fa fa-bell-slash" aria-hidden="true"></i> You're all caught up.</p>
@endforelse
