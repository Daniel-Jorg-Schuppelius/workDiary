<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Protocol;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Rendert ein signiertes Protokoll als PDF auf Storage und liefert den
 * relativen Pfad (MVP-022 §5).
 *
 * Idempotent: identische Revision + identischer Hash → identische Datei
 * (sie wird nur einmal geschrieben).
 */
class ProtocolPdfRenderer {
    public const DISK = 'local';

    public function __construct(private readonly ProtocolHasher $hasher) {}

    public function render(Protocol $protocol): string {
        $protocol->loadMissing(['items.photos.attachment', 'signatures', 'subject']);

        $canonical = $this->hasher->canonicalize($protocol);
        $hash = CryptoHelper::hash($canonical);
        $relativePath = $this->pathFor($protocol, $hash);

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($relativePath)) {
            return $relativePath;
        }

        // C15: gemeinsamer View→Design→PDF-Dreischritt (Dokumentdesign ohne Profil No-Op).
        // MVP-650: signierte Protokolle rendern mit ihrem eingefrorenen Designstand.
        $design = app(DocumentDesignRenderer::class);
        $bytes = $design->renderPdf(
            RenderDocumentKind::Protocol,
            'protocols.pdf',
            [
                'protocol' => $protocol,
                'hash' => $hash,
                'generatedAt' => Carbon::now(),
                // Vollaudit 2026-07 (H7): Foto-Vorschauen (max 4 je Punkt, als
                // data-URI) im Renderer vorberechnen — hält die Blade dumm und
                // vermeidet komplexe Inline-@php im PDF-Template.
                'itemPhotoPreviews' => $this->itemPhotoPreviews($protocol),
            ],
            (int) $protocol->organization_id,
            payload: $design->payloadFromSnapshot($protocol, RenderDocumentKind::Protocol),
        );

        $disk->put($relativePath, $bytes);

        return $relativePath;
    }

    /**
     * Foto-Vorschauen je Protokollpunkt (Vollaudit 2026-07, H7): max. 4 je
     * Punkt, nach Phase/Reihenfolge, als data-URI eingebettet plus Rest-Zähler.
     *
     * @return array<int, array{previews: list<array{src: ?string, phase: string, caption: ?string}>, more: int}>
     */
    private function itemPhotoPreviews(Protocol $protocol): array {
        $disk = Storage::disk(self::DISK);
        $out = [];

        foreach ($protocol->items as $item) {
            $photos = $item->photos
                ->sortBy(fn($p): string => $p->phase->value . '-' . str_pad((string) $p->sort_order, 6, '0', STR_PAD_LEFT))
                ->values();
            if ($photos->isEmpty()) {
                continue;
            }

            $previews = [];
            foreach ($photos->take(4) as $photo) {
                $att = $photo->attachment;
                $src = null;
                if ($att !== null && $disk->exists($att->path)) {
                    $src = 'data:' . $att->mime . ';base64,' . base64_encode((string) $disk->get($att->path));
                }
                $previews[] = [
                    'src' => $src,
                    'phase' => $photo->phase->label(),
                    'caption' => $photo->caption,
                ];
            }

            $out[(int) $item->id] = ['previews' => $previews, 'more' => max(0, $photos->count() - 4)];
        }

        return $out;
    }

    /**
     * Liefert den Hash, der zum aktuellen Inhalt gehoeren wuerde
     * (z. B. zur Verifikation nach dem Signieren).
     */
    public function hashFor(Protocol $protocol): string {
        return CryptoHelper::hash($this->hasher->canonicalize($protocol));
    }

    private function pathFor(Protocol $protocol, string $hash): string {
        $year = ($protocol->occurred_at ?? Carbon::now())->format('Y');
        return sprintf(
            'protocols/%d/%s/%d_r%d_%s.pdf',
            $protocol->organization_id,
            $year,
            $protocol->id,
            $protocol->revision,
            substr($hash, 0, 8),
        );
    }
}
