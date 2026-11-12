<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataOwnershipGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\Integration\{DataDomain, IntegrationInboxStatus};
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\Organization;
use App\Modules\ModuleRegistry;
use App\Services\Integration\Contracts\OwnershipConflictReplay;
use App\Services\Integration\Dto\OwnershipClaim;
use App\Support\MorphMap;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datenführerschaft an den Schreibwegen der Plugins (MVP-1592). Darf die
 * Quelle nicht in den Bereich schreiben, legt {@see allows()} einen
 * Führerschaftskonflikt im Integrations-Eingang an, protokolliert ihn und der
 * Aufrufer schreibt nichts. {@see accept()} spielt den Vorgang einmal ab: die
 * Replay-Klasse ruft denselben Schreibweg erneut, der Guard lässt ihn für
 * genau diese Organisation, diesen Bereich und diese Quelle durch.
 *
 * - Ein verworfener Konflikt bleibt verworfen: dieselbe Quelle meldet
 *   denselben Vorgang nicht erneut an.
 * - Ein offener Konflikt übernimmt den neuesten Stand der Quelle.
 * - Ein übernommener Konflikt öffnet sich wieder, sobald die Quelle einen
 *   anderen Stand schreiben will — der gleiche Stand steht schon hier.
 * - Lässt die Führung den Schreibweg wieder zu, schließt der nächste
 *   Schreibversuch den offenen Konflikt selbst (als übernommen).
 * - Fälle, die ein Schreibweg vor MVP-1592 selbst anlegte
 *   ({@see OwnershipClaim::$legacyKey}), werden beim nächsten Versuch zu
 *   Führerschaftskonflikten.
 */
final class DataOwnershipGuard {
    public const string DEDUPE_PREFIX = 'own:';

    /** @var array<string, true> laufende Übernahmen je Organisation, Bereich und Quelle */
    private array $overrides = [];

    /** @var array<string, bool> gibt es offene Konflikte je Organisation, Bereich und Quelle? */
    private array $openKnown = [];

    /** @var array<string, bool> gibt es offene Altfälle je Organisation und Quelle? */
    private array $legacyKnown = [];

    public function __construct(
        private readonly DataOwnershipResolver $resolver,
        private readonly ModuleRegistry $modules,
    ) {}

    /** true = schreiben; false = Konflikt im Eingang angelegt bzw. schon vorhanden, nichts schreiben. */
    public function allows(Organization $organization, DataDomain $domain, string $source, OwnershipClaim $claim): bool {
        $scope = $this->scope($organization, $domain, $source);
        if (isset($this->overrides[$scope])) {
            return true;
        }

        $dedupeKey = self::dedupeKey($domain, $claim);
        if ($claim->legacyKey !== null) {
            $this->adoptLegacy($organization, $scope, $source, $claim->legacyKey, $dedupeKey);
        }
        if ($this->resolver->mayWrite($organization, $domain, $source)) {
            $this->closeSuperseded($organization, $scope, $source, $dedupeKey);

            return true;
        }

        $this->stage($organization, $domain, $source, $claim, $dedupeKey);
        $this->openKnown[$scope] = true;

        return false;
    }

    /**
     * „Übernehmen“: schreibt den zurückgehaltenen Stand einmal. Null heißt,
     * es war nichts mehr zu schreiben; der Fall ist trotzdem erledigt.
     */
    public function accept(IntegrationInboxItem $item): ?Model {
        $marker = $item->ownershipMarker();
        $domain = DataDomain::tryFrom((string) ($marker['domain'] ?? ''));
        if ($marker === null || $domain === null) {
            throw new RuntimeException((string) __('integration.ownership.not_a_conflict'));
        }
        $replay = $this->replayFor((string) $marker['kind']);
        if ($replay === null) {
            throw new RuntimeException((string) __('integration.ownership.replay_missing'));
        }
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($item->organization_id);
        $scope = $this->scope($organization, $domain, $item->plugin_id);

        return DB::transaction(function () use ($item, $marker, $domain, $replay, $organization, $scope): ?Model {
            $locked = IntegrationInboxItem::query()->withoutGlobalScopes()->lockForUpdate()->find($item->getKey());
            if (! $locked instanceof IntegrationInboxItem || ! $locked->isOpen()) {
                throw new RuntimeException((string) __('integration.ownership.not_open'));
            }

            $this->overrides[$scope] = true;
            try {
                $model = $replay->replayOwnershipConflict($organization, $locked);
            } finally {
                unset($this->overrides[$scope]);
            }

            $locked->refresh();
            if ($locked->isOpen()) {
                app(InboxActionService::class)->markResolved($locked, IntegrationInboxStatus::ResolvedRemote, $model);
            }
            $organization->audit('integration.ownership_overridden', [
                'domain' => $domain->value,
                'source' => $locked->plugin_id,
                'owner' => $marker['owner'],
                'kind' => $marker['kind'],
                'external_id' => $locked->external_id,
                'written' => $model !== null,
            ]);

            return $model;
        });
    }

    /** Offene Führerschaftskonflikte eines Bereichs (Matrix, Hinweis beim Wechsel). */
    public function openConflicts(Organization $organization, DataDomain $domain): int {
        return IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', IntegrationInboxStatus::Open)
            ->whereLikeEscaped('dedupe_key', self::DEDUPE_PREFIX . $domain->value . ':', 'prefix')
            ->count();
    }

    public static function dedupeKey(DataDomain $domain, OwnershipClaim $claim): string {
        // Fremd-IDs (Graph, CalDAV-UIDs) sprengen sonst die 191 Zeichen des Schlüssels.
        return self::DEDUPE_PREFIX . $domain->value . ':' . CryptoHelper::hash($claim->kind . '|' . $claim->key, HashAlgorithm::SHA1);
    }

    private function stage(Organization $organization, DataDomain $domain, string $source, OwnershipClaim $claim, string $dedupeKey): void {
        $owner = $this->resolver->ownerFor($organization, $domain);
        $snapshot = [
            IntegrationInboxItem::SNAPSHOT_OWNERSHIP => ['domain' => $domain->value, 'owner' => $owner, 'kind' => $claim->kind],
            'payload' => $claim->payload,
        ];
        $title = mb_substr(trim($claim->title) !== '' ? trim($claim->title) : '—', 0, 255);

        $item = IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrCreate([
            'organization_id' => $organization->id,
            'plugin_id' => $source,
            'dedupe_key' => $dedupeKey,
        ], [
            'source' => mb_substr($source, 0, 16),
            'target_type' => MorphMap::alias($claim->target),
            'external_type' => mb_substr($claim->kind, 0, 64),
            'external_id' => $claim->externalId !== null ? mb_substr($claim->externalId, 0, 191) : null,
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'status' => IntegrationInboxStatus::Open,
            'referenceable_type' => $claim->local?->getMorphClass(),
            'referenceable_id' => $claim->local?->getKey(),
            'remote_snapshot' => $snapshot,
            'display_title' => $title,
            'display_subtitle' => $claim->subtitle !== null ? mb_substr($claim->subtitle, 0, 255) : null,
            'occurred_at' => now(),
        ]);

        $audit = [
            'domain' => $domain->value,
            'source' => $source,
            'owner' => $owner,
            'kind' => $claim->kind,
            'external_id' => $claim->externalId,
        ];
        if ($item->wasRecentlyCreated) {
            $organization->audit('integration.ownership_blocked', $audit);

            return;
        }
        if ($item->isOpen()) {
            $item->forceFill(['remote_snapshot' => $snapshot, 'display_title' => $title, 'occurred_at' => now()])->save();

            return;
        }
        // Verworfen bleibt verworfen; ein übernommener Stand ist geschrieben, ein neuer nicht.
        if ($item->status === IntegrationInboxStatus::ResolvedRemote && $this->payloadChanged($item, $claim->payload)) {
            $item->forceFill([
                'status' => IntegrationInboxStatus::Open,
                'remote_snapshot' => $snapshot,
                'display_title' => $title,
                'occurred_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
                'resolved_to_type' => null,
                'resolved_to_id' => null,
            ])->save();
            $organization->audit('integration.ownership_blocked', $audit + ['reopened' => true]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function payloadChanged(IntegrationInboxItem $item, array $payload): bool {
        // Vergleich in Speicherform: JSON kennt weder Objekte noch Schlüsselreihenfolge.
        return JsonHelper::decode(JsonHelper::encode($payload)) != (($item->remote_snapshot ?? [])['payload'] ?? null);
    }

    /**
     * Macht den Altfall eines Schreibwegs zum Führerschaftskonflikt: gleicher
     * Fall, neuer Schlüssel; den Stand setzt {@see stage()} bzw. der Selbstschluss.
     */
    private function adoptLegacy(Organization $organization, string $scope, string $source, string $legacyKey, string $dedupeKey): void {
        $open = fn () => IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $source)
            ->where('status', IntegrationInboxStatus::Open);
        // Altfälle der Schreibwege tragen „ownership“ im Schlüssel.
        $this->legacyKnown[$organization->id . '|' . $source] ??= $open()
            ->whereLikeEscaped('dedupe_key', 'ownership')
            ->where('dedupe_key', 'not like', self::DEDUPE_PREFIX . '%')
            ->exists();
        if (! $this->legacyKnown[$organization->id . '|' . $source]) {
            return;
        }

        $legacy = $open()->where('dedupe_key', $legacyKey)->first();
        if ($legacy === null || IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $source)
            ->where('dedupe_key', $dedupeKey)
            ->exists()) {
            return;
        }
        $legacy->forceFill(['dedupe_key' => $dedupeKey])->save();
        $this->openKnown[$scope] = true;
    }

    private function closeSuperseded(Organization $organization, string $scope, string $source, string $dedupeKey): void {
        $this->openKnown[$scope] ??= IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $source)
            ->where('status', IntegrationInboxStatus::Open)
            ->whereLikeEscaped('dedupe_key', explode('|', $scope)[1] . ':', 'prefix')
            ->exists();
        if (! $this->openKnown[$scope]) {
            return;
        }

        $item = IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $source)
            ->where('dedupe_key', $dedupeKey)
            ->where('status', IntegrationInboxStatus::Open)
            ->first();
        // Der Schreibweg schreibt jetzt selbst: übernommen, nicht verworfen — sonst
        // bliebe derselbe Vorgang bei späterer Fremdführung still gesperrt.
        if ($item instanceof IntegrationInboxItem) {
            app(InboxActionService::class)->markResolved($item, IntegrationInboxStatus::ResolvedRemote);
        }
    }

    private function replayFor(string $kind): ?OwnershipConflictReplay {
        foreach ($this->modules->extensions(OwnershipConflictReplay::class) as $class) {
            $replay = app($class);
            if (in_array($kind, $replay->ownershipKinds(), true)) {
                return $replay;
            }
        }

        return null;
    }

    /** "<org>|own:<bereich>|<quelle>" — der mittlere Teil ist das Präfix der Konfliktschlüssel. */
    private function scope(Organization $organization, DataDomain $domain, string $source): string {
        return $organization->id . '|' . self::DEDUPE_PREFIX . $domain->value . '|' . $source;
    }
}
