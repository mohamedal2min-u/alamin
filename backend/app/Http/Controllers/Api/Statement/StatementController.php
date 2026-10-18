<?php

namespace App\Http\Controllers\Api\Statement;

use App\Http\Controllers\Controller;
use App\Models\Flock;
use App\Models\Partner;
use App\Models\Sale;
use App\Services\Statements\StatementDataBuilder;
use App\Services\Statements\StatementPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * كشوف قابلة للمشاركة (PDF): كل طلب مصادَق يعيد رابطاً موقّعاً قصير العمر (30 دقيقة)
 * يُفتح في المتصفح أو يُرسل على واتساب بلا ترويسات مصادقة.
 */
class StatementController extends Controller
{
    private const TTL_MINUTES = 30;

    public function __construct(
        private readonly StatementDataBuilder $builder,
        private readonly StatementPdfRenderer $renderer,
    ) {}

    // GET /api/statements/flock/{flock}
    public function flock(Request $request, int $flock): JsonResponse
    {
        $farmId = (int) $request->attributes->get('farm_id');
        Flock::where('farm_id', $farmId)->findOrFail($flock);

        return $this->link('flock', $flock, $farmId);
    }

    // GET /api/statements/partner/{partner} — الشريك يطلب كشفه وحده
    public function partner(Request $request, int $partner): JsonResponse
    {
        $farmId = (int) $request->attributes->get('farm_id');
        $model = Partner::where('farm_id', $farmId)->findOrFail($partner);

        $isStaff = $request->attributes->get('is_super_admin') === true
            || $request->user()->hasAnyRole(['farm_admin', 'accountant']);
        if (! $isStaff && $model->user_id !== $request->user()->id) {
            abort(403, 'غير مصرح بالوصول إلى هذا المورد');
        }

        return $this->link('partner', $partner, $farmId);
    }

    // GET /api/statements/sale/{sale}
    public function sale(Request $request, int $sale): JsonResponse
    {
        $farmId = (int) $request->attributes->get('farm_id');
        Sale::where('farm_id', $farmId)->findOrFail($sale);

        return $this->link('sale', $sale, $farmId);
    }

    // GET /api/statements/download/{type}/{id}?farm=..&expires=..&signature=..
    // بلا مصادقة: التوقيع يحمي النوع والمعرّف والمزرعة ووقت الانتهاء
    public function download(Request $request, string $type, int $id): Response
    {
        $farmId = (int) $request->query('farm');

        $data = match ($type) {
            'flock' => $this->builder->flock(Flock::where('farm_id', $farmId)->findOrFail($id)),
            'partner' => $this->builder->partner(Partner::where('farm_id', $farmId)->findOrFail($id)),
            'sale' => $this->builder->sale(Sale::where('farm_id', $farmId)->findOrFail($id)),
        };

        $pdf = $this->renderer->render($type, $data);
        $filename = "{$type}-statement-{$id}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function link(string $type, int $id, int $farmId): JsonResponse
    {
        // توقيع نسبي (بلا النطاق): يعمل خلف الوكيل العكسي، والواجهة تُكمل الرابط بعنوان الـ API
        $path = URL::temporarySignedRoute(
            'statements.download',
            now()->addMinutes(self::TTL_MINUTES),
            ['type' => $type, 'id' => $id, 'farm' => $farmId],
            absolute: false,
        );

        return response()->json([
            'url' => url($path),
            'path' => $path,
            'expires_in' => self::TTL_MINUTES * 60,
        ]);
    }
}
