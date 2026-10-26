<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRecurringPlanSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Lexoffice\Models\LexofficeRecurringTemplate;
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Services\Reselling\Plan\{PlanLine, PlanTemplate, RecurringPlanSource};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;

/**
 * Plan-Quelle Lexoffice (Feature 185, MVP-1409): die gespiegelten
 * Serienvorlagen als anbieterneutrale {@see PlanTemplate}s. Artikel über den
 * Katalogschlüssel (`lex:<id>`), Lizenzposition laut Einstufung.
 */
final class LexofficeRecurringPlanSource implements RecurringPlanSource {
    public const KEY = 'lexoffice';

    public function key(): string {
        return self::KEY;
    }

    public function availableFor(Organization $organization): bool {
        return LexofficeConfig::resolve($organization->id)['enabled'] === true;
    }

    public function templatesFor(Organization $organization): Collection {
        $classifier = app(LicenseArticleClassifier::class);

        return LexofficeRecurringTemplate::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->with(['lines.article:id,name'])
            ->orderBy('title')
            ->get()
            ->map(fn(LexofficeRecurringTemplate $template): PlanTemplate => $this->toTemplate($template, $classifier))
            ->values();
    }

    public function templateByKey(Organization $organization, string $key): ?PlanTemplate {
        $prefix = self::KEY . ':';
        if (! str_starts_with($key, $prefix)) {
            return null;
        }
        $template = LexofficeRecurringTemplate::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('external_id', substr($key, strlen($prefix)))
            ->with(['lines.article:id,name'])
            ->first();

        return $template === null ? null : $this->toTemplate($template, app(LicenseArticleClassifier::class));
    }

    public function coversRecipient(Organization $organization, Customer $recipient): bool {
        return ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $recipient->getMorphClass())
            ->where('referenceable_id', $recipient->getKey())
            ->exists();
    }

    private function toTemplate(LexofficeRecurringTemplate $template, LicenseArticleClassifier $classifier): PlanTemplate {
        $organizationId = (int) $template->organization_id;
        $lines = [];
        foreach ($template->lines as $line) {
            $articleKey = $line->lexoffice_article_id !== null ? ArticleCatalog::key(LexofficeArticleCatalogSource::PREFIX, (int) $line->lexoffice_article_id) : null;
            $articleName = $line->article?->name;
            $currency = CurrencyCode::tryFrom((string) $line->currency) ?? CurrencyCode::Euro;
            $lines[] = new PlanLine(
                position: (int) $line->position,
                articleKey: $articleKey,
                articleName: $articleName,
                articleIsLicence: $classifier->isLicense($organizationId, $articleKey, $articleName),
                name: (string) $line->name,
                description: $line->description,
                quantity: (float) $line->quantity,
                unitName: $line->unit_name,
                unitNet: Money::of((string) $line->unit_net, $currency, 4),
                currency: $currency,
            );
        }

        return new PlanTemplate(
            sourceKey: self::KEY,
            key: PlanTemplate::keyOf(self::KEY, (string) $template->external_id),
            title: (string) ($template->title ?? ''),
            recipientCustomerId: $template->customer_id !== null ? (int) $template->customer_id : null,
            recipientName: $template->contact_name,
            status: $template->execution_status->value,
            interval: $template->execution_interval !== null ? $template->execution_interval->value : '',
            startsOn: $template->starts_on,
            endsOn: $template->ends_on,
            nextExecutionOn: $template->next_execution_on,
            lastExecutionOn: $template->last_execution_on,
            finalize: (bool) $template->is_finalized,
            lastFailed: (bool) $template->is_last_execution_failed,
            lastError: $template->last_execution_error,
            lines: $lines,
            permalink: $template->lexofficePermalink(),
        );
    }
}
