<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerChannelResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Finance\BillingMode;
use App\Models\Customer\Customer;
use App\Services\Billing\Contracts\{RetainerPublisher, RetainerVoucherLinks};
use Closure;

/**
 * Welches Buchhaltungsprogramm führt die Pauschalen eines Kunden
 * (Feature 098, MVP-1027)? Gleiche Registry-Mechanik wie
 * {@see ExpenseLinkProviderResolver}: das Plugin registriert beim Booten, der
 * Kern kennt keine Plugin-Klasse. Maßgeblich ist die Rechnungshoheit des
 * Kunden — der Plugin-Schlüssel ist der Wert von {@see BillingMode}.
 */
final class RetainerChannelResolver {
    /** @var array<string, array{label: string, publisher: Closure(): RetainerPublisher, links: Closure(): RetainerVoucherLinks}> */
    private array $channels = [];

    /**
     * @param  Closure(): RetainerPublisher  $publisher
     * @param  Closure(): RetainerVoucherLinks  $links
     */
    public function register(string $pluginId, string $label, Closure $publisher, Closure $links): void {
        $this->channels[$pluginId] = ['label' => $label, 'publisher' => $publisher, 'links' => $links];
    }

    public function supports(BillingMode $mode): bool {
        return isset($this->channels[$mode->value]);
    }

    /** @return list<string> Anzeigenamen der Programme, die Pauschalen führen können */
    public function labels(): array {
        return array_values(array_map(static fn (array $channel): string => $channel['label'], $this->channels));
    }

    /** Anzeigename ohne den Kanal aufzubauen; null = kein Programm für diesen Kunden. */
    public function labelFor(Customer $customer): ?string {
        return $this->channelFor($customer)['label'] ?? null;
    }

    public function publisherFor(Customer $customer): RetainerPublisher {
        $channel = $this->channelFor($customer);

        return $channel !== null ? ($channel['publisher'])() : new NullRetainerPublisher;
    }

    public function linksFor(Customer $customer): RetainerVoucherLinks {
        $channel = $this->channelFor($customer);

        return $channel !== null ? ($channel['links'])() : new NullRetainerVoucherLinks;
    }

    /** @return array{label: string, publisher: Closure(): RetainerPublisher, links: Closure(): RetainerVoucherLinks}|null */
    private function channelFor(Customer $customer): ?array {
        return $this->channels[app(BillingModeResolver::class)->effectiveFor($customer)->value] ?? null;
    }
}
