<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Sign In | TKT House</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">
</head>
<body class="a-lg">
    <main class="a-lg__card">
        <a class="a-lg__logo" href="{{ route('front.home') }}" aria-label="TKT House home">
            <img src="{{ asset('images/logo-light.png') }}" alt="TKT House">
        </a>
        <h1 class="a-lg__sr">Dashboard sign in</h1>

        @if($errors->has('email') || $errors->has('auth'))
            <div class="a-lg__alert" role="alert">{{ $errors->first('email') ?: $errors->first('auth') }}</div>
        @endif

        <form action="{{ route('login') }}" method="POST" autocomplete="on">
            @csrf
            <div class="a-lg__field">
                <label for="username" class="a-lg__sr">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Username" autocomplete="username" class="@error('username') is-invalid @enderror" required autofocus>
                @error('username')<div class="a-lg__err" role="alert">{{ $message }}</div>@enderror
            </div>
            <div class="a-lg__field">
                <label for="password" class="a-lg__sr">Password</label>
                <div class="a-lg__pw">
                    <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" class="@error('password') is-invalid @enderror" required>
                    <button type="button" class="a-lg__eye" aria-label="Show password" aria-pressed="false" data-pw-toggle>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                @error('password')<div class="a-lg__err" role="alert">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="a-lg__btn">Sign In</button>
        </form>

        <div class="a-lg__back"><a href="{{ route('front.home') }}">&larr; Back to website</a></div>
    </main>
    <script>
        (function () {
            var btn = document.querySelector('[data-pw-toggle]'), input = document.getElementById('password');
            if (!btn || !input) return;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                input.focus();
            });
        })();
    </script>
</body>
</html>
