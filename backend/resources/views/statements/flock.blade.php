@php
    $m = fn ($v) => "\u{200E}".number_format((float) $v, 2); // LRM: علامة السالب تبقى قبل الرقم في RTL
    $f = $d['flock'];
    $c = $d['costs'];
    $statusLabel = ['active' => 'نشط', 'closed' => 'مغلق', 'cancelled' => 'ملغى'][$f['status']] ?? $f['status'];
    $bal = collect($d['balances'])->keyBy('partner_id');
@endphp
@include('statements._style')

<h2>بيانات الفوج</h2>
<table class="kv">
    <tr><td class="k">الفوج</td><td class="v">{{ $f['name'] }}</td><td class="k">الحالة</td><td class="v">{{ $statusLabel }}</td></tr>
    <tr><td class="k">تاريخ البدء</td><td class="v">{{ $f['start_date'] }}</td><td class="k">تاريخ الإغلاق</td><td class="v">{{ $f['close_date'] ?? '—' }}</td></tr>
    <tr><td class="k">العمر (يوم)</td><td class="v">{{ $f['age_days'] ?? '—' }}</td><td class="k">عدد الصيصان</td><td class="v">{{ number_format($f['initial_count']) }}</td></tr>
    <tr><td class="k">النفوق</td><td class="v">{{ number_format($f['mortality']) }} ({{ $f['mortality_rate'] }}%)</td><td class="k">المباع (طير)</td><td class="v">{{ number_format($f['sold_birds']) }}</td></tr>
    <tr><td class="k">الوزن المباع (كغ)</td><td class="v">{{ number_format($f['sold_weight_kg'], 1) }}</td><td class="k">متوسط وزن الطير (كغ)</td><td class="v">{{ $f['avg_weight_kg'] !== null ? number_format($f['avg_weight_kg'], 3) : '—' }}</td></tr>
    <tr><td class="k">المتبقي</td><td class="v">{{ number_format($f['remaining']) }}</td><td class="k"></td><td class="v"></td></tr>
</table>

@if ($d['unpriced_count'] > 0)
    <div class="note">تنبيه: يوجد {{ $d['unpriced_count'] }} سجل بلا سعر — الأرقام غير نهائية.</div>
@endif

<h2>الإيرادات (المبيعات)</h2>
<table class="grid">
    <tr><th>التاريخ</th><th>المشتري</th><th>عدد الطيور</th><th>الوزن الصافي (كغ)</th><th>المبلغ ($)</th></tr>
    @forelse ($d['sales'] as $s)
        <tr><td>{{ $s['date'] }}</td><td>{{ $s['buyer'] ?? '—' }}</td><td>{{ number_format($s['birds']) }}</td><td>{{ number_format($s['weight_kg'], 1) }}</td><td>{{ $m($s['amount']) }}</td></tr>
    @empty
        <tr><td colspan="5">لا توجد مبيعات</td></tr>
    @endforelse
    <tr class="total"><td colspan="4">إجمالي الإيرادات</td><td>{{ $m($d['revenue']) }}</td></tr>
</table>

<h2>التكاليف</h2>
<table class="grid">
    <tr><th>البند</th><th>المبلغ ($)</th></tr>
    <tr><td>الصيصان</td><td>{{ $m($c['chicks']) }}</td></tr>
    <tr><td>العلف</td><td>{{ $m($c['feed']) }}</td></tr>
    <tr><td>الأدوية</td><td>{{ $m($c['medicine']) }}</td></tr>
    <tr><td>الماء</td><td>{{ $m($c['water']) }}</td></tr>
    @if ($c['other_inventory'] != 0)
        <tr><td>مستهلكات أخرى من المخزون</td><td>{{ $m($c['other_inventory']) }}</td></tr>
    @endif
    @foreach ($c['other_by_category'] as $name => $amount)
        <tr><td>مصاريف أخرى: {{ $name }}</td><td>{{ $m($amount) }}</td></tr>
    @endforeach
    <tr class="total"><td>إجمالي التكاليف</td><td>{{ $m($d['total_expenses']) }}</td></tr>
</table>

<table class="kv" style="margin: 6pt 0;">
    <tr>
        <td class="k big">{{ $d['net'] >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}</td>
        <td class="big {{ $d['net'] >= 0 ? 'pos' : 'neg' }}">{{ $m($d['net']) }} $</td>
    </tr>
</table>

<h2>حصص الشركاء {{ $d['recorded'] ? '(مسجّلة عند الإغلاق)' : '(تقديرية حتى الإغلاق)' }}</h2>
<table class="grid">
    <tr><th>الشريك</th><th>النسبة</th><th>المبلغ ($)</th><th>رصيده في الفوج ($)</th><th>رصيده الكلي ($)</th></tr>
    @forelse ($d['partners'] as $p)
        <tr>
            <td>{{ $p['name'] ?? '—' }}</td>
            <td>{{ rtrim(rtrim(number_format($p['percent'], 2), '0'), '.') }}%</td>
            <td class="{{ $p['amount'] >= 0 ? 'pos' : 'neg' }}">{{ $m($p['amount']) }}</td>
            <td>{{ isset($bal[$p['partner_id']]) ? $m($bal[$p['partner_id']]['flock_balance']) : '—' }}</td>
            <td>{{ isset($bal[$p['partner_id']]) ? $m($bal[$p['partner_id']]['total_balance']) : '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="5">لا توجد حصص شركاء</td></tr>
    @endforelse
</table>
