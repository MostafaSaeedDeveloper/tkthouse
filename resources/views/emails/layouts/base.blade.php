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
    <span class="logo"><span class="g">tkt</span><span class="w">house</span></span>
  </div>

  @isset($heroTitle)
  <div class="ehero">
    @isset($heroIcon)
      <span class="ehero-icon">{{ $heroIcon }}</span>
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
