<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationDossierService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningEnrollmentStatus;
use App\Models\Learning\{LearningCertificate, LearningEnrollment};
use App\Models\{Organization, User, UserQualification};
use App\Models\Safety\SafetyInstructionParticipant;
use App\Models\Training\TrainingAssignment;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\{Carbon, Collection};

/**
 * Qualifikationsnachweis nach außen (Feature 149, MVP-750).
 *
 * Adressaten sind Auditoren (ISO 9001/45001), Auftraggeber
 * (Präqualifikation, Nachunternehmerprüfung), die Aufsicht nach einem
 * Vorfall und Kunden.
 *
 * **Stichtagsfähigkeit ist der Kern.** Die Frage nach einem Unfall lautet
 * „war diese Person **am 14. März** unterwiesen?" — nicht „ist sie es
 * heute?". Deshalb rechnet jede Zeile gegen den Stichtag und nutzt die
 * historischen Nachweiszeilen.
 *
 * **Kein neuer Datenbestand:** die Mappe liest Qualifikationen (013),
 * Unterweisungen (132), Schulungs-Soll (145) und Zertifikate (149).
 *
 * Es gibt zwei Ausprägungen. Die **aggregierte** ist die Vorgabe: sie
 * beantwortet „wie viele der Eingesetzten haben den Nachweis" ohne Namen zu
 * nennen. Die **namentliche** ist die Ausnahme und gehört protokolliert —
 * sie ist eine Weitergabe personenbezogener Daten.
 */
class QualificationDossierService {
    /**
     * Namentliche Mappe für eine Personengruppe über einen Zeitraum.
     *
     * Ohne `$to` ist der Zeitraum ein einzelner Tag — die frühere
     * Stichtags-Betrachtung als Sonderfall.
     *
     * @param  Collection<int, User>  $users
     * @return list<array{user: User, qualifications: list<array<string, mixed>>, instructions: list<array<string, mixed>>, certificates: list<array<string, mixed>>, open_obligations: int, coverage: string}>
     */
    public function forUsers(Collection $users, ?Carbon $on = null, ?Carbon $to = null): array {
        $on ??= Carbon::today();
        $to ??= $on;
        $userIds = $users->pluck('id');

        $qualifications = UserQualification::query()
            ->with('qualification')
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $instructions = SafetyInstructionParticipant::query()
            ->with('instruction')
            ->whereIn('user_id', $userIds)
            ->whereNotNull('signed_at')
            ->get()
            ->groupBy('user_id');

        $certificates = LearningCertificate::query()
            ->with('course')
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $obligations = TrainingAssignment::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('fulfilled_at')
            ->get()
            ->groupBy('user_id');

        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'user' => $user,
                'qualifications' => $q = $this->qualificationRows($qualifications->get($user->id, collect()), $on, $to),
                'instructions' => $i = $this->instructionRows($instructions->get($user->id, collect()), $on, $to),
                'certificates' => $c = $this->certificateRows($certificates->get($user->id, collect()), $on, $to),
                'open_obligations' => $open = $obligations->get($user->id, collect())->count(),
                // Zeilen-Ampel: schlechteste Deckung über alle Nachweisarten;
                // ein offenes Pflicht-Soll drückt sie höchstens auf „teilweise",
                // denn es sagt nichts über die vorhandenen Nachweise aus.
                'coverage' => $this->rowCoverage([...$q, ...$i, ...$c], $open),
            ];
        }

        return $rows;
    }

    /**
     * Aggregierte Auskunft **ohne Namen** — die Vorgabe für Angebote und
     * Vergabeunterlagen: „fünf Eingesetzte, alle mit gültigem Nachweis,
     * kürzeste Gültigkeit bis …".
     *
     * @param  Collection<int, User>  $users
     * @return array{people: int, covered: int, missing: int, earliest_expiry: string|null}
     */
    public function summarizeQualification(Collection $users, int $qualificationId, ?Carbon $on = null): array {
        $on ??= Carbon::today();

        $entries = UserQualification::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('qualification_id', $qualificationId)
            ->get();

        $covered = 0;
        $earliest = null;

        foreach ($entries as $entry) {
            // Punktbetrachtung: der entartete Zeitraum `on..on`.
            if ($this->coverage($entry->valid_from, $entry->valid_until, $on, $on) !== self::COVERAGE_FULL) {
                continue;
            }

            $covered++;

            // Ohne Ablaufdatum gibt es kein frühestes Ende.
            $until = $entry->valid_until;

            if ($until === null) {
                continue;
            }

            if ($earliest === null || $until->lessThan($earliest)) {
                $earliest = $until;
            }
        }

        return [
            'people' => $users->count(),
            'covered' => $covered,
            'missing' => max(0, $users->count() - $covered),
            'earliest_expiry' => $earliest?->toDateString(),
        ];
    }

    /**
     * Maschinenlesbares Paket der namentlichen Mappe — reproduzierbar, damit
     * zwei Läufe zum selben Stichtag identisch sind (Muster Z3-Export).
     *
     * @param  Collection<int, User>  $users
     * @return array<string, mixed>
     */
    public function exportPayload(Organization $organization, Collection $users, ?Carbon $on = null, ?Carbon $to = null): array {
        $on ??= Carbon::today();
        $to ??= $on;

        $people = [];
        foreach ($this->forUsers($users, $on, $to) as $row) {
            $people[] = [
                'name' => $row['user']->name,
                'qualifications' => $row['qualifications'],
                'instructions' => $row['instructions'],
                'certificates' => $row['certificates'],
                'open_obligations' => $row['open_obligations'],
                'coverage' => $row['coverage'],
            ];
        }

        // Stabile Reihenfolge: sonst unterscheiden sich zwei Läufe im Hash,
        // obwohl sie dasselbe aussagen.
        usort($people, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        $payload = [
            'format' => 'workdiary.learning.dossier',
            'format_version' => 1,
            'organization' => $organization->name,
            // Zeitraum statt Stichtag (MVP-750, 2026-09-01): `as_of` bleibt
            // als Beginn erhalten, damit bestehende Auswertungen des Formats
            // weiterlesen koennen; `as_of_to` ist neu.
            'as_of' => $on->toDateString(),
            'as_of_to' => $to->toDateString(),
            'people' => $people,
        ];

        $payload['hash'] = CryptoHelper::hash(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return $payload;
    }

    /**
     * @param  Collection<int, UserQualification>  $entries
     * @return list<array<string, mixed>>
     */
    private function qualificationRows(Collection $entries, Carbon $on, Carbon $to): array {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'name' => (string) ($entry->qualification->name ?? ''),
                'valid_from' => $entry->valid_from?->toDateString(),
                'valid_until' => $entry->valid_until?->toDateString(),
                'valid_on' => $this->coverage($entry->valid_from, $entry->valid_until, $on, $to) === self::COVERAGE_FULL,
                'coverage' => $this->coverage($entry->valid_from, $entry->valid_until, $on, $to),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SafetyInstructionParticipant>  $entries
     * @return list<array<string, mixed>>
     */
    private function instructionRows(Collection $entries, Carbon $on, Carbon $to): array {
        $rows = [];
        foreach ($entries as $entry) {
            $heldOn = $entry->instruction->held_on ?? null;

            // Was erst nach dem Zeitraum stattfindet, deckt ihn nicht —
            // eine spätere Unterweisung heilt keinen früheren Zeitpunkt.
            // Innerhalb des Zeitraums gehaltene zählen als Teildeckung.
            if ($heldOn !== null && $heldOn->greaterThan($to)) {
                continue;
            }

            $rows[] = [
                'topic' => (string) ($entry->instruction->topic ?? ''),
                'held_on' => $heldOn?->toDateString(),
                'signed_at' => $entry->signed_at?->toDateString(),
                'next_due_on' => $entry->next_due_on?->toDateString(),
                'valid_on' => $this->coverage($heldOn, $entry->next_due_on, $on, $to) === self::COVERAGE_FULL,
                'coverage' => $this->coverage($heldOn, $entry->next_due_on, $on, $to),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, LearningCertificate>  $entries
     * @return list<array<string, mixed>>
     */
    private function certificateRows(Collection $entries, Carbon $on, Carbon $to): array {
        $rows = [];
        foreach ($entries as $entry) {
            // `issued_on` ist Pflicht — ein Zertifikat ohne Ausstellungsdatum
            // gibt es nicht.
            if ($entry->issued_on->greaterThan($to)) {
                continue;
            }

            // Über getAttribute: der Datums-Cast lässt den Typ sonst als
            // nicht-nullbar erscheinen, obwohl die Spalte nullable ist.
            $revoked = $entry->getAttribute('revoked_at');
            $revokedOn = $revoked instanceof Carbon && $revoked->lessThanOrEqualTo($on);
            // Ein Widerruf IM Zeitraum kappt die Gueltigkeit ab seinem Tag.
            $effectiveUntil = $revoked instanceof Carbon && ! $revokedOn
                ? ($entry->valid_until === null || $revoked->lessThan($entry->valid_until) ? $revoked : $entry->valid_until)
                : $entry->valid_until;

            $rows[] = [
                'course' => (string) ($entry->course->title ?? ''),
                'number' => $entry->number,
                'issued_on' => $entry->issued_on->toDateString(),
                'valid_until' => $entry->valid_until?->toDateString(),
                // Ein Widerruf wirkt ab seinem Zeitpunkt, nicht rückwirkend.
                'revoked' => $revokedOn,
                'valid_on' => ! $revokedOn && $this->coverage($entry->issued_on, $effectiveUntil, $on, $to) === self::COVERAGE_FULL,
                'coverage' => $revokedOn
                    ? self::COVERAGE_NONE
                    : $this->coverage($entry->issued_on, $effectiveUntil, $on, $to),
            ];
        }

        return $rows;
    }

    /** Nachweis deckt jeden Tag des Zeitraums. */
    public const COVERAGE_FULL = 'full';

    /** Nachweis deckt einen Teil des Zeitraums — er beginnt spaeter oder laeuft vorher ab. */
    public const COVERAGE_PARTIAL = 'partial';

    /** Nachweis deckt keinen einzigen Tag des Zeitraums. */
    public const COVERAGE_NONE = 'none';

    /**
     * Deckung eines Gueltigkeitsintervalls ueber den betrachteten Zeitraum.
     *
     * Der Stichtag ist der entartete Fall `von == bis`: dort gibt es nur
     * `full` oder `none`, `partial` kann nicht auftreten. Deshalb ersetzt
     * dieser Begriff die fruehere Punktbetrachtung, statt neben ihr zu
     * stehen — zwei Fassungen derselben Regel laufen auseinander.
     *
     * `null` bei `$until` heisst unbefristet, `null` bei `$from` heisst „schon
     * immer".
     */
    public function coverage(?Carbon $from, ?Carbon $until, Carbon $rangeFrom, Carbon $rangeTo): string {
        $startsAfterRange = $from !== null && $from->greaterThan($rangeTo);
        $endsBeforeRange = $until !== null && $until->lessThan($rangeFrom);

        if ($startsAfterRange || $endsBeforeRange) {
            return self::COVERAGE_NONE;
        }

        $startsInTime = $from === null || $from->lessThanOrEqualTo($rangeFrom);
        $lastsThrough = $until === null || $until->greaterThanOrEqualTo($rangeTo);

        return $startsInTime && $lastsThrough ? self::COVERAGE_FULL : self::COVERAGE_PARTIAL;
    }

    /**
     * Ampel einer Personenzeile.
     *
     * Ein offenes Pflicht-Soll faerbt hoechstens gelb: Es sagt, dass etwas
     * fehlt — nicht, dass ein vorhandener Nachweis nicht traegt. Rot bleibt
     * dem Fall vorbehalten, dass ein Nachweis den Zeitraum gar nicht deckt.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    private function rowCoverage(array $entries, int $openObligations): string {
        $worst = $this->worstCoverage($entries);

        if ($worst === self::COVERAGE_FULL && $openObligations > 0) {
            return self::COVERAGE_PARTIAL;
        }

        return $worst;
    }

    /**
     * Schlechteste Deckung einer Menge von Nachweisen — die Ampel einer
     * Zelle: ein einziger ungedeckter Nachweis macht die Zelle rot.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    public function worstCoverage(array $entries): string {
        $worst = self::COVERAGE_FULL;
        foreach ($entries as $entry) {
            $value = (string) ($entry['coverage'] ?? self::COVERAGE_NONE);
            if ($value === self::COVERAGE_NONE) {
                return self::COVERAGE_NONE;
            }
            if ($value === self::COVERAGE_PARTIAL) {
                $worst = self::COVERAGE_PARTIAL;
            }
        }

        return $worst;
    }

    /**
     * Deckungsbild einer Gruppe **ohne Namen** — die Vorgabe-Ansicht der
     * Nachweismappe und zugleich die Einsatz-Ampel: grün, wenn niemand ein
     * offenes Pflicht-Soll oder einen abgelaufenen Nachweis hat.
     *
     * Bewusst ohne Personenliste: die Frage „können wir den Auftrag
     * besetzen" braucht keine Namen.
     *
     * @param  Collection<int, User>  $users
     * @return array{people: int, ready: int, partial: int, expired: int, open_obligations: int, earliest_expiry: string|null, tone: string}
     */
    public function coverageSummary(Collection $users, ?Carbon $on = null, ?Carbon $to = null): array {
        $on ??= Carbon::today();
        $to ??= $on;

        $ready = 0;
        $partial = 0;
        $expired = 0;
        $open = 0;
        $earliest = null;

        foreach ($this->forUsers($users, $on, $to) as $row) {
            foreach (array_merge($row['qualifications'], $row['certificates']) as $entry) {
                // Frühestes Ablaufdatum: die Zahl, nach der in Angeboten
                // gefragt wird („bis wann sind wir besetzbar?").
                $until = $entry['valid_until'] ?? null;
                if (is_string($until) && ($earliest === null || $until < $earliest)) {
                    $earliest = $until;
                }
            }

            $open += (int) $row['open_obligations'];

            match ($row['coverage']) {
                self::COVERAGE_FULL => $ready++,
                self::COVERAGE_PARTIAL => $partial++,
                default => $expired++,
            };
        }

        return [
            'people' => $users->count(),
            'ready' => $ready,
            'partial' => $partial,
            'expired' => $expired,
            'open_obligations' => $open,
            'earliest_expiry' => $earliest,
            // Die Ampel bewertet nicht die Person, sondern die Besetzbarkeit
            // über den GANZEN Zeitraum: teilweise gedeckt ist nicht besetzbar,
            // sondern „nur bis …".
            'tone' => match (true) {
                $expired > 0 => 'error',
                $partial > 0 || $open > 0 => 'warning',
                default => 'success',
            },
        ];
    }

    /**
     * Offene Kursabschlüsse einer Person — für die Einsatz-Ampel.
     */
    public function openEnrollments(User $user): int {
        return LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                LearningEnrollmentStatus::Assigned->value,
                LearningEnrollmentStatus::InProgress->value,
            ])
            ->count();
    }
}
