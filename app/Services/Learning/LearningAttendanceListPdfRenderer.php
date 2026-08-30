<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAttendanceListPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Event\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Learning\LearningUnit;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\URL;

/**
 * Teilnehmerliste eines Präsenztermins als PDF (Feature 149, MVP-741).
 *
 * Zwei Wege auf einem Blatt: der **QR-Code** für den Selbst-Check-in und
 * die **Unterschriftenspalte** als Papier-Rückfall — Netz und Handy sind auf
 * einer Baustelle keine Selbstverständlichkeit.
 *
 * Die Liste ist ein Arbeitsmittel, **kein Nachweis**: nachgewiesen ist die
 * Teilnahme erst, wenn der Status `attended` steht.
 */
class LearningAttendanceListPdfRenderer {
    public function __construct(
        private readonly LearningEventService $events,
    ) {}

    public function output(LearningUnit $unit): string {
        $event = $unit->event;

        if ($event === null) {
            throw new \RuntimeException('Diese Lerneinheit hat keinen Termin.');
        }

        [, $until] = $this->events->checkInWindow($event);

        // Befristet signiert: der Code taugt nur rund um den Termin.
        $checkInUrl = URL::temporarySignedRoute(
            'learning.checkin.show',
            $until,
            ['unit' => $unit->sqid],
        );

        $participants = EventParticipant::query()
            ->with('user:id,name')
            ->where('event_id', $event->id)
            ->whereIn('status', [
                ParticipantStatus::Accepted->value,
                ParticipantStatus::Attended->value,
            ])
            ->get()
            ->sortBy(fn (EventParticipant $p): string => (string) ($p->user->name ?? ''))
            ->values();

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            'learning.pdf.attendance-list',
            [
                'unit' => $unit,
                'event' => $event,
                'participants' => $participants,
                'checkInUrl' => $checkInUrl,
                'qrImage' => $this->qrDataUri($checkInUrl),
            ],
            (int) $unit->organization_id,
        );
    }

    /** QR als eingebettetes SVG — kein externer Dienst, kein Netzzugriff. */
    private function qrDataUri(string $url): string {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd())))->writeString($url);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
