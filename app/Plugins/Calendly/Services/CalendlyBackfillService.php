<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyBackfillService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Services;

use App\Models\{CalendlyConnection, Organization};
use App\Plugins\Calendly\Api\CalendlyClient;
use App\Plugins\Calendly\CalendlyConfig;
use Carbon\CarbonImmutable;

/**
 * Polling-Backfill/Reconciliation der Calendly-Termine (Feature 095): die
 * verlässliche Quelle, die verpasste Webhook-Impulse heilt und Termine
 * einholt, die während einer ausgefallenen Subscription entstanden sind.
 * `GET /scheduled_events` (Zeitfenster, Cursor) + je Event die Invitees →
 * idempotenter Upsert über den {@see CalendlyIngestService}. Abwesenheit ≠
 * Absage — storniert wird nur bei definitivem `canceled`-Status.
 */
class CalendlyBackfillService {
    private const MAX_PAGES = 50;

    public function __construct(private readonly CalendlyIngestService $ingest) {}

    /**
     * @return array{created: int, updated: int, skipped: int, unmatched: int}
     */
    public function sync(Organization $organization): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'unmatched' => 0];

        $connection = CalendlyConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof CalendlyConnection || ! $connection->isActive()) {
            return $stats;
        }
        $organizationUri = (string) $connection->calendly_organization_uri;
        if ($organizationUri === '') {
            return $stats;
        }

        $config = CalendlyConfig::resolve();
        $client = new CalendlyClient($connection);
        $min = CarbonImmutable::now()->subDays($config['backfill_days_past'])->toIso8601ZuluString();
        $max = CarbonImmutable::now()->addDays($config['backfill_days_future'])->toIso8601ZuluString();

        $pageToken = null;
        $pages = 0;
        $apiSuccess = true;
        do {
            $page = $client->listScheduledEvents($organizationUri, $min, $max, $pageToken);
            if (! $page['success']) {
                $apiSuccess = false;
                break;
            }
            foreach ($page['collection'] as $event) {
                $eventUri = is_string($event['uri'] ?? null) ? $event['uri'] : '';
                if ($eventUri === '') {
                    continue;
                }
                foreach ($client->listEventInvitees($eventUri) as $invitee) {
                    // Der Backfill-Invitee referenziert das Event nur per URI —
                    // die volle Event-Resource für das Feld-Mapping mitgeben.
                    $invitee['scheduled_event'] = $event;
                    $result = $this->ingest->handleInvitee($organization, '', $invitee);
                    if ($result === null) {
                        $stats['skipped']++;

                        continue;
                    }
                    $result->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
                    if ($result->customer_id === null) {
                        $stats['unmatched']++;
                    }
                }
            }
            $pageToken = $page['next_page_token'];
            $pages++;
        } while ($pageToken !== null && $pages < self::MAX_PAGES);

        if ($apiSuccess) {
            $connection->forceFill(['last_synced_at' => now()])->save();
            $connection->recordConnectionSuccess();
        } else {
            $connection->recordConnectionFailure(__('Calendly API-Abfrage fehlgeschlagen.'));
        }

        return $stats;
    }
}
