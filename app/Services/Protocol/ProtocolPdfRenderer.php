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

use App\Models\Protocol;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $protocol->loadMissing(['items', 'signatures', 'subject']);

        $canonical = $this->hasher->canonicalize($protocol);
        $hash = hash('sha256', $canonical);
        $relativePath = $this->pathFor($protocol, $hash);

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($relativePath)) {
            return $relativePath;
        }

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('protocols.pdf', [
            'protocol' => $protocol,
            'hash' => $hash,
            'generatedAt' => Carbon::now(),
        ])->setPaper('A4');

        $disk->put($relativePath, $pdf->output());

        return $relativePath;
    }

    /**
     * Liefert den Hash, der zum aktuellen Inhalt gehoeren wuerde
     * (z. B. zur Verifikation nach dem Signieren).
     */
    public function hashFor(Protocol $protocol): string {
        return hash('sha256', $this->hasher->canonicalize($protocol));
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
