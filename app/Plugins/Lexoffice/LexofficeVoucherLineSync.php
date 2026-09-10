<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherLineSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine, Organization};
use App\Models\Reselling\ResalePeriodLink;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Log};
use Throwable;

/**
 * Positionen der gespiegelten Lexoffice-Rechnungen und -Gutschriften
 * nachladen (Feature 152, MVP-760 = Feature 140 Schnitt 2): je Beleg ohne
 * `lines_synced_at` ein `GET /invoices/{id}` bzw. `GET /credit-notes/{id}`,
 * Positionen und Belegtexte in den Spiegel. Nur Lexoffice-eigene Belege
 * (`invoice`, `creditnote`) — Buchungsbelege haben keine Positionen.
 * Positionen werden IN PLACE über `(voucher_id, position)` aktualisiert,
 * damit die IDs stabil bleiben: `resale_period_links` zeigt per Morph ohne
 * FK darauf (Review 2026-09-10, B1). Ratenlimit über den Client-
 * Anfrageabstand, 429 retryt der Client mit Retry-After/Backoff.
 *
 * Vorzeichen bei Gutschriften (Review A3): `quantity` und `unit_net` bleiben
 * positiv (LicenseMonths rechnet mit der Menge), nur `total_net` ist negativ;
 * das Vorzeichen des Bezugs setzt PeriodLinker::attach() über den Belegtyp.
 */
final class LexofficeVoucherLineSync {
    public const VOUCHER_TYPES = ['invoice', 'creditnote'];

    /** Detailendpunkt je Belegtyp (gleiche `lineItems`-Struktur). */
    private const ENDPOINTS = ['invoice' => '/invoices/', 'creditnote' => '/credit-notes/'];

    /** Wiederholungen je Anfrage (Toolkit-Retry inkl. Retry-After), danach zählt der Beleg als Fehlschlag. */
    public const MAX_RETRIES = 5;

    /** Backoff nach n Fehlschlägen: 2^n Stunden, ab 8 Versuchen fest 7 Tage. */
    public const MAX_BACKOFF_HOURS = 168;

    private const BACKOFF_STEPS = 7;

    /** Erfolg löscht den Fehlermarker. */
    private const CLEARED_FAILURE = ['lines_sync_failed_at' => null, 'lines_sync_attempts' => 0];

    private ?PluginApiClient $api = null;

    private float $requestInterval;

    /**
     * @param  float|null  $requestInterval  Anfrageabstand in Sekunden; null = Einstellung der gebundenen Organisation
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
        ?float $requestInterval = null,
    ) {
        $this->requestInterval = $requestInterval ?? LexofficeConfig::requestInterval();
    }

    /** Tests: kein Anfrageabstand, keine Retry-Wartezeit. */
    public function withoutThrottle(): self {
        $this->requestInterval = 0.0;
        $this->api = null;

        return $this;
    }

    /**
     * Rechnungen ohne Positionen nachladen — neueste zuerst, höchstens $limit.
     * Ein Fehler je Beleg (API, Parser, DB) zählt als `failed`, markiert den
     * Beleg mit Backoff und hält den Lauf nicht an.
     *
     * @return array{synced: int, lines: int, failed: int, remaining: int}
     */
    public function syncMissing(Organization|int $organization, int $limit = 100): array {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $query = $this->pending($organizationId);
        $remaining = (clone $query)->count();
        $synced = 0;
        $lines = 0;
        $failed = 0;
        // Fehlschläge (nach Backoff wieder fällig) ans Ende, sonst neueste zuerst.
        $batch = (clone $query)
            ->orderByRaw('CASE WHEN lines_sync_failed_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
        foreach ($batch as $voucher) {
            try {
                $lines += $this->syncVoucher($voucher);
                $synced++;
            } catch (Throwable $e) {
                $failed++;
                $this->markFailed($voucher, $e);
            }
        }

        return ['synced' => $synced, 'lines' => $lines, 'failed' => $failed, 'remaining' => max(0, $remaining - $synced)];
    }

    /**
     * Positionen EINES Belegs (Rechnung oder Gutschrift) laden und in place
     * abgleichen (IDs bleiben stehen, überzählige Positionen fallen weg).
     * Liefert die Zahl der Positionen.
     */
    public function syncVoucher(LexofficeVoucher $voucher): int {
        $creditNote = (string) $voucher->voucher_type === 'creditnote';
        $invoice = $this->getJson((self::ENDPOINTS[(string) $voucher->voucher_type] ?? '/invoices/') . $voucher->external_id, $creditNote ? 'Gutschrift abrufen' : 'Rechnung abrufen');
        if ($invoice === null) {
            // In Lexoffice gelöscht: als erledigt markieren, nicht ewig neu versuchen.
            $voucher->forceFill(['lines_synced_at' => now()] + self::CLEARED_FAILURE)->save();

            return 0;
        }
        $parsed = LexofficeInvoiceParser::parse($invoice);
        if ($creditNote) {
            // Gutschrift: Positionsbetrag negativ, Menge/Stückpreis positiv (siehe Klassen-Doc).
            foreach ($parsed['lines'] as &$line) {
                $line['total_net'] = -abs($line['total_net']);
            }
            unset($line);
        }
        foreach ($parsed['issues'] as $issue) {
            Log::warning('LexofficeVoucherLineSync: ' . $issue, ['organization_id' => $voucher->organization_id, 'voucher_number' => $voucher->voucher_number]);
        }
        $articleIds = $this->articleIds($voucher->organization_id, array_column($parsed['lines'], 'external_article_id'));

        DB::transaction(function () use ($voucher, $parsed, $articleIds): void {
            /** @var \Illuminate\Database\Eloquent\Collection<int, LexofficeVoucherLine> $existing */
            $existing = LexofficeVoucherLine::query()->withoutGlobalScopes()->where('voucher_id', $voucher->id)->get()->keyBy('position');
            $seen = [];
            foreach ($parsed['lines'] as $line) {
                $attributes = [
                    'type' => $line['type'] !== '' ? $line['type'] : null,
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
                    LexofficeVoucherLine::query()->create($attributes + [
                        'organization_id' => $voucher->organization_id,
                        'voucher_id' => $voucher->id,
                        'position' => $line['position'],
                    ]);

                    continue;
                }
                if (! self::sameIdentity($current, $attributes)) {
                    // Fachlich eine andere Position: Bezüge bleiben stehen, der
                    // Betreiber sieht es im Log (Reparatur: resale:repair-links).
                    $this->warnLinked($current, 'Position hat sich fachlich geändert, bestehende Rechnungsbezüge zeigen auf den neuen Inhalt.', [
                        'old_name' => $current->name, 'new_name' => $attributes['name'],
                        'old_article' => $current->external_article_id, 'new_article' => $attributes['external_article_id'],
                    ]);
                }
                $current->fill($attributes)->save();
            }
            // Eloquent-`except()` filtert nach Primärschlüssel, nicht nach dem keyBy-Schlüssel.
            foreach ($existing as $position => $stale) {
                if (in_array((int) $position, $seen, true)) {
                    continue;
                }
                $this->warnLinked($stale, 'Position entfällt in Lexoffice, ihre Rechnungsbezüge verwaisen.', ['name' => $stale->name]);
                $stale->delete();
            }
            $voucher->forceFill([
                'voucher_text' => $parsed['voucher_text'] !== '' ? $parsed['voucher_text'] : null,
                'recipient_name' => $parsed['recipient'] !== '' ? mb_substr($parsed['recipient'], 0, 255) : null,
                'service_starts_on' => $parsed['service_from'],
                'service_ends_on' => $parsed['service_to'],
                'lines_synced_at' => now(),
            ] + self::CLEARED_FAILURE)->save();
        });

        return count($parsed['lines']);
    }

    /**
     * Spiegel zurücksetzen: alle Rechnungen/Gutschriften wieder als „Positionen
     * fehlen" markieren, damit `syncMissing` sie neu lädt (Positionen bleiben
     * bis zum Neuladen stehen). Liefert die Zahl der markierten Belege.
     */
    public function resetSynced(Organization $organization): int {
        return LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('voucher_type', self::VOUCHER_TYPES)
            ->whereNotNull('lines_synced_at')
            ->update(['lines_synced_at' => null]);
    }

    /**
     * Rechnungen und Gutschriften, deren Positionen fehlen — ohne Entwürfe
     * (sie ändern sich noch) und ohne Belege im Fehler-Backoff (Reihenfolge:
     * syncMissing). Stornierte bleiben drin: ihre Positionen erklären Lücken.
     *
     * @return Builder<LexofficeVoucher>
     */
    public function pending(int $organizationId): Builder {
        return LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('voucher_type', self::VOUCHER_TYPES)
            ->where('archived', false)
            ->where(static fn(Builder $q) => $q->whereNull('voucher_status')->orWhere('voucher_status', '<>', 'draft'))
            ->whereNull('lines_synced_at')
            ->where(static fn(Builder $q) => self::retryDue($q));
    }

    /**
     * Nie fehlgeschlagen — oder Fehlschlag älter als 2^n Stunden (n =
     * Versuche), ab 8 Versuchen älter als 7 Tage. Obergrenze halboffen (`<`).
     *
     * @param  Builder<LexofficeVoucher>  $q
     */
    private static function retryDue(Builder $q): void {
        $now = now();
        $q->whereNull('lines_sync_failed_at');
        for ($attempts = 1; $attempts <= self::BACKOFF_STEPS; $attempts++) {
            $before = $now->copy()->subHours(2 ** $attempts)->toDateTimeString();
            $q->orWhere(static fn(Builder $w) => $w
                ->where('lines_sync_attempts', $attempts === 1 ? '<=' : '=', $attempts)
                ->where('lines_sync_failed_at', '<', $before));
        }
        $q->orWhere(static fn(Builder $w) => $w
            ->where('lines_sync_attempts', '>', self::BACKOFF_STEPS)
            ->where('lines_sync_failed_at', '<', $now->copy()->subHours(self::MAX_BACKOFF_HOURS)->toDateTimeString()));
    }

    /**
     * Fehlermarker setzen (Query statt Model-Save: ein Abbruch mitten in der
     * Transaktion darf keine halb gefüllten Belegfelder mitschreiben).
     */
    private function markFailed(LexofficeVoucher $voucher, Throwable $e): void {
        LexofficeVoucher::query()->withoutGlobalScopes()->whereKey($voucher->id)->update([
            'lines_sync_failed_at' => now(),
            'lines_sync_attempts' => DB::raw('lines_sync_attempts + 1'),
        ]);
        Log::warning('LexofficeVoucherLineSync: Positionen nicht geladen.', [
            'organization_id' => $voucher->organization_id,
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'error' => self::describe($e),
        ]);
    }

    /** Fehlertext ohne Secrets/Bodies: Klasse + gekürzte Meldung, Bearer-Token geschwärzt. */
    private static function describe(Throwable $e): string {
        $message = (string) preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $e->getMessage());

        return class_basename($e) . ': ' . mb_substr($message, 0, 200);
    }

    /**
     * Gleiche Position, wenn die Lexoffice-Artikel-ID übereinstimmt; ohne
     * Artikel-ID entscheidet der Name.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function sameIdentity(LexofficeVoucherLine $current, array $attributes): bool {
        $oldArticle = (string) $current->external_article_id;
        $newArticle = (string) ($attributes['external_article_id'] ?? '');
        if ($oldArticle !== '' && $newArticle !== '') {
            return $oldArticle === $newArticle;
        }

        return $current->name === (string) ($attributes['name'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warnLinked(LexofficeVoucherLine $line, string $message, array $context): void {
        $links = ResalePeriodLink::query()->withoutGlobalScopes()
            ->where('linkable_type', $line->getMorphClass())
            ->where('linkable_id', $line->id)
            ->count();
        if ($links === 0) {
            return;
        }
        Log::warning('LexofficeVoucherLineSync: ' . $message, $context + [
            'organization_id' => $line->organization_id,
            'voucher_id' => $line->voucher_id,
            'line_id' => $line->id,
            'position' => $line->position,
            'links' => $links,
        ]);
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

    /**
     * @return array<string, mixed>|null  null bei 404 (in Lexoffice gelöscht)
     */
    private function getJson(string $path, string $action): ?array {
        // 429/5xx wiederholt der Client (MAX_RETRIES, Retry-After); was danach
        // noch fehlschlägt, ist ein normaler API-Fehler des Belegs.
        $response = $this->api()->getResponse($this->baseUrl . $path);
        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw LexofficeApiException::fromResponse($response, 'Lexoffice', $action);
        }
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $this->baseUrl, $this->requestInterval);
            $this->api->setAuthentication(new BearerAuthentication($this->apiKey));
            $this->api->setMaxRetries(self::MAX_RETRIES);
        }

        return $this->api;
    }
}
