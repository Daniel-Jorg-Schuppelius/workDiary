<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventMatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Profiles;

use App\Models\{Event, Organization};
use App\Services\Integration\Match\{AbstractMatchProfile, ExactField, FuzzyField, MatchStrategy};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Abgleich-Profil für Termine (Feature 102, C3-Übernahme): trägt vor allem
 * die `create()`-Seite der Integrations-Inbox — „Neu anlegen" macht aus
 * einem Kalender-VORSCHLAG (Zwei-Wege-Rückimport) ein WorkDiary-Event; die
 * ExternalReference schreibt der {@see \App\Services\Integration\InboxActionService}
 * aus dem Inbox-Item (plugin/typ/remote-ID), womit der Publish den Termin
 * fortan führt. Matching-Strategien: identischer Titel + Beginn (likely),
 * ähnlicher Titel (fuzzy).
 *
 * @extends AbstractMatchProfile<Event>
 */
class EventMatchProfile extends AbstractMatchProfile {
    /** Schwelle für die Titel-Ähnlichkeit. */
    public const FUZZY_THRESHOLD = 0.86;

    public function targetType(): string {
        return Event::class;
    }

    protected function newCandidateQuery(): Builder {
        return Event::query();
    }

    public function strategies(): array {
        return [
            new ExactField('title', MatchStrategy::LIKELY, 'title'),
            new FuzzyField(['title'], self::FUZZY_THRESHOLD, 'title_similar'),
        ];
    }

    public function display(array $mapped): array {
        $title = (string) ($mapped['title'] ?? '');

        return [
            'title' => $title !== '' ? $title : (string) __('(ohne Titel)'),
            'subtitle' => ($mapped['started_at'] ?? null) !== null ? (string) $mapped['started_at'] : null,
        ];
    }

    public function create(Organization $organization, array $mapped): Model {
        $attributes = array_intersect_key($mapped, array_flip((new Event)->getFillable()));
        $attributes['organization_id'] = $organization->id;

        return Event::create($attributes);
    }
}
