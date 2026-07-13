<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavContactImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

use App\Enums\Integration\{ConflictFieldPolicy, ImportMatchPolicy};
use App\Models\{CardDavCard, CardDavConnection, IntegrationInboxItem, Organization};
use App\Plugins\CardDav\CardDavPlugin;
use App\Plugins\CardDav\Contracts\CardDavGatewayFactory;
use App\Services\Integration\IntegrationResolver;
use App\Services\Integration\Profiles\CustomerMatchProfile;
use Throwable;

/**
 * Lese-Sync der CardDAV-Kontakte als Matching-Quelle (Bauturbo A9, MVP-329).
 *
 * Inbox-First (MVP-103): jede Karte läuft durch den {@see IntegrationResolver}
 * — eindeutige Treffer werden nur VERLINKT (ExternalReference), alles andere
 * landet als Zuordnungsvorschlag in der Integrations-Inbox. Es gibt KEIN
 * Auto-Merge und KEIN Direkt-Schreiben auf Kundenfelder
 * (ConflictFieldPolicy::ManualReview) und keine Neuanlage.
 *
 * Idempotenz über UID+ETag: der lokale Spiegel ({@see CardDavCard}) speist den
 * ETag-Fallback der Client-Lib und überspringt unveränderte Karten; wiederholte
 * Läufe erzeugen keine Duplikate. Remote gelöschte Karten räumen nur den
 * Spiegel und offene Inbox-Vorschläge ab — lokale Kunden bleiben unberührt
 * (keine Löschweitergabe, wie Todoist).
 */
class CardDavContactImporter {
    public const EXT_TYPE_CONTACT = 'contact';

    /** Quell-Kennung in der Inbox (`source`-Spalte). */
    public const SOURCE = 'carddav';

    public function __construct(
        private readonly CardDavGatewayFactory $factory,
        private readonly IntegrationResolver $resolver,
        private readonly VCardContactMapper $mapper,
    ) {}

    /**
     * Synchronisiert alle sync-fähigen Anbindungen der Organisation.
     *
     * @return array{connections: int, changed: int, linked: int, staged: int, skipped: int, deleted: int, failed: int}
     */
    public function sync(Organization $organization): array {
        $counters = ['connections' => 0, 'changed' => 0, 'linked' => 0, 'staged' => 0, 'skipped' => 0, 'deleted' => 0, 'failed' => 0];

        $connections = CardDavConnection::query()
            ->where('organization_id', $organization->id)
            ->get();

        foreach ($connections as $connection) {
            if (! $connection->isSyncable()) {
                continue;
            }
            $counters['connections']++;

            try {
                $result = $this->syncConnection($organization, $connection);
                foreach (['changed', 'linked', 'staged', 'skipped', 'deleted'] as $key) {
                    $counters[$key] += $result[$key];
                }
                $connection->recordConnectionSuccess();
            } catch (Throwable $e) {
                // Fehler zählt in die Verbindungs-Gesundheit (Auto-Disable ab
                // Schwellwert, ExpiryScanner meldet die Störung als Betriebsaufgabe).
                $connection->recordConnectionFailure($e->getMessage());
                $counters['failed']++;
            }
        }

        return $counters;
    }

    /**
     * @return array{changed: int, linked: int, staged: int, skipped: int, deleted: int}
     */
    private function syncConnection(Organization $organization, CardDavConnection $connection): array {
        $result = ['changed' => 0, 'linked' => 0, 'staged' => 0, 'skipped' => 0, 'deleted' => 0];

        $gateway = $this->factory->for($connection);

        /** @var array<string, string> $localEtags href → etag (ETag-Fallback + Lösch-Erkennung) */
        $localEtags = CardDavCard::query()
            ->where('carddav_connection_id', $connection->id)
            ->pluck('etag', 'href')
            ->all();

        $page = $gateway->syncAddressbook(
            (string) $connection->addressbook_url,
            (string) $connection->sync_token,
            $localEtags,
        );

        $profile = app(CustomerMatchProfile::class);

        foreach ($page->changed as $change) {
            // Idempotenz-Kurzschluss: unveränderte Karte (gleicher ETag) überspringen.
            if (($localEtags[$change->href] ?? null) === $change->etag) {
                $result['skipped']++;

                continue;
            }

            if ($change->vcard === null) {
                // Abruf-/Parse-Fehler der Lib: Karte auslassen, ETag NICHT
                // fortschreiben — der nächste Lauf versucht sie erneut.
                $result['skipped']++;

                continue;
            }

            $parsed = $this->mapper->map($change->vcard, $this->uidFromHref($change->href));
            $result['changed']++;

            $outcome = $this->resolver->resolve(
                $organization,
                CardDavPlugin::ID,
                $profile,
                self::EXT_TYPE_CONTACT,
                $parsed['uid'],
                $parsed['mapped'],
                $parsed['raw'],
                ImportMatchPolicy::AutoLinkExactOnly,
                ConflictFieldPolicy::ManualReview,
                source: self::SOURCE,
            );
            $result[$outcome->isResolved() ? 'linked' : 'staged']++;

            CardDavCard::query()->updateOrCreate(
                ['carddav_connection_id' => $connection->id, 'href' => $change->href],
                [
                    'organization_id' => $organization->id,
                    'uid' => $parsed['uid'],
                    'etag' => $change->etag,
                ],
            );
        }

        foreach ($page->deleted as $href) {
            $result['deleted'] += $this->forgetCard($organization, $connection, $href);
        }

        $connection->forceFill([
            'sync_token' => $page->syncToken !== '' ? $page->syncToken : null,
            'last_synced_at' => now(),
        ])->save();

        return $result;
    }

    /**
     * Remote gelöschte Karte: Spiegel-Zeile entfernen und noch OFFENE
     * Zuordnungsvorschläge verwerfen (aufgelöste Items bleiben unangetastet,
     * lokale Kunden sowieso — keine Löschweitergabe).
     */
    private function forgetCard(Organization $organization, CardDavConnection $connection, string $href): int {
        $card = CardDavCard::query()
            ->where('carddav_connection_id', $connection->id)
            ->where('href', $href)
            ->first();

        if (! $card instanceof CardDavCard) {
            return 0;
        }

        if ($card->uid !== null && $card->uid !== '') {
            IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', CardDavPlugin::ID)
                ->where('dedupe_key', self::EXT_TYPE_CONTACT . ':' . $card->uid)
                ->where('status', IntegrationInboxItem::STATUS_OPEN)
                ->update([
                    'status' => IntegrationInboxItem::STATUS_DISMISSED,
                    'resolved_at' => now(),
                ]);
        }

        $card->delete();

        return 1;
    }

    /** Fallback-UID für Karten ohne UID-Property: Objektname aus dem href. */
    private function uidFromHref(string $href): string {
        $base = basename(rtrim($href, '/'));

        return $base !== '' ? $base : $href;
    }
}
