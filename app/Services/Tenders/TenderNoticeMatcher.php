<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNoticeMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Tenders;

use App\Models\Tenders\{TenderFilterProfile, TenderNotice, TenderNoticeMatch};

/**
 * Gleicht Bekanntmachungen gegen die Suchprofile der Organisationen ab
 * (MVP-630).
 *
 * Ein Profil beschreibt, was ein Betrieb überhaupt bedienen kann: **CPV** sagt,
 * was beschafft wird, **NUTS**, wo. Beides ist hierarchisch — wer `45` sucht,
 * meint alle Bauleistungen, wer `DEA` sucht, ganz Nordrhein-Westfalen. Deshalb
 * wird auf Präfix verglichen, nicht auf Gleichheit.
 *
 * **Ausschlusswörter wiegen schwerer als Stichwörter.** Wer „Abbruch"
 * ausschließt, will keine Abbrucharbeiten sehen, auch wenn der CPV-Code passt;
 * ein Ausschluss verwirft deshalb sofort.
 */
final class TenderNoticeMatcher {
    /**
     * Gleicht eine Menge Bekanntmachungen gegen alle aktiven Profile ab und
     * legt neue Treffer an.
     *
     * @param  iterable<TenderNotice> $notices
     * @return int Zahl der neu angelegten Treffer
     */
    public function match(iterable $notices): int {
        // Ohne globalen Scope: Der Abgleich läuft im Cron ohne angemeldete
        // Person und ordnet gerade erst zu, wen eine Bekanntmachung angeht -
        // ein Mandantenfilter hätte hier nichts zu filtern und würde alles
        // verwerfen.
        $profiles = TenderFilterProfile::query()
            ->withoutGlobalScopes()
            ->where('active', true)
            ->get();
        if ($profiles->isEmpty()) {
            return 0;
        }

        $created = 0;
        foreach ($notices as $notice) {
            foreach ($profiles as $profile) {
                if (!$this->matches($notice, $profile)) {
                    continue;
                }

                // Eine Bekanntmachung erscheint je Organisation einmal - auch
                // wenn mehrere Profile greifen, ist es dieselbe Ausschreibung.
                $match = TenderNoticeMatch::query()->firstOrCreate(
                    [
                        'organization_id' => $profile->organization_id,
                        'tender_notice_id' => $notice->id,
                    ],
                    [
                        'tender_filter_profile_id' => $profile->id,
                        'state' => TenderNoticeMatch::STATE_NEW,
                    ],
                );

                if ($match->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /** Passt diese Bekanntmachung zu diesem Profil? */
    public function matches(TenderNotice $notice, TenderFilterProfile $profile): bool {
        $haystack = mb_strtolower(trim(
            $notice->title . ' ' . (string) $notice->summary . ' ' . (string) $notice->buyer_name
        ));

        // Ausgeschlossene Auftraggeber gegen das Auftraggeberfeld, nicht gegen
        // den Fließtext: „Stadt Bonn" als Ausschlusswort verwürfe auch eine
        // Bekanntmachung, die die Stadt nur nebenbei erwähnt.
        $buyer = mb_strtolower(trim((string) $notice->buyer_name));
        if ($buyer !== '') {
            foreach ($this->words($profile->excluded_buyers) as $excluded) {
                if (str_contains($buyer, $excluded)) {
                    return false;
                }
            }
        }

        // Ausschluss zuerst: Er verwirft, egal wie gut der Rest passt.
        foreach ($this->words($profile->excluded_keywords) as $word) {
            if (str_contains($haystack, $word)) {
                return false;
            }
        }

        if (!$this->withinValue($notice, $profile)) {
            return false;
        }

        // Ohne jedes Kriterium wäre das Profil ein Abonnement auf alles - dann
        // trifft es auch alles, aber bewusst.
        $criteria = 0;
        $hits = 0;

        $cpv = $this->codes($profile->cpv_codes);
        if ($cpv !== []) {
            $criteria++;
            $hits += $this->matchesPrefix($notice->cpv_codes ?? [], $cpv) ? 1 : 0;
        }

        $nuts = $this->codes($profile->nuts_codes);
        if ($nuts !== []) {
            $criteria++;
            $hits += $this->matchesPrefix(array_filter([$notice->nuts_code]), $nuts) ? 1 : 0;
        }

        $keywords = $this->words($profile->keywords);
        if ($keywords !== []) {
            $criteria++;
            foreach ($keywords as $word) {
                if (str_contains($haystack, $word)) {
                    $hits++;

                    break;
                }
            }
        }

        // Alle gesetzten Kriterien müssen zutreffen: Ein Profil aus „Bau in
        // Bonn" meint beides, nicht das eine oder das andere.
        return $criteria === 0 || $hits === $criteria;
    }

    /**
     * Präfixvergleich: `45` trifft `45210000`, `DEA` trifft `DEA22`. CPV und
     * NUTS sind Hierarchien — auf Gleichheit zu prüfen hieße, nur die
     * unterste Ebene zu finden.
     *
     * @param iterable<string> $values
     * @param list<string>     $wanted
     */
    private function matchesPrefix(iterable $values, array $wanted): bool {
        foreach ($values as $value) {
            $normalised = strtoupper(str_replace('-', '', (string) $value));
            foreach ($wanted as $prefix) {
                if ($prefix !== '' && str_starts_with($normalised, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function withinValue(TenderNotice $notice, TenderFilterProfile $profile): bool {
        $value = $notice->estimated_value === null ? null : (float) $notice->estimated_value;
        if ($value === null) {
            // Ohne Wertangabe darf keine Wertgrenze ausschließen - sonst
            // verschwinden Ausschreibungen, die ihren Wert nicht nennen.
            return true;
        }

        if ($profile->min_value !== null && $value < (float) $profile->min_value) {
            return false;
        }

        return !($profile->max_value !== null && $value > (float) $profile->max_value);
    }

    /**
     * @return list<string>
     */
    private function codes(mixed $codes): array {
        return array_values(array_filter(array_map(
            static fn (mixed $code): string => strtoupper(str_replace('-', '', trim((string) $code))),
            is_array($codes) ? $codes : []
        )));
    }

    /**
     * @return list<string>
     */
    private function words(mixed $words): array {
        return array_values(array_filter(array_map(
            static fn (mixed $word): string => mb_strtolower(trim((string) $word)),
            is_array($words) ? $words : []
        )));
    }
}
