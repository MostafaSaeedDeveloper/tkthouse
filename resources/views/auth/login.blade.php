<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Sign In | TKT House</title>
    <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @foreach(['tokens', 'module-admin'] as $ds)
    <link rel="stylesheet" href="{{ asset('admin-ds/css/'.$ds.'.css') }}?v={{ filemtime(public_path('admin-ds/css/'.$ds.'.css')) }}">
    @endforeach
</head>
<body class="a-lg">
    <main class="a-lg__card">
        <a class="a-lg__logo" href="{{ route('front.home') }}" aria-label="TKT House home">
            <img src="{{ asset('images/logo-light.png') }}" alt="TKT House">
        </a>
        <h1 class="a-lg__title">Dashboard sign in</h1>
        <p class="a-lg__sub">Admin access</p>

        @if($errors->has('email') || $errors->has('auth'))
            <div class="a-lg__alert" role="alert">{{ $errors->first('email') ?: $errors->first('auth') }}</div>
        @endif

        <form action="{{ route('login') }}" method="POST" autocomplete="off">
            @csrf
            <div class="a-lg__field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Enter your username" class="@error('username') is-invalid @enderror" required autofocus>
                @error('username')<div class="a-lg__err" role="alert">{{ $message }}</div>@enderror
            </div>
            <div class="a-lg__field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" class="@error('password') is-invalid @enderror" required>
                @error('password')<div class="a-lg__err" role="alert">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="a-lg__btn">Sign In</button>
        </form>

        <div class="a-lg__back"><a href="{{ route('front.home') }}">&larr; Back to website</a></div>
    </main>
</body>
</html>
