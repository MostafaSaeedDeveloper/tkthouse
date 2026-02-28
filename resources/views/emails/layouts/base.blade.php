<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{{ $title ?? 'TKT House Notification' }}</title>
@include('emails.partials.styles')
</head>
<body>
<div class="eb">
<div class="ew">

  <div class="eh">
    <img src="{{ asset('images/logo-light.png') }}" alt="TKT House" width="146" style="margin: 0 auto; height: auto;">
  </div>

  @isset($heroTitle)
  <div class="ehero">
    @isset($heroIcon)
      <div class="ehero-icon" style="text-align:center;font-size:44px;line-height:1;margin:0 auto 14px;display:block;width:100%;">{{ $heroIcon }}</div>
    @endisset
    <h1>{{ $heroTitle }}</h1>
    @isset($heroText)
      <p>{!! $heroText !!}</p>
    @endisset
  </div>
  @endisset

  <div class="ebody">
    @yield('content')
  </div>

  <div class="efooter">
    <p>© {{ date('Y') }} TKT House · All rights reserved</p>
    <p style="margin-top:5px;">{{ $footerText ?? 'This email was sent from TKT House.' }}</p>
  </div>

</div>
</div>
</body>
</html>
