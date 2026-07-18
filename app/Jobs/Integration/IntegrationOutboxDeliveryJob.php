<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Contracts\PluginDispatcher;
use App\Jobs\AbstractOutboxDeliveryJob;
use App\Models\{IntegrationInboxItem, IntegrationOutboxEntry};
use App\Services\Integration\{IntegrationOutboxDispatcherResolver, IntegrationOutboxService};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Stellt einen generischen Outbox-Eintrag an das externe System zu
 * (Feature 055, MVP-114). Ablauf im gemeinsamen Skelett (C14); hier nur die
 * Kompensation: sichtbarer Fall in der Integrations-Inbox.
 *
 * @extends AbstractOutboxDeliveryJob<IntegrationOutboxEntry, IntegrationOutboxDispatcher>
 */
class IntegrationOutboxDeliveryJob extends AbstractOutboxDeliveryJob {
    public function handle(IntegrationOutboxService $outbox, IntegrationOutboxDispatcherResolver $resolver): void {
        $this->deliver($outbox, $resolver);
    }

    protected function newEntryQuery(): Builder {
        return IntegrationOutboxEntry::query();
    }

    protected function outboxService(): IntegrationOutboxService {
        return app(IntegrationOutboxService::class);
    }

    /**
     * @param IntegrationOutboxDispatcher $dispatcher
     * @param IntegrationOutboxEntry $entry
     */
    protected function dispatchEntry(PluginDispatcher $dispatcher, Model $entry): bool {
        return $dispatcher->dispatch($entry);
    }

    /** @param IntegrationOutboxEntry $entry */
    protected function compensateEntry(Model $entry, string $reason): void {
        // Sichtbarer Inbox-Fall statt stillem Verlust: der lokale Stand gilt,
        // die externe Seite ist veraltet — manuelle Auflösung über die Inbox.
        IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrCreate([
            'organization_id' => $entry->organization_id,
            'plugin_id' => $entry->plugin_id,
            'dedupe_key' => 'outbox-failed:' . $entry->idempotency_key,
        ], [
            'source' => $entry->plugin_id,
            'target_type' => $entry->subject_type ?? $entry->operation,
            'external_type' => $entry->operation,
            'external_id' => (string) $entry->id,
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $entry->subject_type,
            'referenceable_id' => $entry->subject_id,
            'local_snapshot' => $entry->payload,
            'remote_snapshot' => [],
            'display_title' => $entry->operation,
            'display_subtitle' => mb_substr($reason, 0, 120),
            'occurred_at' => now(),
        ]);
    }
}
