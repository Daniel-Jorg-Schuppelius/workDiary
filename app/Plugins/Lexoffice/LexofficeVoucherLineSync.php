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
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Positionen der gespiegelten Lexoffice-Rechnungen nachladen (Feature 152,
 * MVP-760 = Feature 140 Schnitt 2): je Rechnung ohne `lines_synced_at` ein
 * `GET /invoices/{id}`, Positionen und Belegtexte in den Spiegel. Nur
 * Lexoffice-eigene Rechnungen (`invoice`) — Buchungsbelege haben keine
 * Positionen. Ratenlimit über den Client-Anfrageabstand, 429 mit Retry-After.
 */
final class LexofficeVoucherLineSync {
    public const VOUCHER_TYPES = ['invoice'];

    private ?PluginApiClient $api = null;

    private float $requestInterval;

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
        foreach ((clone $query)->orderByDesc('voucher_date')->orderByDesc('id')->limit($limit)->get() as $voucher) {
            try {
                $lines += $this->syncVoucher($voucher);
                $synced++;
            } catch (LexofficeApiException) {
                $failed++;
            }
        }

        return ['synced' => $synced, 'lines' => $lines, 'failed' => $failed, 'remaining' => max(0, $remaining - $synced)];
    }

    /**
     * Positionen EINER Rechnung laden und ersetzen. Liefert die Zahl der Positionen.
     */
    public function syncVoucher(LexofficeVoucher $voucher): int {
        $invoice = $this->getJson('/invoices/' . $voucher->external_id, 'Rechnung abrufen');
        if ($invoice === null) {
            // In Lexoffice gelöscht: als erledigt markieren, nicht ewig neu versuchen.
            $voucher->forceFill(['lines_synced_at' => now()])->save();

            return 0;
        }
        $parsed = LexofficeInvoiceParser::parse($invoice);
        $articleIds = $this->articleIds($voucher->organization_id, array_column($parsed['lines'], 'external_article_id'));

        DB::transaction(function () use ($voucher, $parsed, $articleIds): void {
            LexofficeVoucherLine::query()->withoutGlobalScopes()->where('voucher_id', $voucher->id)->delete();
            foreach ($parsed['lines'] as $line) {
                LexofficeVoucherLine::query()->create([
                    'organization_id' => $voucher->organization_id,
                    'voucher_id' => $voucher->id,
                    'position' => $line['position'],
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
                ]);
            }
            $voucher->forceFill([
                'voucher_text' => $parsed['voucher_text'] !== '' ? $parsed['voucher_text'] : null,
                'recipient_name' => $parsed['recipient'] !== '' ? mb_substr($parsed['recipient'], 0, 255) : null,
                'lines_synced_at' => now(),
            ])->save();
        });

        return count($parsed['lines']);
    }

    /**
     * @return Builder<LexofficeVoucher>
     */
    public function pending(int $organizationId): Builder {
        return LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('voucher_type', self::VOUCHER_TYPES)
            ->where('archived', false)
            ->whereNull('lines_synced_at');
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
        $attempts = 0;
        do {
            $response = $this->api()->getResponse($this->baseUrl . $path, []);
            if ($response->status() === 429 && $attempts < 5) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                if ($this->requestInterval > 0) {
                    usleep(max($retryAfter, 1) * 1_000_000);
                }
                $attempts++;

                continue;
            }
            if ($response->status() === 404) {
                return null;
            }
            if (! $response->successful()) {
                throw LexofficeApiException::fromResponse($response, 'Lexoffice', $action);
            }
            $json = $response->json();

            return is_array($json) ? $json : [];
        } while ($attempts <= 5);

        throw new RuntimeException('Lexoffice-Anfrage nach Ratelimit-Wiederholungen fehlgeschlagen: ' . $action);
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $this->baseUrl, $this->requestInterval);
            $this->api->setAuthentication(new BearerAuthentication($this->apiKey));
        }

        return $this->api;
    }
}
