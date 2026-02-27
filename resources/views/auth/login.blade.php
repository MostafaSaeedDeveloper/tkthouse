<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Login | TKT House</title>
    <link rel="shortcut icon" href="{{ asset('admin/assets/media/favicons/favicon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('admin/assets/media/favicons/favicon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('admin/assets/media/favicons/apple-touch-icon-180x180.png') }}">
    <link rel="stylesheet" id="css-main" href="{{ asset('admin/assets/css/dashmix.min.css') }}">
  </head>
  <body>
    <div id="page-container">
      <main id="main-container">
        <div class="bg-image" style="background-image: url('{{ asset('admin/assets/media/photos/photo22@2x.jpg') }}');">
          <div class="row g-0 bg-primary-op">
            <div class="hero-static col-md-6 d-flex align-items-center bg-body-extra-light">
              <div class="p-3 w-100">
                <div class="mb-3 text-center">
                  <a class="link-fx fw-bold fs-1" href="{{ route('front.home') }}">
                    <span class="text-dark">TKT</span><span class="text-primary">House</span>
                  </a>
                  <p class="text-uppercase fw-bold fs-sm text-muted mb-2">Dashboard Sign In</p>
                </div>

                <div class="row g-0 justify-content-center">
                  <div class="col-sm-8 col-xl-6">
                    <form action="{{ route('login') }}" method="POST">
                      @csrf

                      <div class="py-3">
                        <div class="mb-4">
                          <input type="text" class="form-control form-control-lg form-control-alt @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" placeholder="Username" required autofocus>
                          @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                          <input type="password" class="form-control form-control-lg form-control-alt @error('password') is-invalid @enderror" id="password" name="password" placeholder="Password" required>
                          @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                      </div>

                      <div class="mb-4">
                        <button type="submit" class="btn w-100 btn-lg btn-hero btn-primary">
                          <i class="fa fa-fw fa-sign-in-alt opacity-50 me-1"></i> Sign In
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <div class="hero-static col-md-6 d-none d-md-flex align-items-md-center justify-content-md-center text-md-center">
              <div class="p-3">
                <p class="display-4 fw-bold text-white mb-3">Welcome to TKT House Dashboard</p>
                <p class="fs-lg fw-semibold text-white-75 mb-0">Copyright &copy; <span data-toggle="year-copy"></span></p>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <script src="{{ asset('admin/assets/js/dashmix.app.min.js') }}"></script>
  </body>
</html>
