<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDuplicateFinder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\{Customer, CustomerMergeDismissal, Organization};
use App\Services\Integration\Match\{EntityMatcher, MatchStrategy};
use App\Services\Integration\Profiles\CustomerMatchProfile;
use Illuminate\Support\Collection;

/**
 * Findet Dubletten-Kandidaten unter den Kunden einer Organisation. Nutzt das
 * gemeinsame {@see CustomerMatchProfile} + {@see EntityMatcher} (keine eigene
 * Match-Logik mehr) und ergänzt die paarweise Confidence um die Ziel-Heuristik
 * (welcher Datensatz bestehen bleibt) sowie das Ausfiltern dismisster Paare.
 */
class CustomerDuplicateFinder {
    /** @deprecated Stufen entsprechen {@see MatchStrategy}; Konstanten für UI/Tests erhalten. */
    public const CONF_EXACT = MatchStrategy::EXACT;
    public const CONF_LIKELY = MatchStrategy::LIKELY;
    public const CONF_FUZZY = MatchStrategy::FUZZY;

    /** @var array<string, int> */
    private const RANK = [
        MatchStrategy::FUZZY => 1,
        MatchStrategy::LIKELY => 2,
        MatchStrategy::EXACT => 3,
    ];

    public function __construct(
        private readonly EntityMatcher $matcher,
        private readonly CustomerMatchProfile $profile,
    ) {}

    /**
     * @param  string|null  $onlyConfidence  Auf eine Stufe einschränken (z. B. nur 'exact').
     * @return Collection<int, array{source: Customer, target: Customer, confidence: string, reasons: list<string>}>
     */
    public function candidates(Organization $organization, ?string $onlyConfidence = null): Collection {
        /** @var Collection<int, Customer> $customers */
        $customers = $this->profile->candidates($organization)->withCount('projects')->get();

        $list = $customers->values();
        $count = $list->count();

        // Wertesätze einmal vorab extrahieren (statt O(n²)-mal).
        $fields = [];
        foreach ($list as $i => $customer) {
            $fields[$i] = $this->profile->extract($customer);
        }

        /** @var array<string, array{a: Customer, b: Customer, confidence: string, reasons: array<string, true>}> $pairs */
        $pairs = [];
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $cmp = $this->matcher->compare($this->profile, $fields[$i], $fields[$j]);
                if ($cmp['confidence'] === null) {
                    continue;
                }
                $a = $list[$i];
                $b = $list[$j];
                $key = min((int) $a->id, (int) $b->id) . '-' . max((int) $a->id, (int) $b->id);
                $pairs[$key] = [
                    'a' => $a,
                    'b' => $b,
                    'confidence' => $cmp['confidence'],
                    'reasons' => array_fill_keys($cmp['reasons'], true),
                ];
            }
        }

        $dismissed = $this->dismissedKeys($organization);

        return Collection::make($pairs)
            ->reject(fn(array $p): bool => isset($dismissed[min((int) $p['a']->id, (int) $p['b']->id) . '-' . max((int) $p['a']->id, (int) $p['b']->id)]))
            ->when($onlyConfidence !== null, fn(Collection $c) => $c->filter(fn(array $p): bool => $p['confidence'] === $onlyConfidence))
            ->map(function (array $p): array {
                [$target, $source] = $this->orderTargetSource($p['a'], $p['b']);

                return [
                    'source' => $source,
                    'target' => $target,
                    'confidence' => $p['confidence'],
                    'reasons' => array_keys($p['reasons']),
                ];
            })
            ->sortByDesc(fn(array $p): int => self::RANK[$p['confidence']])
            ->values();
    }

    /**
     * Bestimmt Ziel (bleibt) und Quelle (geht auf): Lexoffice-Anbindung > mehr
     * Projekte > kleinere (ältere) ID.
     *
     * @return array{0: Customer, 1: Customer}  [Ziel, Quelle]
     */
    private function orderTargetSource(Customer $a, Customer $b): array {
        $score = static function (Customer $c): array {
            $hasLex = trim((string) $c->lexoffice_contact_number) !== '' ? 1 : 0;

            return [$hasLex, (int) ($c->projects_count ?? 0), -((int) $c->id)];
        };

        return $score($a) >= $score($b) ? [$a, $b] : [$b, $a];
    }

    /**
     * @return array<string, true>
     */
    private function dismissedKeys(Organization $organization): array {
        return CustomerMergeDismissal::query()
            ->where('organization_id', $organization->id)
            ->get(['customer_low_id', 'customer_high_id'])
            ->mapWithKeys(fn(CustomerMergeDismissal $d): array => [
                $d->customer_low_id . '-' . $d->customer_high_id => true,
            ])
            ->all();
    }
}
