<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRecurringTemplateSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\{ApiException, NotFoundException};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Exceptions\LexofficeApiException;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeRecurringTemplate, LexofficeRecurringTemplateLine};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Facades\{DB, Log};
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\Documents\RecurringTemplatesEndpoint;
use Throwable;

/**
 * Serienvorlagen aus Lexoffice spiegeln (Feature 185, MVP-1409):
 * `GET /v1/recurring-templates` (Liste mit Kontakt, Summe und Einstellungen,
 * bis 250 je Seite, neueste Änderung zuerst) und je neuer oder geänderter
 * Vorlage der Einzelabruf mit den Positionen. Die API ist nur lesend — das
 * Register schreibt nie eine Vorlage. Vorlagen, die die vollständige Liste
 * nicht mehr nennt, gelten als beendet.
 *
 * Quelle: https://developers.lexware.io/docs/#recurring-templates-endpoint
 */
final class LexofficeRecurringTemplateSync {
    public const PAGE_SIZE = 250;

    /** Mehr Seiten liest kein Lauf — die Liste wäre dann unvollständig, nichts wird beendet. */
    private const MAX_PAGES = 20;

    private ?Client $client = null;

    /** @param  int|null  $organizationId  null = gebundene Organisation bzw. Einzelplatz-Konfiguration */
    public function __construct(private readonly ?int $organizationId) {}

    /**
     * @return array{read: int, created: int, updated: int, lines: int, ended: int, failed: int, complete: bool}
     */
    public function sync(Organization $organization): array {
        $result = ['read' => 0, 'created' => 0, 'updated' => 0, 'lines' => 0, 'ended' => 0, 'failed' => 0, 'complete' => true];
        $endpoint = new RecurringTemplatesEndpoint($this->client());
        /** @var array<string, true> $seen */
        $seen = [];
        $page = 0;
        do {
            try {
                $data = $endpoint->search(['page' => $page, 'size' => self::PAGE_SIZE, 'sort' => 'updatedDate,DESC'])->toArray();
            } catch (ApiException $e) {
                throw LexofficeApiException::fromApiException($e, 'Lexoffice', 'Serienvorlagen abrufen');
            }
            foreach ((array) ($data['content'] ?? []) as $entry) {
                if (! is_array($entry) || (string) ($entry['id'] ?? '') === '') {
                    continue;
                }
                $result['read']++;
                $seen[(string) $entry['id']] = true;
                try {
                    $this->upsert($organization, $entry, $endpoint, $result);
                } catch (Throwable $e) {
                    $result['failed']++;
                    Log::warning('LexofficeRecurringTemplateSync: Vorlage nicht gespiegelt.', ['organization_id' => $organization->id, 'template' => $entry['id'], 'error' => class_basename($e) . ': ' . mb_substr($e->getMessage(), 0, 200)]);
                }
            }
            $last = (bool) ($data['last'] ?? true);
            $page++;
        } while (! $last && $page < self::MAX_PAGES);
        if (! $last) {
            $result['complete'] = false;
        }

        // Verschwundene Vorlagen: nur bei vollständiger Liste beenden (Review-Lehre aus dem Domain-Sync).
        if ($result['complete']) {
            $gone = LexofficeRecurringTemplate::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('execution_status', '<>', 'ENDED')
                ->when($seen !== [], static fn($q) => $q->whereNotIn('external_id', array_keys($seen)))
                ->get();
            foreach ($gone as $template) {
                $template->forceFill(['execution_status' => 'ENDED', 'next_execution_on' => null, 'synced_at' => now()])->save();
                $result['ended']++;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{read: int, created: int, updated: int, lines: int, ended: int, failed: int, complete: bool}  $result
     */
    private function upsert(Organization $organization, array $entry, RecurringTemplatesEndpoint $endpoint, array &$result): void {
        $externalId = (string) $entry['id'];
        $settings = is_array($entry['recurringTemplateSettings'] ?? null) ? $entry['recurringTemplateSettings'] : [];
        $address = is_array($entry['address'] ?? null) ? $entry['address'] : [];
        $total = is_array($entry['totalPrice'] ?? null) ? $entry['totalPrice'] : [];
        $contactId = StringHelper::trimToNull((string) ($address['contactId'] ?? ''));
        $remoteUpdated = self::dateTime($entry['updatedDate'] ?? null);

        $template = LexofficeRecurringTemplate::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('external_id', $externalId)
            ->first();
        $isNew = $template === null;
        $template ??= new LexofficeRecurringTemplate(['organization_id' => $organization->id, 'external_id' => $externalId]);
        $needsDetail = $isNew || $template->lines_synced_at === null || $template->remote_updated_at?->toDateTimeString() !== $remoteUpdated?->toDateTimeString();

        $template->fill([
            'contact_external_id' => $contactId,
            'contact_name' => StringHelper::trimToNull(mb_substr((string) ($address['name'] ?? ''), 0, 255)),
            'customer_id' => $contactId !== null ? $this->customerIdFor($organization, $contactId) : null,
            'title' => StringHelper::trimToNull(mb_substr((string) ($entry['title'] ?? ''), 0, 255)),
            'execution_status' => (string) ($settings['executionStatus'] ?? 'PAUSED'),
            'execution_interval' => StringHelper::trimToNull((string) ($settings['executionInterval'] ?? '')),
            'starts_on' => self::date($settings['startDate'] ?? null),
            'ends_on' => self::date($settings['endDate'] ?? null),
            'next_execution_on' => self::date($settings['nextExecutionDate'] ?? null),
            'last_execution_on' => self::date($settings['lastExecutionDate'] ?? null),
            'is_finalized' => (bool) ($settings['finalize'] ?? false),
            'is_last_execution_failed' => (bool) ($settings['lastExecutionFailed'] ?? false),
            'last_execution_error' => StringHelper::trimToNull((string) ($settings['lastExecutionErrorMessage'] ?? '')),
            'total_net' => is_numeric($total['totalNetAmount'] ?? null) ? (string) $total['totalNetAmount'] : null,
            'currency' => (string) ($total['currency'] ?? 'EUR'),
            'remote_updated_at' => $remoteUpdated,
            'payload' => $entry,
            'synced_at' => now(),
        ]);

        if (! $needsDetail) {
            $template->save();
            $result['updated']++;

            return;
        }

        $detail = $this->fetch($externalId, $endpoint);
        DB::transaction(function () use ($organization, $template, $detail, $isNew, &$result): void {
            $template->save();
            if ($detail === null) {
                // In Lexoffice gelöscht, obwohl in der Liste: als beendet führen.
                $template->forceFill(['execution_status' => 'ENDED', 'next_execution_on' => null, 'lines_synced_at' => now()])->save();
                $result[$isNew ? 'created' : 'updated']++;

                return;
            }
            $parsed = LexofficeInvoiceParser::parse($detail);
            $articleIds = $this->articleIds($organization->id, array_column($parsed['lines'], 'external_article_id'));
            $existing = LexofficeRecurringTemplateLine::query()->withoutGlobalScopes()->where('template_id', $template->id)->get()->keyBy('position');
            $seen = [];
            foreach ($parsed['lines'] as $line) {
                $attributes = [
                    'kind' => $line['type'] !== '' ? $line['type'] : null,
                    'external_article_id' => $line['external_article_id'] !== '' ? $line['external_article_id'] : null,
                    'lexoffice_article_id' => $articleIds[$line['external_article_id']] ?? null,
                    'name' => mb_substr($line['name'], 0, 255),
                    'description' => $line['description'] !== '' ? $line['description'] : null,
                    'quantity' => $line['quantity'],
                    'unit_name' => $line['unit_name'] !== '' ? mb_substr($line['unit_name'], 0, 32) : null,
                    'unit_net' => $line['unit_net'],
                    'total_net' => $line['total_net'],
                    'tax_rate' => $line['tax_rate'],
                    'currency' => $parsed['currency']->value,
                ];
                $seen[] = (int) $line['position'];
                $current = $existing->get($line['position']);
                if ($current === null) {
                    LexofficeRecurringTemplateLine::query()->create($attributes + ['organization_id' => $organization->id, 'template_id' => $template->id, 'position' => $line['position']]);
                } else {
                    $current->fill($attributes)->save();
                }
                $result['lines']++;
            }
            foreach ($existing as $position => $stale) {
                if (! in_array((int) $position, $seen, true)) {
                    $stale->delete();
                }
            }
            $template->forceFill([
                'title' => StringHelper::trimToNull(mb_substr((string) ($detail['title'] ?? $template->title ?? ''), 0, 255)),
                'lines_synced_at' => now(),
            ])->save();
            $result[$isNew ? 'created' : 'updated']++;
        });
    }

    /** Kunde zum Lexoffice-Kontakt über die Kontaktverknüpfung (nie raten). */
    private function customerIdFor(Organization $organization, string $contactId): ?int {
        $reference = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('external_id', $contactId)
            ->first();
        if ($reference === null || ! MorphMap::is((string) $reference->referenceable_type, Customer::class)) {
            return null;
        }

        return (int) $reference->referenceable_id;
    }

    /**
     * @param  list<string>  $externalIds
     * @return array<string, int>
     */
    private function articleIds(int $organizationId, array $externalIds): array {
        $externalIds = array_values(array_unique(array_filter($externalIds, static fn(string $id): bool => $id !== '')));
        if ($externalIds === []) {
            return [];
        }

        return LexofficeArticle::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id')
            ->map(static fn($id): int => (int) $id)
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function fetch(string $externalId, RecurringTemplatesEndpoint $endpoint): ?array {
        try {
            return $endpoint->get(new ID($externalId))->toArray();
        } catch (NotFoundException) {
            return null;
        } catch (ApiException $e) {
            throw LexofficeApiException::fromApiException($e, 'Lexoffice', 'Serienvorlage abrufen');
        }
    }

    private static function date(mixed $value): ?string {
        return self::dateTime($value)?->toDateString();
    }

    private static function dateTime(mixed $value): ?CarbonImmutable {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function client(): Client {
        return $this->client ??= app(LexofficeClientFactory::class)->for($this->organizationId);
    }
}
