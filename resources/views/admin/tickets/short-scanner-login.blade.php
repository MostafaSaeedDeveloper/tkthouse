<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scanner Login</title>
  <link rel="stylesheet" href="{{ asset('admin/assets/css/dashmix.min.css') }}">
</head>
<body class="bg-body-dark">
<div class="hero bg-body-extra-light min-vh-100">
  <div class="hero-inner">
    <div class="content content-full">
      <div class="row justify-content-center">
        <div class="col-md-6 col-xl-4">
          <div class="block block-rounded mb-0">
            <div class="block-header block-header-default">
              <h3 class="block-title">Scanner Quick Login</h3>
            </div>
            <div class="block-content">
              <p class="text-muted">Enter scanner username and password to open QR scanner directly.</p>
              <p class="mb-3"><strong>Name:</strong> {{ $scannerUser->name }}</p>
              <form method="POST" action="{{ route('front.scanner.short-link.login', $token) }}">
                @csrf
                <div class="mb-3">
                  <label class="form-label">Username</label>
                  <input type="text" class="form-control" name="username" value="{{ old('username') }}" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Password</label>
                  <input type="password" class="form-control" name="password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Open Scanner</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
