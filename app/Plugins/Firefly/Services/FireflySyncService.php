<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflySyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Services;

use APIToolkit\Exceptions\UnauthorizedException;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\Organization;
use App\Plugins\Firefly\Api\FireflyClientFactory;
use App\Plugins\Firefly\Enums\{FireflyMatchStatus, FireflyTransactionKind};
use App\Plugins\Firefly\{FireflyConfig, FireflyPlugin};
use App\Plugins\Firefly\Models\{FireflyConnection, FireflyTransaction};
use App\Plugins\PluginErrorRecorder;
use App\Plugins\Support\{PluginApiException, PluginSettingsResolver, PluginTenantGate};
use App\Services\Finance\Banking\ReferenceExtractor;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{NumberHelper, StringHelper};
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Firefly-III-Bewegungen als Abgleichquelle (Feature 176, MVP-1333): jeder Lauf
 * liest das rollierende Zeitfenster, spiegelt Ein- und Ausgänge und ordnet sie
 * über eine Belegnummer im Verwendungszweck zu — Eingang → Rechnung, Ausgang →
 * Eingangsrechnung —, nie über Betrag oder Datum. Mehrdeutige Nummern bleiben
 * offen. WorkDiary bucht daraus keine Zahlung und schreibt nichts zurück. Was
 * im vollständig gelesenen Fenster fehlt, entfällt aus dem Spiegel.
 */
class FireflySyncService {
    private const LOCK_SECONDS = 900;

    public function __construct(private readonly FireflyClientFactory $clients) {}

    public function connection(int $organizationId): ?FireflyConnection {
        return FireflyConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
    }

    public function mayRun(Organization $organization): bool {
        $orgId = (int) $organization->id;
        if (PluginTenantGate::blocks($orgId) || ! PluginSettingsResolver::for(FireflyPlugin::ID, $orgId)->enabled() || ! $this->clients->isConfigured($orgId)) {
            return false;
        }

        return $this->connection($orgId)?->isReady() === true;
    }

    /** @return array{read: int, created: int, updated: int, unchanged: int, matched: int, removed: int} */
    public function run(Organization $organization): array {
        $counters = ['read' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'matched' => 0, 'removed' => 0];
        if (! $this->mayRun($organization)) {
            return $counters;
        }
        $lock = Cache::lock('firefly:' . $organization->id, self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw new RuntimeException('Firefly-Abgleich läuft bereits.');
        }
        try {
            return $this->runLocked($organization, $counters);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{read: int, created: int, updated: int, unchanged: int, matched: int, removed: int}  $counters
     * @return array{read: int, created: int, updated: int, unchanged: int, matched: int, removed: int}
     */
    private function runLocked(Organization $organization, array $counters): array {
        $orgId = (int) $organization->id;
        $connection = $this->connection($orgId);
        if ($connection === null) {
            return $counters;
        }
        $to = CarbonImmutable::today();
        $from = $to->subDays(FireflyConfig::resolve($orgId)['window_days']);
        $seen = [];
        $complete = false;
        $error = null;
        try {
            foreach ($this->clients->for($orgId)->transactionGroups($from, $to) as $group) {
                foreach ((array) data_get($group, 'attributes.transactions', []) as $split) {
                    if (is_array($split) && ($journalId = $this->mirror($orgId, is_scalar($group['id'] ?? null) ? (string) $group['id'] : null, $split, $counters)) !== null) {
                        $seen[$journalId] = true;
                    }
                }
            }
            $complete = true;
        } catch (Throwable $e) {
            try {
                app(PluginErrorRecorder::class)->record(FireflyPlugin::ID, 'runtime', $e, [], $orgId);
            } catch (Throwable) {
                // Aufzeichnung darf den Lauf nicht reißen.
            }
            if ($e instanceof UnauthorizedException || ($e instanceof PluginApiException && $e->isAuthError())) {
                $connection->recordConnectionFailure(class_basename($e));

                throw $e;
            }
            $error = class_basename($e);
        }

        if ($complete) {
            $stale = FireflyTransaction::query()->where('organization_id', $orgId)
                ->whereBetween('booked_on', DateRange::days($from, $to))
                ->whereNotIn('remote_journal_id', array_map('strval', array_keys($seen)));
            $counters['removed'] = (clone $stale)->count();
            $stale->delete();
        }
        $connection->forceFill(['last_synced_at' => CarbonImmutable::now(), 'last_sync_counters' => $counters, 'is_last_run_complete' => $complete])->save();
        $error !== null ? $connection->recordConnectionFailure($error) : $connection->recordConnectionSuccess();

        return $counters;
    }

    /**
     * Eine Buchung spiegeln; liefert die Journal-ID oder null für Umbuchungen und Unlesbares.
     *
     * @param  array<mixed>  $split
     * @param  array{read: int, created: int, updated: int, unchanged: int, matched: int, removed: int}  $counters
     */
    private function mirror(int $orgId, ?string $groupId, array $split, array &$counters): ?string {
        $kind = FireflyTransactionKind::tryFrom((string) ($split['type'] ?? ''));
        $journalId = is_scalar($split['transaction_journal_id'] ?? null) ? (string) $split['transaction_journal_id'] : '';
        $amount = is_numeric($split['amount'] ?? null) ? NumberHelper::toUSFormat((string) $split['amount'], 2) : null;
        $date = is_string($split['date'] ?? null) ? CarbonImmutable::parse($split['date']) : null;
        if ($kind === null || ! ctype_digit($journalId) || $amount === null || $date === null) {
            return null;
        }
        $counters['read']++;
        $description = StringHelper::trimToNull($split['description'] ?? null);
        // Die SEPA-End-to-End-ID trägt bei Überweisungen oft die Belegnummer des Absenders.
        $references = ReferenceExtractor::extract($description, StringHelper::trimToNull($split['notes'] ?? null), StringHelper::trimToNull($split['internal_reference'] ?? null), StringHelper::trimToNull($split['external_id'] ?? null), StringHelper::trimToNull($split['sepa_ct_id'] ?? null));
        [$status, $invoiceId, $incomingId] = $this->match($orgId, $kind, $references);
        if ($status === FireflyMatchStatus::Matched) {
            $counters['matched']++;
        }

        $row = FireflyTransaction::query()->firstOrNew(['organization_id' => $orgId, 'remote_journal_id' => $journalId]);
        $row->fill([
            'remote_group_id' => $groupId,
            'kind' => $kind,
            'booked_on' => $date->toDateString(),
            'currency' => CurrencyCode::tryFrom(strtoupper((string) ($split['currency_code'] ?? ''))),
            'booked_amount' => $amount,
            'description' => $description !== null ? mb_substr($description, 0, 1000) : null,
            'counterparty_name' => StringHelper::trimToNull($split[$kind === FireflyTransactionKind::Deposit ? 'source_name' : 'destination_name'] ?? null),
            'account_name' => StringHelper::trimToNull($split[$kind === FireflyTransactionKind::Deposit ? 'destination_name' : 'source_name'] ?? null),
            'references' => $references !== [] ? $references : null,
            'match_status' => $status,
            'invoice_id' => $invoiceId,
            'incoming_einvoice_id' => $incomingId,
        ]);
        if ($row->exists && ! $row->isDirty()) {
            $counters['unchanged']++;
        } else {
            $counters[$row->exists ? 'updated' : 'created']++;
            $row->save();
        }

        return $journalId;
    }

    /**
     * Genau ein Beleg der Organisation mit einer der Nummern; Eingang sucht
     * Rechnungen, Ausgang Eingangsrechnungen.
     *
     * @param  list<string>  $references
     * @return array{0: FireflyMatchStatus, 1: ?int, 2: ?int}
     */
    private function match(int $orgId, FireflyTransactionKind $kind, array $references): array {
        if ($references === []) {
            return [FireflyMatchStatus::Unmatched, null, null];
        }
        $ids = $kind === FireflyTransactionKind::Deposit
            ? Invoice::query()->withoutGlobalScopes()->where('organization_id', $orgId)->whereIn('number', $references)->pluck('id')
            : IncomingEInvoice::query()->withoutGlobalScopes()->where('organization_id', $orgId)->whereIn('invoice_number', $references)->pluck('id');
        $ids = $ids->unique()->values();
        if ($ids->count() !== 1) {
            return [$ids->isEmpty() ? FireflyMatchStatus::Unmatched : FireflyMatchStatus::Ambiguous, null, null];
        }
        $id = (int) $ids->first();

        return [FireflyMatchStatus::Matched, $kind === FireflyTransactionKind::Deposit ? $id : null, $kind === FireflyTransactionKind::Withdrawal ? $id : null];
    }
}
