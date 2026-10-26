<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanLinkProposer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Enums\Reselling\LinkOrigin;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePlanLink, ResaleSubscription};
use App\Services\Reselling\Marketplace\{MarketplaceCompany, ProductNameMatcher};
use App\Services\Reselling\Register\LicenseAccounts;
use Illuminate\Support\Facades\DB;

/**
 * Vorschlagslauf Abo ↔ Vorlagenposition (Feature 185, MVP-1411): je
 * Lizenzposition einer Serienvorlage die Abos desselben Rechnungsempfängers
 * mit passendem Produkt — Artikelschlüssel vor Namensvergleich; bei mehreren
 * Endkunden desselben Produkts nur mit Nennung im Positions- oder
 * Vorlagentext. Bestätigte und manuelle Zuordnungen bleiben; Vorschläge
 * werden ersetzt. Die Lizenzen der Position verteilen sich auf die Abos
 * (größtes zuerst), was keinen Platz findet, bleibt „Vorlage ohne Abo".
 */
final class PlanLinkProposer {
    public function __construct(
        private readonly RecurringPlans $plans,
        private readonly LicenseAccounts $accounts,
        private readonly ProductNameMatcher $matcher = new ProductNameMatcher(),
    ) {}

    /**
     * @return array{templates: int, lines: int, links: int, unmatched: int}
     */
    public function propose(Organization $organization): array {
        $result = ['templates' => 0, 'lines' => 0, 'links' => 0, 'unmatched' => 0];
        if (! $this->plans->availableFor($organization)) {
            return $result;
        }
        $templates = $this->plans->templatesFor($organization);
        if ($templates->isEmpty()) {
            ResalePlanLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('origin', LinkOrigin::Proposed->value)->delete();

            return $result;
        }
        $subscriptions = $this->accounts->subscriptions($organization)
            ->filter(static fn(ResaleSubscription $s): bool => $s->hasHolder() && ! $s->is_own_holding && $s->status->isPlanning())
            ->values();

        DB::transaction(function () use ($organization, $templates, $subscriptions, &$result): void {
            ResalePlanLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('origin', LinkOrigin::Proposed->value)->delete();
            $decided = ResalePlanLink::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get();
            /** @var array<int, float> $linkedBySubscription Abo → schon zugeordnete Lizenzen (entschieden) */
            $linkedBySubscription = [];
            /** @var array<string, true> $decidedLines Vorlagenposition → hat eine entschiedene Zuordnung */
            $decidedLines = [];
            foreach ($decided as $link) {
                $linkedBySubscription[(int) $link->subscription_id] = ($linkedBySubscription[(int) $link->subscription_id] ?? 0.0) + $link->quantity;
                $decidedLines[$link->lineKey()] = true;
            }
            /** @var array<int, array<string, array<string, int>>> $holdersByProduct Empfänger → Produkt → Halter */
            $holdersByProduct = [];
            foreach ($subscriptions as $subscription) {
                $recipient = $subscription->billedTo()?->id;
                if ($recipient !== null) {
                    $holdersByProduct[$recipient][$subscription->productKey()][$subscription->holderKey()] = 1;
                }
            }

            foreach ($templates as $template) {
                $result['templates']++;
                if (! $template->isActive() || $template->recipientCustomerId === null) {
                    continue;
                }
                $intervalMonths = $template->intervalMonths();
                foreach ($template->licenceLines() as $line) {
                    $result['lines']++;
                    $lineKey = ResalePlanLink::lineKeyOf($template->key, $line->position);
                    if (isset($decidedLines[$lineKey])) {
                        continue; // Nutzerentscheidung bleibt
                    }
                    $remaining = $line->licences($intervalMonths);
                    $candidates = $subscriptions
                        ->filter(fn(ResaleSubscription $s): bool => $s->billedTo()?->id === $template->recipientCustomerId && $this->matchesProduct($s, $line))
                        ->filter(function (ResaleSubscription $s) use ($holdersByProduct, $template, $line): bool {
                            $owners = count($holdersByProduct[$template->recipientCustomerId][$s->productKey()] ?? []);
                            if ($owners <= 1 || $s->foreignCustomer === null) {
                                return true;
                            }

                            return self::mentions($s->foreignCustomer->name, $line->text() . ' ' . $template->title);
                        })
                        ->sortBy([['quantity', 'desc'], ['starts_on', 'asc'], ['id', 'asc']])
                        ->values();
                    foreach ($candidates as $subscription) {
                        if ($remaining <= 0.001) {
                            break;
                        }
                        $free = $subscription->quantity - ($linkedBySubscription[$subscription->id] ?? 0.0);
                        $take = (int) floor(min($remaining, max(0.0, $free)) + 0.001);
                        if ($take <= 0) {
                            continue;
                        }
                        ResalePlanLink::query()->create([
                            'organization_id' => $organization->id,
                            'subscription_id' => $subscription->id,
                            'plan_key' => $template->key,
                            'line_position' => $line->position,
                            'quantity' => $take,
                            'origin' => LinkOrigin::Proposed,
                        ]);
                        $linkedBySubscription[$subscription->id] = ($linkedBySubscription[$subscription->id] ?? 0.0) + $take;
                        $remaining -= $take;
                        $result['links']++;
                    }
                    if ($remaining > 0.001) {
                        $result['unmatched']++;
                    }
                }
            }
        });

        return $result;
    }

    /** Produkt der Position = Produkt des Abos: gleicher Artikelschlüssel, sonst Namensvergleich bei Lizenzpositionen. */
    private function matchesProduct(ResaleSubscription $subscription, PlanLine $line): bool {
        if ($line->articleKey !== null && $subscription->article_ref !== null) {
            return $line->articleKey === $subscription->article_ref;
        }
        $text = trim((string) $line->articleName . ' ' . $line->text());

        return $this->matcher->matches($subscription->label, $text) || $this->matcher->matches($subscription->productName(), $text);
    }

    /** Alle Kern-Tokens (≥ 4 Zeichen) des Namens im Text — die Nennung des Endkunden. */
    public static function mentions(string $name, string $text): bool {
        $haystack = ' ' . MarketplaceCompany::matchKey($text) . ' ';
        $tokens = array_values(array_filter(explode(' ', MarketplaceCompany::matchKey($name)), static fn(string $t): bool => mb_strlen($t) >= 4));
        if ($tokens === []) {
            return false;
        }
        foreach ($tokens as $token) {
            if (! str_contains($haystack, ' ' . $token . ' ')) {
                return str_contains(str_replace(' ', '', $haystack), str_replace(' ', '', MarketplaceCompany::matchKey($name)));
            }
        }

        return true;
    }
}
