@php
    $m = fn ($v) => "\u{200E}".number_format((float) $v, 2); // LRM: علامة السالب تبقى قبل الرقم في RTL
    $w = fn ($v) => $v === null ? '—' : number_format((float) $v, 1);
    $s = $d['sale'];
@endphp
@include('statements._style')

<table class="kv">
    <tr><td class="k">رقم البيع</td><td class="v">#{{ $s['id'] }}{{ $s['reference_no'] ? ' / '.$s['reference_no'] : '' }}</td><td class="k">التاريخ</td><td class="v">{{ $s['date'] }}</td></tr>
    <tr><td class="k">المشتري</td><td class="v">{{ $s['buyer'] ?? '—' }}</td><td class="k">الفوج</td><td class="v">{{ $s['flock'] ?? '—' }}</td></tr>
    @if ($s['vehicle_before_kg'] !== null || $s['vehicle_after_kg'] !== null)
        <tr><td class="k">قبان السيارة فارغة (كغ)</td><td class="v">{{ $w($s['vehicle_before_kg']) }}</td><td class="k">قبان السيارة محمّلة (كغ)</td><td class="v">{{ $w($s['vehicle_after_kg']) }}</td></tr>
    @endif
    @if ($s['deduction_kg'])
        <tr><td class="k">خصم وزن (كغ)</td><td class="v">{{ $w($s['deduction_kg']) }}</td><td class="k"></td><td class="v"></td></tr>
    @endif
</table>

<h2>الوزنات</h2>
<table class="grid">
    <tr>
        <th>#</th><th>الطيور</th><th>الأقفاص</th><th>القائم (كغ)</th><th>الفارغ (كغ)</th><th>الصافي (كغ)</th>
        @if ($d['priced'])<th>سعر الكيلو ($)</th><th>المبلغ ($)</th>@endif
    </tr>
    @foreach ($d['lines'] as $i => $l)
        <tr>
            <td>{{ $i + 1 }}</td><td>{{ number_format($l['birds']) }}</td><td>{{ $l['crates'] ?? '—' }}</td>
            <td>{{ $w($l['gross_kg']) }}</td><td>{{ $w($l['tare_kg']) }}</td><td>{{ $w($l['net_kg']) }}</td>
            @if ($d['priced'])<td>{{ $m($l['price_per_kg']) }}</td><td>{{ $m($l['line_total']) }}</td>@endif
        </tr>
    @endforeach
    <tr class="total">
        <td>المجموع</td><td>{{ number_format($d['totals']['birds']) }}</td><td>{{ $d['totals']['crates'] ?: '—' }}</td>
        <td>{{ $w($d['totals']['gross_kg']) }}</td><td>{{ $w($d['totals']['tare_kg']) }}</td><td>{{ $w($d['totals']['net_kg']) }}</td>
        @if ($d['priced'])<td></td><td>{{ $m($d['gross_amount']) }}</td>@endif
    </tr>
</table>

@if ($d['priced'])
    <table class="grid" style="width: 50%;">
        @if ($d['discount_amount'] > 0)
            <tr><td>الخصم</td><td>{{ $m($d['discount_amount']) }}</td></tr>
        @endif
        <tr class="total"><td>الصافي المستحق</td><td>{{ $m($d['net_amount']) }}</td></tr>
        <tr><td>المستلم</td><td class="pos">{{ $m($d['received_amount']) }}</td></tr>
        <tr><td>المتبقي</td><td class="{{ $d['remaining_amount'] > 0 ? 'neg' : '' }}">{{ $m($d['remaining_amount']) }}</td></tr>
    </table>
@else
    <div class="note">لم يُسعَّر البيع بعد — يحدد المحاسب سعر الكيلو.</div>
@endif
