<?php

namespace App\Services\Statements;

use Mpdf\Mpdf;

/**
 * يحوّل بيانات الكشف إلى PDF عربي (تشكيل الحروف + RTL) عبر mPDF.
 * الخط XB Riyaz مضمّن مع mPDF؛ لا تنزيل خطوط أثناء التشغيل.
 */
class StatementPdfRenderer
{
    public const TITLES = [
        'flock' => 'كشف حساب الفوج',
        'partner' => 'كشف حساب شريك',
        'sale' => 'ورقة الوزن',
    ];

    public function render(string $type, array $data): string
    {
        $tempDir = storage_path('framework/cache/mpdf');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'xbriyaz',
            'directionality' => 'rtl',
            'tempDir' => $tempDir,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 30,
            'margin_bottom' => 18,
            'margin_header' => 8,
            'margin_footer' => 8,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $title = self::TITLES[$type];
        $generated = now('Asia/Damascus')->format('Y-m-d H:i');
        $mpdf->SetTitle($title.' — '.($data['farm_name'] ?? ''));
        $mpdf->SetAuthor('YMD');

        $mpdf->SetHTMLHeader(view('statements.header', ['title' => $title, 'farm' => $data['farm_name'] ?? ''])->render());
        $mpdf->SetHTMLFooter(view('statements.footer', ['generated' => $generated])->render());
        $mpdf->WriteHTML(view('statements.'.$type, ['d' => $data])->render());

        return $mpdf->Output('', 'S');
    }
}
