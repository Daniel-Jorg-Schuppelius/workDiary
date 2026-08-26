<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNoticeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Construction;

use App\Enums\Construction\ConstructionNoticeStatus;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Construction\ConstructionNotice;
use App\Models\{DiaryEntry, DocumentDispatch, Organization, Site, User};
use App\Services\Concerns\AssignsSequentialNo;
use App\Services\Weather\WeatherService;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Schreibstelle der VOB/B-Schreiben (H23, MVP-728). Zustaendig fuer Nummern,
 * Wetter-Snapshot am Anlasstag, die Festschreibung nach dem Versand und den
 * manuellen Zugangsnachweis (Einschreiben, Bote, persoenliche Uebergabe).
 *
 * Bewusst KEIN Fristautomatismus: `claims_time_extension` bleibt ein Vermerk am
 * Schreiben. Ob sich Bauzeit oder Gewaehrleistung verschieben, entscheiden die
 * Vertragsparteien — WorkDiary dokumentiert nur.
 */
class ConstructionNoticeService {
    use AssignsSequentialNo;

    /** Manuelle Zustellwege (Zugangsnachweis ausserhalb der E-Mail). */
    public const DELIVERY_METHODS = ['registered_mail', 'courier', 'handover', 'fax', 'portal'];

    public function __construct(private readonly WeatherService $weather) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Organization $organization, RenderDocumentKind $kind, array $data, ?User $actor = null): ConstructionNotice {
        $this->assertKind($kind);

        $notice = DB::transaction(function () use ($organization, $kind, $data, $actor): ConstructionNotice {
            return ConstructionNotice::create([
                'organization_id' => $organization->id,
                'notice_no' => $this->nextNo(ConstructionNotice::class, 'notice_no', 'organization_id', (int) $organization->id),
                'kind' => $kind,
                'status' => ConstructionNoticeStatus::Draft,
                'diary_entry_id' => $data['diary_entry_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_email' => $data['recipient_email'] ?? null,
                'subject' => (string) $data['subject'],
                'occurred_on' => $data['occurred_on'],
                'facts' => (string) $data['facts'],
                'impact_schedule' => $data['impact_schedule'] ?? null,
                'impact_cost' => $data['impact_cost'] ?? null,
                'claims_time_extension' => (bool) ($data['claims_time_extension'] ?? false),
                'legal_reference' => trim((string) ($data['legal_reference'] ?? '')) !== ''
                    ? (string) $data['legal_reference']
                    : ConstructionNotice::defaultLegalReference($kind),
                'created_by' => $actor?->id,
            ]);
        });

        $this->attachWeather($notice, $actor);

        return $notice->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ConstructionNotice $notice, array $data, ?User $actor = null): ConstructionNotice {
        $this->assertEditable($notice);

        $notice->fill([
            'diary_entry_id' => $data['diary_entry_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'subject' => (string) $data['subject'],
            'occurred_on' => $data['occurred_on'],
            'facts' => (string) $data['facts'],
            'impact_schedule' => $data['impact_schedule'] ?? null,
            'impact_cost' => $data['impact_cost'] ?? null,
            'claims_time_extension' => (bool) ($data['claims_time_extension'] ?? false),
            'legal_reference' => trim((string) ($data['legal_reference'] ?? '')) !== ''
                ? (string) $data['legal_reference']
                : ConstructionNotice::defaultLegalReference($notice->kind),
        ]);
        $notice->save();

        $this->attachWeather($notice, $actor);

        return $notice->refresh();
    }

    /**
     * Manueller Zugangsnachweis: Einschreiben, Bote, persoenliche Uebergabe,
     * Fax. Schreibt eine Zeile ins gemeinsame Dispatch-Log (Feature 128) und
     * schreibt das Schreiben damit fest — genau wie der E-Mail-Versand.
     *
     * @param  array{method: string, delivered_at: mixed, recipient: string, reference?: string|null}  $proof
     */
    public function recordManualDelivery(ConstructionNotice $notice, array $proof, string $pdfBytes, ?User $actor = null): DocumentDispatch {
        if (! in_array($proof['method'], self::DELIVERY_METHODS, true)) {
            throw new RuntimeException('Unbekannter Zustellweg: ' . $proof['method']);
        }

        $dispatch = DocumentDispatch::query()->create([
            'organization_id' => $notice->organization_id,
            'document_kind' => $notice->kind->value,
            'document_id' => (int) $notice->getKey(),
            'channel' => DocumentDispatch::CHANNEL_MANUAL,
            'format' => 'pdf',
            'status' => 'sent',
            'recipient' => $proof['recipient'],
            'sha256' => CryptoHelper::hash($pdfBytes),
            'meta' => array_filter([
                'method' => $proof['method'],
                'delivered_at' => Carbon::parse($proof['delivered_at'])->toIso8601String(),
                'reference' => $proof['reference'] ?? null,
            ]),
            'created_by' => $actor?->id,
        ]);

        $this->markSent($notice, Carbon::parse($proof['delivered_at']));
        $notice->audit($notice->kind->value . '.delivered', [
            'dispatch_id' => $dispatch->id,
            'method' => $proof['method'],
        ]);

        return $dispatch;
    }

    /** Erster Versand friert das Schreiben ein; spaetere Versande aendern nichts. */
    public function markSent(ConstructionNotice $notice, ?Carbon $at = null): ConstructionNotice {
        if ($notice->status === ConstructionNoticeStatus::Draft) {
            $notice->forceFill([
                'status' => ConstructionNoticeStatus::Sent,
                'sent_at' => $at ?? Carbon::now(),
            ])->save();
        }

        return $notice;
    }

    /** Eingangsbestaetigung des Auftraggebers vermerken. */
    public function acknowledge(ConstructionNotice $notice, ?string $note, ?User $actor = null): ConstructionNotice {
        if ($notice->status === ConstructionNoticeStatus::Draft) {
            throw new RuntimeException('Ein Entwurf kann keinen Zugang bestätigt bekommen.');
        }

        $notice->forceFill([
            'status' => ConstructionNoticeStatus::Acknowledged,
            'acknowledged_at' => Carbon::now(),
            'acknowledged_note' => $note !== null ? mb_substr($note, 0, 500) : null,
        ])->save();
        $notice->audit($notice->kind->value . '.acknowledged', ['user_id' => $actor?->id]);

        return $notice;
    }

    /** Nur Entwuerfe duerfen verschwinden — Versendetes bleibt nachweisbar. */
    public function delete(ConstructionNotice $notice): void {
        $this->assertEditable($notice);
        $notice->delete();
    }

    public function assertEditable(ConstructionNotice $notice): void {
        if (! $notice->isEditable()) {
            abort(422, (string) __('construction.error.frozen'));
        }
    }

    private function assertKind(RenderDocumentKind $kind): void {
        if (! in_array($kind, ConstructionNotice::KINDS, true)) {
            throw new RuntimeException('Belegart ist kein VOB/B-Schreiben: ' . $kind->value);
        }
    }

    /**
     * Wetterlage des Anlasstags — Messwert, keine Beobachtung. Faellt der
     * Dienst aus, bleibt das Feld leer und das Schreiben trotzdem moeglich.
     */
    private function attachWeather(ConstructionNotice $notice, ?User $actor): void {
        if ($notice->weather_snapshot_id !== null) {
            return; // Snapshot ist unveraenderlich.
        }

        $date = $notice->occurred_on;

        $snapshot = null;
        $site = $notice->site_id !== null ? Site::query()->find($notice->site_id) : null;
        if ($site instanceof Site) {
            $snapshot = $this->weather->snapshotForSite($site, $date, $actor);
        }

        if ($snapshot === null && $notice->diary_entry_id !== null) {
            $entry = DiaryEntry::query()->find($notice->diary_entry_id);
            $coords = $entry instanceof DiaryEntry ? $this->weather->coordsForDiaryEntry($entry) : null;
            $organization = $notice->organization;
            if ($coords !== null && $organization instanceof Organization) {
                $snapshot = $this->weather->snapshot($organization, $coords[0], $coords[1], $date, $actor);
            }
        }

        if ($snapshot !== null) {
            $notice->forceFill(['weather_snapshot_id' => $snapshot->id])->save();
        }
    }
}
