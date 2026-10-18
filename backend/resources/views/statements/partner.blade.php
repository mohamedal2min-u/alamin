@php
    $m = fn ($v) => "\u{200E}".number_format((float) $v, 2); // LRM: علامة السالب تبقى قبل الرقم في RTL
@endphp
@include('statements._style')

<table class="kv">
    <tr>
        <td class="k">الشريك</td><td class="v">{{ $d['partner']['name'] }}</td>
        <td class="k">الرصيد الكلي ($)</td><td class="v {{ $d['total_balance'] >= 0 ? 'pos' : 'neg' }}">{{ $m($d['total_balance']) }}</td>
    </tr>
</table>

<h2>الرصيد حسب الفوج</h2>
<table class="grid">
    <tr><th>الفوج</th><th>الرصيد ($)</th></tr>
    @forelse ($d['per_flock'] as $r)
        <tr><td>{{ $r['flock'] ?? 'بلا فوج (حركات عامة)' }}</td><td class="{{ $r['balance'] >= 0 ? 'pos' : 'neg' }}">{{ $m($r['balance']) }}</td></tr>
    @empty
        <tr><td colspan="2">لا توجد حركات</td></tr>
    @endforelse
    <tr class="total"><td>كل الأفواج</td><td>{{ $m($d['total_balance']) }}</td></tr>
</table>

<h2>الحركات</h2>
<table class="grid">
    <tr><th>التاريخ</th><th>النوع</th><th>الفوج</th><th>البيان</th><th>المبلغ ($)</th><th>الرصيد ($)</th></tr>
    @forelse ($d['transactions'] as $t)
        <tr>
            <td>{{ $t['date'] }}</td>
            <td>{{ $t['type_label'] }}</td>
            <td>{{ $t['flock'] ?? '—' }}</td>
            <td>{{ $t['description'] ?? '' }}</td>
            <td class="{{ $t['signed_amount'] < 0 ? 'neg' : 'pos' }}">{{ $t['counted'] ? $m($t['signed_amount']) : $m($t['amount']).' *' }}</td>
            <td>{{ $m($t['balance']) }}</td>
        </tr>
    @empty
        <tr><td colspan="6">لا توجد حركات</td></tr>
    @endforelse
</table>
<div style="font-size: 8pt; color: #6b7280;">* حركة «تعديل» لا تدخل في الرصيد.</div>
