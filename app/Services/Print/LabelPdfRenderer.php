<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabelPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Print;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Platform\Organization;
use App\Models\Print\LabelTemplate;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Support\SqidEncoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use CommonToolkit\Helper\Data\DataUrlHelper;

/**
 * Drucketikett als PDF (Feature 048 E5, MVP-882): Lager und Objekte teilen
 * Vorlage, Layout und QR. Der QR trägt den Code oder — bei Objekten — die
 * URL, die ein Handy direkt öffnet.
 *
 * @phpstan-type LabelData array{code: string, code_type: string, title: string, subtitle: ?string, lines: list<string>}
 */
final class LabelPdfRenderer {
    public function __construct(
        private readonly DocumentDesignRenderer $renderer,
        private readonly SqidEncoder $sqids,
    ) {}

    /** @param LabelData $label */
    public function render(array $label, ?string $qrValue = null, ?string $templateSqid = null): string {
        $template = $this->template($templateSqid);
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

        // #83: registriertes Spezialformat ohne Firmenbogen/Basisdesign
        // (siehe RenderDocumentKind::capabilityNote()).
        return $this->renderer->renderPdf(
            RenderDocumentKind::Label,
            'inventory.labels.label',
            [
                'label' => $label,
                'qr' => $withQr ? $this->qrDataUri($qrValue ?? $label['code']) : null,
                'fields' => $fields,
            ],
            null,
            ['paper_size' => $paper, 'orientation' => $orientation],
        );
    }

    /** Vorlage per Sqid, sonst die Standardvorlage der Organisation. */
    private function template(?string $sqid): ?LabelTemplate {
        if ($sqid !== null && $sqid !== '') {
            $id = $this->sqids->decode(LabelTemplate::class, $sqid);

            return $id !== null ? LabelTemplate::query()->find($id) : null;
        }

        return LabelTemplate::query()->where('is_default', true)->first();
    }

    private function qrDataUri(string $value): string {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(120, 1), new SvgImageBackEnd())))->writeString($value);

        return (string) DataUrlHelper::encode($svg, 'image/svg+xml');
    }
}
