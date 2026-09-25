<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabelController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Inventory;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockSerial};
use App\Services\Inventory\LabelService;
use App\Services\Print\LabelPdfRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Etikettendruck (Feature 048, E5): erzeugt ein druckbares Etikett (PDF) für
 * Variante/Charge/Seriennummer aus den {@see LabelService}-Daten. Sehen mit
 * inventory.viewAny oder inventory.post.
 */
class LabelController extends Controller {
    public function __construct(
        private readonly LabelService $labels,
        private readonly LabelPdfRenderer $renderer,
    ) {}

    public function variant(ArticleVariant $variant): Response {
        return $this->pdf($this->labels->forVariant($variant));
    }

    public function serial(StockSerial $stockSerial): Response {
        return $this->pdf($this->labels->forSerial($stockSerial));
    }

    public function lot(StockLot $stockLot): Response {
        return $this->pdf($this->labels->forLot($stockLot));
    }

    /** @param array{code: string, code_type: string, title: string, subtitle: ?string, lines: list<string>} $data */
    private function pdf(array $data): Response {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryPost->value) ?? false), 403);

        $bytes = $this->renderer->render($data, null, request()->string('template')->toString());

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . $data['code'] . '.pdf"',
        ]);
    }
}
