@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>My Dashboard</h6>
    </div>
</div>

<section class="py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-warning mb-0">Account Dashboard</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('front.checkout') }}" class="btn btn-warning">New Checkout</a>
                <form method="POST" action="{{ route('front.customer.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light">Logout</button>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-header border-secondary text-warning">Profile</div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            @if($user->profileImageUrl())
                                <img src="{{ $user->profileImageUrl() }}" alt="Profile image" class="rounded-circle" style="width:110px;height:110px;object-fit:cover;">
                            @else
                                <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center bg-secondary text-white" style="width:110px;height:110px;font-size:35px;">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('front.account.profile.update') }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}" required>
                                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Profile Image</label>
                                <input type="file" name="profile_image" class="form-control @error('profile_image') is-invalid @enderror" accept="image/*">
                                <small class="text-muted d-block mt-1">JPG/PNG/WebP up to 2MB.</small>
                                @error('profile_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <button class="btn btn-warning w-100" type="submit">Save Profile</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-header border-secondary text-warning">My Orders</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Total</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($orders as $order)
                                        <tr>
                                            <td>{{ $order->order_number }}</td>
                                            <td>{{ ucwords(str_replace('_', ' ', $order->status)) }}</td>
                                            <td>{{ ucwords(str_replace('_', ' ', $order->payment_status)) }}</td>
                                            <td>{{ number_format($order->total_amount, 2) }} EGP</td>
                                            <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No orders yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer border-secondary">{{ $orders->links() }}</div>
                </div>

                <div class="card bg-dark border-secondary">
                    <div class="card-header border-secondary text-warning">My Tickets</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Holder</th>
                                        <th>Order #</th>
                                        <th>Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tickets as $ticket)
                                        <tr>
                                            <td>{{ $ticket->ticket_number }}</td>
                                            <td>{{ $ticket->holder_name }}</td>
                                            <td>{{ $ticket->order?->order_number }}</td>
                                            <td>{{ number_format($ticket->ticket_price, 2) }} EGP</td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-warning" href="{{ route('front.tickets.show', $ticket) }}">View</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No tickets yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer border-secondary">{{ $tickets->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
