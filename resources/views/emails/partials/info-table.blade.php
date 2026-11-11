{{-- Label / value box for e-mails. A real table (not flexbox): Gmail and other mail apps ignore flexbox, which glued labels to their values.
     $rows: list of ['label' => string, 'value' => string, 'tone' => 'gold'|'green'|'red'|'white'|null, 'color' => '#hex' (optional, bold)]. Falsy rows are skipped. --}}
@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur'], true);
    $labelAlign = $rtl ? 'right' : 'left';
    $valueAlign = $rtl ? 'left' : 'right';
    $tones = ['gold' => ['#f5b800', 700], 'green' => ['#22c55e', 600], 'red' => ['#e8445a', 600], 'white' => ['#ffffff', 700]];
    $rows = array_values(array_filter($rows));
@endphp
<table class="einfo" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#15151e;border:1px solid #26263a;border-radius:10px;border-collapse:separate;margin:20px 0;">
  @foreach($rows as $row)
    @php
        [$color, $weight] = isset($row['color']) ? [$row['color'], 700] : ($tones[$row['tone'] ?? ''] ?? ['#dddde8', 500]);
        $line = $loop->last ? '' : 'border-bottom:1px solid #26263a;';
    @endphp
    <tr>
      <td width="40%" style="padding:12px 0;padding-{{ $labelAlign }}:18px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a8aa3;text-align:{{ $labelAlign }};vertical-align:top;{{ $line }}">{{ $row['label'] }}</td>
      <td style="padding:12px 18px 12px 12px;font-size:14px;line-height:1.5;color:{{ $color }};font-weight:{{ $weight }};text-align:{{ $valueAlign }};vertical-align:top;{{ $line }}">{{ $row['value'] }}</td>
    </tr>
  @endforeach
</table>
