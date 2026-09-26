<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherCategorySync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\Platform\Organization;
use App\Models\Plugins\Lexoffice\{LexofficePostingCategory, LexofficeVoucher, LexofficeVoucherCategory};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Support\Billing\VoucherTypes;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Log};
use Throwable;

/**
 * Kategoriezeilen der Einkaufsbelege nachladen (MVP-905): `/vouchers/{id}`
 * liefert `voucherItems` mit Betrag, Steuer und `categoryId`; dazu der
 * Spiegel der Buchungskategorien (`/posting-categories`). Grundlage der
 * Auswertung „Ausgaben je Kategorie“. Ein fehlerhafter Beleg bleibt offen und
 * wird im nächsten Lauf erneut versucht.
 */
final class LexofficeVoucherCategorySync {
    private ?PluginApiClient $api = null;

    private float $requestInterval;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
        ?float $requestInterval = null,
    ) {
        $this->requestInterval = $requestInterval ?? LexofficeConfig::requestInterval();
    }

    /** Tests: kein Anfrageabstand. */
    public function withoutThrottle(): self {
        $this->requestInterval = 0.0;
        $this->api = null;

        return $this;
    }

    /** @return array{synced: int, failed: int, remaining: int} */
    public function syncMissing(Organization|int $organization, int $limit = 100): array {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $query = $this->pending($organizationId);
        $remaining = (clone $query)->count();
        $synced = 0;
        $failed = 0;
        $batch = (clone $query)->orderByDesc('voucher_date')->orderByDesc('id')->limit($limit)->get();
        if ($batch->isNotEmpty()) {
            $this->syncPostingCategories($organizationId);
        }
        foreach ($batch as $voucher) {
            try {
                $this->syncVoucher($voucher);
                $synced++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('LexofficeVoucherCategorySync: Kategorien nicht geladen.', [
                    'organization_id' => $organizationId,
                    'voucher_id' => $voucher->id,
                    'error' => class_basename($e) . ': ' . mb_substr((string) preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $e->getMessage()), 0, 200),
                ]);
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'remaining' => max(0, $remaining - $synced)];
    }

    public function syncVoucher(LexofficeVoucher $voucher): int {
        $json = $this->getJson('/vouchers/' . $voucher->external_id, 'Beleg abrufen');
        $rows = [];
        if ($json !== null) {
            $gross = strtolower((string) ($json['taxType'] ?? 'net')) === 'gross';
            foreach (array_values((array) ($json['voucherItems'] ?? [])) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $amount = NumberHelper::normalizeDecimalString((string) ($item['amount'] ?? '0'));
                $tax = NumberHelper::normalizeDecimalString((string) ($item['taxAmount'] ?? '0'));
                $rows[] = [
                    'organization_id' => $voucher->organization_id,
                    'voucher_id' => $voucher->id,
                    'position' => $index + 1,
                    'category_external_id' => isset($item['categoryId']) ? (string) $item['categoryId'] : null,
                    'net_amount' => bcsub($amount, $gross ? $tax : '0', 2),
                    'currency' => $voucher->currency->value,
                    'tax_rate' => isset($item['taxRatePercent']) ? NumberHelper::normalizeDecimalString((string) $item['taxRatePercent']) : null,
                ];
            }
        }

        DB::transaction(function () use ($voucher, $rows): void {
            LexofficeVoucherCategory::query()->withoutGlobalScopes()->where('voucher_id', $voucher->id)->delete();
            foreach ($rows as $row) {
                LexofficeVoucherCategory::query()->create($row);
            }
            $voucher->forceFill(['categories_synced_at' => now()])->save();
        });

        return count($rows);
    }

    public function syncPostingCategories(int $organizationId): int {
        $json = $this->getJson('/posting-categories', 'Buchungskategorien abrufen') ?? [];
        $count = 0;
        foreach ($json as $category) {
            if (! is_array($category) || ! isset($category['id'], $category['name'])) {
                continue;
            }
            LexofficePostingCategory::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organizationId, 'external_id' => (string) $category['id']],
                ['name' => (string) $category['name'], 'kind' => (string) ($category['type'] ?? 'outgo'), 'group_name' => isset($category['groupName']) ? (string) $category['groupName'] : null],
            );
            $count++;
        }

        return $count;
    }

    /** @return Builder<LexofficeVoucher> Einkaufsbelege ohne Kategoriezeilen, ohne Entwürfe und Archiv. */
    public function pending(int $organizationId): Builder {
        return LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('voucher_type', VoucherTypes::EXPENSES)
            ->where('archived', false)
            ->where(static fn (Builder $q) => $q->whereNull('voucher_status')->orWhere('voucher_status', '<>', 'draft'))
            ->whereNull('categories_synced_at');
    }

    /** @return array<array-key, mixed>|null null bei 404 */
    private function getJson(string $path, string $action): ?array {
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
            $this->api->setMaxRetries(LexofficeVoucherLineSync::MAX_RETRIES);
        }

        return $this->api;
    }
}
