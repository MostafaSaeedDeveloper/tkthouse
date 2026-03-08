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
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 14px;">
        <tr>
          <td align="center" style="text-align:center;">
            <div class="ehero-icon">{{ $heroIcon }}</div>
          </td>
        </tr>
      </table>
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
