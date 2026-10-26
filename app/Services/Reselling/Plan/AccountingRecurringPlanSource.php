<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingRecurringPlanSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Enums\Billing\DocumentKind;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\Organization;
use App\Services\Reselling\Register\LicenseArticleClassifier;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Plan-Quelle über den allgemeinen Belegspiegel (Feature 185, MVP-1426):
 * Serienvorlagen, die ein Puller als Beleg der Art `recurring_template`
 * spiegelt (easybill RECURRING-Dokumente, Invoice Ninja `recurring_invoices`,
 * sevDesk Rechnungen vom Typ WKR). Die programmspezifische Deutung von
 * Status und Rhythmus trifft der Puller und legt sie normalisiert unter
 * `payload.plan` ab; Positionen liegen wie bei Rechnungen in
 * `accounting_voucher_lines`.
 *
 * @phpstan-type PlanPayload array{status?: string, interval?: string, starts_on?: string|null, ends_on?: string|null, next_execution_on?: string|null, last_execution_on?: string|null, finalize?: bool, last_failed?: bool, last_error?: string|null, permalink?: string|null}
 */
final class AccountingRecurringPlanSource implements RecurringPlanSource {
    public const KEY = 'accounting';

    public function key(): string {
        return self::KEY;
    }

    public function availableFor(Organization $organization): bool {
        return $this->query($organization)->exists();
    }

    public function templatesFor(Organization $organization): Collection {
        $classifier = app(LicenseArticleClassifier::class);

        return $this->query($organization)->with('lines')->orderBy('voucher_number')->orderBy('id')->get()
            ->map(fn(AccountingVoucher $voucher): PlanTemplate => $this->toTemplate($voucher, $classifier))
            ->values();
    }

    public function templateByKey(Organization $organization, string $key): ?PlanTemplate {
        $prefix = self::KEY . ':';
        if (! str_starts_with($key, $prefix)) {
            return null;
        }
        [$pluginId, $externalId] = array_pad(explode(':', substr($key, strlen($prefix)), 2), 2, '');
        if ($pluginId === '' || $externalId === '') {
            return null;
        }
        $voucher = $this->query($organization)->where('plugin_id', $pluginId)->where('external_id', $externalId)->with('lines')->first();

        return $voucher === null ? null : $this->toTemplate($voucher, app(LicenseArticleClassifier::class));
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return $this->query($organization)->where('customer_id', $recipient->id)->exists();
    }

    /** Schlüssel einer gespiegelten Vorlage: `accounting:<plugin>:<externe ID>`. */
    public static function keyFor(AccountingVoucher $voucher): string {
        return PlanTemplate::keyOf(self::KEY, $voucher->plugin_id . ':' . $voucher->external_id);
    }

    /** @return Builder<AccountingVoucher> */
    private function query(Organization $organization): Builder {
        return AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('document_kind', DocumentKind::RecurringTemplate->value)
            ->whereNull('source_deleted_at');
    }

    private function toTemplate(AccountingVoucher $voucher, LicenseArticleClassifier $classifier): PlanTemplate {
        $organizationId = (int) $voucher->organization_id;
        /** @var PlanPayload $plan */
        $plan = is_array($voucher->payload['plan'] ?? null) ? $voucher->payload['plan'] : [];
        $lines = [];
        foreach ($voucher->lines as $line) {
            $currency = CurrencyCode::tryFrom((string) $line->currency) ?? CurrencyCode::Euro;
            $lines[] = new PlanLine(
                position: (int) $line->position,
                articleKey: $line->article_ref,
                articleName: null,
                articleIsLicence: $classifier->isLicense($organizationId, $line->article_ref, $line->name),
                name: (string) $line->name,
                description: $line->description,
                quantity: (float) $line->quantity,
                unitName: $line->unit_name,
                unitNet: Money::of((string) $line->unit_net, $currency, 4),
                currency: $currency,
            );
        }
        $status = (string) ($plan['status'] ?? PlanTemplate::STATUS_PAUSED);

        return new PlanTemplate(
            sourceKey: self::KEY,
            key: self::keyFor($voucher),
            title: (string) ($voucher->voucher_number ?? ($voucher->plugin_id . ' #' . $voucher->external_id)),
            recipientCustomerId: $voucher->customer_id !== null ? (int) $voucher->customer_id : null,
            recipientName: $voucher->customer?->name,
            status: in_array($status, [PlanTemplate::STATUS_ACTIVE, PlanTemplate::STATUS_PAUSED, PlanTemplate::STATUS_ENDED], true) ? $status : PlanTemplate::STATUS_PAUSED,
            interval: (string) ($plan['interval'] ?? ''),
            startsOn: self::date($plan['starts_on'] ?? null),
            endsOn: self::date($plan['ends_on'] ?? null),
            nextExecutionOn: self::date($plan['next_execution_on'] ?? null),
            lastExecutionOn: self::date($plan['last_execution_on'] ?? null),
            finalize: (bool) ($plan['finalize'] ?? false),
            lastFailed: (bool) ($plan['last_failed'] ?? false),
            lastError: isset($plan['last_error']) ? (string) $plan['last_error'] : null,
            lines: $lines,
            permalink: isset($plan['permalink']) ? (string) $plan['permalink'] : null,
        );
    }

    private static function date(mixed $value): ?CarbonImmutable {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
