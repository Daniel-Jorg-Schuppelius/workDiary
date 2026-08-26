<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolInboundService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Peppol;

use App\Models\{Organization, User};
use App\Plugins\Contracts\PeppolTransportProvider;
use App\Plugins\PluginManager;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use ERechnungToolkit\Contracts\AccessPointClientInterface;
use ERechnungToolkit\Peppol\InboundDocument;
use Throwable;

/**
 * Peppol-Eingang (Feature 066, MVP-734).
 *
 * Der Abhol-Endpunkt des Providers ist nur eine weitere **Herkunft** — die
 * Verarbeitung ist dieselbe kanalneutrale Strecke wie bei Upload, Mail und
 * Cloud-Eingang ({@see IncomingEInvoiceService::storeIncoming()} mit
 * `source = 'peppol'`). Es gibt hier bewusst keine zweite Parser-, Dedup- oder
 * Ablage-Logik.
 *
 * **Quittiert wird erst nach der Übernahme.** Ein nicht lesbares Dokument
 * bleibt beim Provider liegen und wird beim nächsten Lauf erneut geliefert —
 * lieber ein wiederholter Eingang als ein verlorener Beleg. Dubletten
 * (SHA-256) gelten dagegen als übernommen und werden quittiert, sonst käme
 * dasselbe Dokument endlos wieder.
 *
 * @phpstan-type PeppolInboundCounters array{fetched: int, imported: int, duplicates: int, unreadable: int, acknowledged: int, failed: int}
 */
class PeppolInboundService {
    public function __construct(private readonly IncomingEInvoiceService $invoices) {}

    /**
     * @return PeppolInboundCounters
     */
    public function poll(Organization $organization, int $limit = 50): array {
        $counters = ['fetched' => 0, 'imported' => 0, 'duplicates' => 0, 'unreadable' => 0, 'acknowledged' => 0, 'failed' => 0];

        $client = $this->client((int) $organization->id);
        if (! $client instanceof AccessPointClientInterface) {
            return $counters;
        }

        $actor = $this->actor($organization);
        if (! $actor instanceof User) {
            return $counters; // ohne zuordenbaren Bearbeiter kein DMS-Eintrag
        }

        foreach ($client->receive($limit) as $document) {
            $counters['fetched']++;
            try {
                $payload = $document->getPayloadXml();
            } catch (Throwable) {
                // Umschlag unlesbar: nicht quittieren, damit der Beleg nicht
                // still verschwindet.
                $counters['unreadable']++;

                continue;
            }

            $result = $this->invoices->storeIncoming(
                $actor,
                $payload,
                'application/xml',
                null,
                'peppol',
                null,
                $this->filename($document),
            );

            match ((string) $result['status']) {
                'created' => $counters['imported']++,
                'duplicate' => $counters['duplicates']++,
                default => $counters['unreadable']++,
            };

            if ($result['status'] === 'unreadable') {
                continue;
            }

            if ($client->acknowledge($document->getMessageId())) {
                $counters['acknowledged']++;
            } else {
                // Nicht quittiert heißt: kommt wieder. Der Dedup fängt es ab.
                $counters['failed']++;
            }
        }

        return $counters;
    }

    private function filename(InboundDocument $document): string {
        $safe = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $document->getMessageId());

        return (string) __('peppol.inbound.document_name', ['id' => mb_substr($safe, 0, 64)]);
    }

    private function client(int $organizationId): ?AccessPointClientInterface {
        $plugin = app(PluginManager::class)->implementing(PeppolTransportProvider::class)->first();

        return $plugin instanceof PeppolTransportProvider ? $plugin->peppolAccessPoint($organizationId) : null;
    }

    /**
     * Ausführende Person des automatischen Eingangs. Der Eingang gehört keiner
     * Person; für Ablage und Audit braucht er trotzdem einen Bearbeiter —
     * dieselbe Wahl wie bei den übrigen Org-Läufen: die älteste Person der
     * Organisation.
     */
    private function actor(Organization $organization): ?User {
        return User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();
    }
}
