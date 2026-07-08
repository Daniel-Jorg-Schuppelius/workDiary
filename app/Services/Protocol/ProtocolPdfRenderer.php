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
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

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
        $hash = CryptoHelper::hash($canonical);
        $relativePath = $this->pathFor($protocol, $hash);

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($relativePath)) {
            return $relativePath;
        }

        $html = view('protocols.pdf', [
            'protocol' => $protocol,
            'hash' => $hash,
            'generatedAt' => Carbon::now(),
        ])->render();

        $bytes = PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen (protocols.pdf).');

        $disk->put($relativePath, $bytes);

        return $relativePath;
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
