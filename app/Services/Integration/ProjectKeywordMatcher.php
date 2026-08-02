<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectKeywordMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\Project\ProjectStatus;
use App\Models\{Customer, ForeignCustomer, Organization, Project};
use CommonToolkit\Enums\SearchMode;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ordnet importierte Zeiten anhand ihres Textes einem Projekt zu (MVP-483):
 * „Installation DATEV-Updates" landet im Projekt „DATEV" **desselben Kunden**,
 * statt im Standardprojekt („Wartung") bzw. in der Zuordnungs-Inbox.
 *
 * Schlüsselwörter sind (a) die aus dem Projektnamen abgeleiteten Begriffe —
 * ohne jede Pflege — und (b) gepflegte Synonyme aus `projects.keywords`, für
 * Texte, in denen der Projektname nicht vorkommt („Lohn" → „LODAS").
 *
 * Zwei Leitplanken: **nie kundenübergreifend** raten (ohne Kunden-/Fremdkunden-
 * Kontext gibt es keinen Treffer) und **nur eindeutig** buchen — treffen zwei
 * Projekte gleich gut, bleibt es beim bisherigen Verhalten.
 */
class ProjectKeywordMatcher {
    /**
     * @param  string  ...$texts  Beschreibung, Tätigkeit, Notiz … — Reihenfolge egal
     */
    public function match(Organization $organization, Customer|ForeignCustomer|null $scope, string ...$texts): ?ProjectKeywordHit {
        if ($scope === null || ! $this->enabledFor($organization)) {
            return null;
        }

        $haystack = self::normalize(implode(' ', $texts));
        if ($haystack === '') {
            return null;
        }

        $minLength = $this->minTokenLength($organization);
        $stopwords = $this->stopwords($organization);

        $best = null;
        $bestScore = [0, 0];
        $ambiguous = false;

        foreach ($this->candidates($organization, $scope) as $project) {
            $hit = $this->bestHitFor($project, $haystack, $minLength, $stopwords);
            if ($hit === null) {
                continue;
            }

            $score = [mb_strlen($hit->keyword), $hit->explicit ? 1 : 0];
            if ($score > $bestScore) {
                $best = $hit;
                $bestScore = $score;
                $ambiguous = false;
            } elseif ($score === $bestScore && $best !== null && $best->project->getKey() !== $project->getKey()) {
                $ambiguous = true;
            }
        }

        return $ambiguous ? null : $best;
    }

    /**
     * Bester Treffer eines Projekts: längster passender Begriff, gepflegtes
     * Synonym schlägt bei gleicher Länge das abgeleitete Namenswort.
     *
     * @param  list<string>  $stopwords
     */
    private function bestHitFor(Project $project, string $haystack, int $minLength, array $stopwords): ?ProjectKeywordHit {
        $best = null;
        $bestScore = [0, 0];

        foreach ($this->keywordsOf($project, $minLength, $stopwords) as $keyword => $explicit) {
            $keyword = (string) $keyword;
            if (! StringHelper::containsKeyword($haystack, $keyword, SearchMode::CONTAINS)) {
                continue;
            }

            $score = [mb_strlen($keyword), $explicit ? 1 : 0];
            if ($score > $bestScore) {
                $best = new ProjectKeywordHit($project, $keyword, $explicit);
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Begriffe eines Projekts: gepflegte Synonyme + aus dem Namen abgeleitete
     * Wörter + der vollständige Name (längster und damit stärkster Treffer).
     *
     * @param  list<string>  $stopwords
     * @return array<string, bool>  Begriff => gepflegt
     */
    private function keywordsOf(Project $project, int $minLength, array $stopwords): array {
        $keywords = [];

        foreach (is_array($project->keywords) ? $project->keywords : [] as $keyword) {
            $keyword = self::normalize((string) $keyword);
            if (mb_strlen($keyword) >= 3) {
                $keywords[$keyword] = true;
            }
        }

        $name = self::normalize($project->name);
        if (mb_strlen($name) >= $minLength && ! in_array($name, $stopwords, true)) {
            $keywords[$name] ??= false;
        }

        foreach (preg_split('/[^\p{L}\p{N}]+/u', $name) ?: [] as $token) {
            // Reine Zahlen ("2026") und Allerweltswörter träfen wahllos.
            if (mb_strlen($token) >= $minLength
                && preg_match('/\p{L}/u', $token) === 1
                && ! in_array($token, $stopwords, true)) {
                $keywords[$token] ??= false;
            }
        }

        return $keywords;
    }

    /**
     * Zuordenbare Projekte des Kunden bzw. Endkunden. Das Standardprojekt
     * bleibt außen vor — dorthin führt ohnehin der Fallback der Aufrufer.
     *
     * @return Collection<int, Project>
     */
    private function candidates(Organization $organization, Customer|ForeignCustomer $scope): Collection {
        $query = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->where('status', '!=', ProjectStatus::Archived->value)
            ->where('is_default', false);

        if ($scope instanceof ForeignCustomer) {
            $query->where('customer_id', $scope->customer_id)->where('foreign_customer_id', $scope->id);
        } else {
            // Ohne Endkunden-Kontext nur Firmen-Projekte — Endkunden-Projekte
            // gehören fachlich zu einem anderen Ansprechpartner.
            $query->where('customer_id', $scope->id)->whereNull('foreign_customer_id');
        }

        return $query->get();
    }

    private function enabledFor(Organization $organization): bool {
        return (bool) ($this->settings($organization)['enabled'] ?? true);
    }

    private function minTokenLength(Organization $organization): int {
        return max(3, (int) ($this->settings($organization)['min_token_length'] ?? 4));
    }

    /** @return list<string> */
    private function stopwords(Organization $organization): array {
        $stopwords = $this->settings($organization)['stopwords'] ?? [];

        return array_values(array_map(
            static fn(mixed $word): string => self::normalize((string) $word),
            is_array($stopwords) ? $stopwords : [],
        ));
    }

    /**
     * Org-explizit statt über den ambienten `currentOrganization`-Kontext:
     * Scheduler-Importe laufen je Mandant, ein ambienter Fallback würde dort
     * mit fremden Einstellungen arbeiten.
     *
     * @return array<string, mixed>
     */
    private function settings(Organization $organization): array {
        $settings = $organization->groupSettings('project')['keyword_matching'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    /** Kleinschreibung + kollabierte Leerzeichen — beide Seiten identisch behandelt. */
    private static function normalize(string $value): string {
        return mb_strtolower(trim(StringHelper::normalizeWhitespace($value)));
    }
}
