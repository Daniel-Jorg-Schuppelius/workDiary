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
use App\Models\Protocol\Protocol;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\Data\{CryptoHelper, DataUrlHelper};
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
    public const string DISK = 'local';

    public function __construct(private readonly ProtocolHasher $hasher) {}

    /** Schreibt das PDF idempotent auf die Disk und liefert den Pfad — für Nachweise (unterschrieben, archiviert, ersetzt). */
    public function render(Protocol $protocol): string {
        [$hash, $relativePath] = $this->identify($protocol);

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($relativePath)) {
            $disk->put($relativePath, $this->bytes($protocol, $hash));
        }

        return $relativePath;
    }

    /**
     * PDF-Inhalt zum Ausliefern. Entwürfe und Protokolle in Prüfung entstehen
     * bei jedem Abruf neu und bleiben nicht liegen: ihre Dateien stünden in
     * keinem Löschregister.
     */
    public function content(Protocol $protocol): string {
        if ($protocol->status->isImmutable()) {
            return (string) Storage::disk(self::DISK)->get($this->render($protocol));
        }
        [$hash] = $this->identify($protocol);

        return $this->bytes($protocol, $hash);
    }

    /** Räumt früher abgelegte Fassungen dieser Revision weg; `$keep` und angehängte PDFs bleiben. */
    public function discardSuperseded(Protocol $protocol, string $keep): void {
        $disk = Storage::disk(self::DISK);
        $prefix = sprintf('%d_r%d_', $protocol->id, $protocol->revision);
        $attached = $protocol->attachments()->where('meta_type', Protocol::META_PDF)->pluck('path')->all();

        foreach ($disk->files(dirname($keep)) as $file) {
            if ($file !== $keep && str_starts_with(basename($file), $prefix) && ! in_array($file, $attached, true)) {
                $disk->delete($file);
            }
        }
    }

    /** @return array{0: string, 1: string} Inhalts-Hash und Pfad */
    private function identify(Protocol $protocol): array {
        $protocol->loadMissing(['items.photos.attachment', 'signatures.signatureAttachment', 'subject']);
        $hash = CryptoHelper::hash($this->hasher->canonicalize($protocol));

        return [$hash, $this->pathFor($protocol, $hash)];
    }

    private function bytes(Protocol $protocol, string $hash): string {
        // C15: gemeinsamer View→Design→PDF-Dreischritt (Dokumentdesign ohne Profil No-Op).
        // MVP-650: signierte Protokolle rendern mit ihrem eingefrorenen Designstand.
        $design = app(DocumentDesignRenderer::class);
        $organization = \App\Models\Platform\Organization::query()->withoutGlobalScopes()->find($protocol->organization_id);
        $payload = $design->payloadFromSnapshot($protocol, RenderDocumentKind::Protocol)
            ?? ($organization !== null ? $design->payloadFor($organization, RenderDocumentKind::Protocol) : null);

        return $design->renderPdf(
            RenderDocumentKind::Protocol,
            'protocols.pdf',
            [
                'protocol' => $protocol,
                'hash' => $hash,
                'generatedAt' => Carbon::now(),
                // Aussteller nach Blockzustand (MVP-1624); unterschriebene Altprotokolle bleiben, wie sie waren.
                'design' => $design->contextForDocument($protocol, RenderDocumentKind::Protocol, $payload, finalized: $protocol->signed_at !== null),
                'issuer' => \App\Services\DocumentDesign\IssuerIdentity::for($organization),
                // Vollaudit 2026-07 (H7): Foto-Vorschauen (max 4 je Punkt, als
                // data-URI) im Renderer vorberechnen — hält die Blade dumm und
                // vermeidet komplexe Inline-@php im PDF-Template.
                'itemPhotoPreviews' => $this->itemPhotoPreviews($protocol),
                'signatureImages' => $this->signatureImages($protocol),
            ],
            $organization,
            payload: $payload,
        );
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
                    $src = DataUrlHelper::encode((string) $disk->get($att->path), $att->mime) ?: null;
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
     * Unterschriftsbilder als data-URI je Signatur (MVP-1572). Quelle ist nur
     * der Anhang am Protokoll; ein PDF-Scan der Papierabnahme bleibt Anhang
     * und wird nicht eingebettet.
     *
     * @return array<int, string>
     */
    private function signatureImages(Protocol $protocol): array {
        $out = [];
        foreach ($protocol->signatures as $signature) {
            $attachment = $signature->signatureAttachment;
            if ($attachment === null || ! in_array($attachment->mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                continue;
            }
            $disk = Storage::disk($attachment->disk ?: self::DISK);
            if (! $disk->exists($attachment->path)) {
                continue;
            }
            $src = DataUrlHelper::encode((string) $disk->get($attachment->path), $attachment->mime);
            if ($src) {
                $out[(int) $signature->id] = $src;
            }
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
        // Unterschriften stehen im PDF, aber nicht im Inhalts-Hash: ohne eigenes
        // Kennzeichen bliebe die vor der Unterschrift erzeugte Datei stehen.
        $signatures = $protocol->signatures->isEmpty()
            ? ''
            : '_s' . substr(CryptoHelper::hash($protocol->signatures->sortBy('id')->pluck('hash')->implode('|')), 0, 6);

        return sprintf(
            'protocols/%d/%s/%d_r%d_%s%s.pdf',
            $protocol->organization_id,
            $year,
            $protocol->id,
            $protocol->revision,
            substr($hash, 0, 8),
            $signatures,
        );
    }
}
