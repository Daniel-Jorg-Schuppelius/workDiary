<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolPdfController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Protocol\{ProtocolPdfRenderer, ProtocolService};
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\{Auth, Storage};
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Protokoll-PDF in Objektakte und Fallakte des Portals (Phase 137, E17): nur
 * unterschriebene, kundensichtbare Protokolle des eigenen Objekts bzw.
 * Auftrags, sonst 404. Jeder Abruf steht im Verlauf des Protokolls.
 */
class ProtocolPdfController extends Controller {
    public function __construct(
        private readonly ProtocolPdfRenderer $renderer,
        private readonly ProtocolService $protocols,
    ) {}

    public function asset(Asset $asset, Protocol $protocol): StreamedResponse {
        return $this->download($asset, $protocol);
    }

    public function diary(DiaryEntry $diary, Protocol $protocol): StreamedResponse {
        return $this->download($diary, $protocol);
    }

    private function download(Asset|DiaryEntry $subject, Protocol $protocol): StreamedResponse {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        // Ohne Organisationskontext greift kein Scope: Zugehörigkeit ausdrücklich prüfen.
        abort_unless(
            (int) $subject->customer_id === (int) $user->customer_id
            && (int) $subject->organization_id === (int) $user->organization_id
            && $subject->protocols()->releasedToCustomer()->whereKey($protocol->getKey())->exists(),
            404,
        );

        $path = $this->renderer->render($protocol);
        $this->protocols->recordPortalPdfDownload($protocol, $user);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(ProtocolPdfRenderer::DISK);

        return $disk->download($path, sprintf('protokoll-%s-r%d.pdf', $protocol->getRouteKey(), $protocol->revision));
    }
}
