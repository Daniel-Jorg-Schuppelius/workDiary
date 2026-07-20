<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabelController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Models\{ArticleVariant, LabelTemplate, Organization, StockLot, StockSerial};
use App\Services\Inventory\LabelService;
use App\Services\SqidEncoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Etikettendruck (Feature 048, E5): erzeugt ein druckbares Etikett (PDF) für
 * Variante/Charge/Seriennummer aus den {@see LabelService}-Daten. Sehen mit
 * inventory.viewAny oder inventory.post.
 */
class LabelController extends Controller {
    public function __construct(private readonly LabelService $labels) {}

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

        $template = $this->resolveTemplate();
        if ($template instanceof LabelTemplate) {
            $paper = $template->paper_size;
            $orientation = $template->orientation;
            $withQr = $template->with_qr;
            $fields = $template->fields;
        } else {
            // Fallback: leichtgewichtige Org-Konfiguration (settings.label).
            $config = app()->bound('currentOrganization') && app('currentOrganization') instanceof Organization
                ? (array) data_get(app('currentOrganization')->settings, 'label', [])
                : [];
            $paper = isset($config['paper_size']) && is_string($config['paper_size']) && $config['paper_size'] !== '' ? $config['paper_size'] : 'a7';
            $orientation = 'landscape';
            $withQr = ($config['with_qr'] ?? true) !== false;
            $fields = LabelTemplate::FIELDS;
        }

        // View→PDF über den zentralen Renderer (C15; Vollaudit 2026-07, N27) —
        // Writer-Options (Papierformat der Etikettenvorlage) werden durchgereicht.
        $bytes = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->renderPdf(
            \App\Enums\DocumentDesign\RenderDocumentKind::Report,
            'inventory.labels.label',
            [
                'label' => $data,
                'qr' => $withQr ? $this->qrDataUri($data['code']) : null,
                'fields' => $fields,
            ],
            null,
            ['paper_size' => $paper, 'orientation' => $orientation],
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . $data['code'] . '.pdf"',
        ]);
    }

    /** Wählt die Etikettenvorlage: ?template (Sqid) oder die Standardvorlage der Org. */
    private function resolveTemplate(): ?LabelTemplate {
        $sqid = request()->string('template')->toString();
        if ($sqid !== '') {
            $id = app(SqidEncoder::class)->decode(LabelTemplate::class, $sqid);

            return $id !== null ? LabelTemplate::query()->find($id) : null;
        }

        return LabelTemplate::query()->where('is_default', true)->first();
    }

    /** Erzeugt einen scannbaren QR-Code als SVG-Data-URI für das Etikett. */
    private function qrDataUri(string $value): string {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(120, 1), new SvgImageBackEnd())))->writeString($value);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
