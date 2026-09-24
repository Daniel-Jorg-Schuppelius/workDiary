<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolMirrorPdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Protocol\Mirror;

use App\Models\Protocol\Protocol;
use App\Plugins\Support\Mirror\Contracts\MirrorPdfRenderer;
use App\Services\Protocol\ProtocolPdfRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Der Renderer schreibt das PDF idempotent auf seine Disk und liefert den Pfad. */
final class ProtocolMirrorPdf implements MirrorPdfRenderer {
    public function __construct(private readonly ProtocolPdfRenderer $renderer) {}

    public function modelClass(): string {
        return Protocol::class;
    }

    public function pdf(Model $document): string {
        if (! $document instanceof Protocol) {
            throw new \InvalidArgumentException('Protocol erwartet.');
        }

        return (string) Storage::disk(ProtocolPdfRenderer::DISK)->get($this->renderer->render($document));
    }
}
