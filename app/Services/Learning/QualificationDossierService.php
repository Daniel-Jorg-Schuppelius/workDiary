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
     * Namentliche Mappe für eine Personengruppe zum Stichtag.
     *
     * @param  Collection<int, User>  $users
     * @return list<array{user: User, qualifications: list<array<string, mixed>>, instructions: list<array<string, mixed>>, certificates: list<array<string, mixed>>, open_obligations: int}>
     */
    public function forUsers(Collection $users, ?Carbon $on = null): array {
        $on ??= Carbon::today();
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
                'qualifications' => $this->qualificationRows($qualifications->get($user->id, collect()), $on),
                'instructions' => $this->instructionRows($instructions->get($user->id, collect()), $on),
                'certificates' => $this->certificateRows($certificates->get($user->id, collect()), $on),
                'open_obligations' => $obligations->get($user->id, collect())->count(),
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
            if (! $this->isValidOn($entry->valid_from, $entry->valid_until, $on)) {
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
    public function exportPayload(Organization $organization, Collection $users, ?Carbon $on = null): array {
        $on ??= Carbon::today();

        $people = [];
        foreach ($this->forUsers($users, $on) as $row) {
            $people[] = [
                'name' => $row['user']->name,
                'qualifications' => $row['qualifications'],
                'instructions' => $row['instructions'],
                'certificates' => $row['certificates'],
                'open_obligations' => $row['open_obligations'],
            ];
        }

        // Stabile Reihenfolge: sonst unterscheiden sich zwei Läufe im Hash,
        // obwohl sie dasselbe aussagen.
        usort($people, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        $payload = [
            'format' => 'workdiary.learning.dossier',
            'format_version' => 1,
            'organization' => $organization->name,
            'as_of' => $on->toDateString(),
            'people' => $people,
        ];

        $payload['hash'] = CryptoHelper::hash(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return $payload;
    }

    /**
     * @param  Collection<int, UserQualification>  $entries
     * @return list<array<string, mixed>>
     */
    private function qualificationRows(Collection $entries, Carbon $on): array {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'name' => (string) ($entry->qualification->name ?? ''),
                'valid_from' => $entry->valid_from?->toDateString(),
                'valid_until' => $entry->valid_until?->toDateString(),
                'valid_on' => $this->isValidOn($entry->valid_from, $entry->valid_until, $on),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SafetyInstructionParticipant>  $entries
     * @return list<array<string, mixed>>
     */
    private function instructionRows(Collection $entries, Carbon $on): array {
        $rows = [];
        foreach ($entries as $entry) {
            $heldOn = $entry->instruction->held_on ?? null;

            // Zum Stichtag zählt nur, was bis dahin stattgefunden hat —
            // eine spätere Unterweisung heilt keinen früheren Zeitpunkt.
            if ($heldOn !== null && $heldOn->greaterThan($on)) {
                continue;
            }

            $rows[] = [
                'topic' => (string) ($entry->instruction->topic ?? ''),
                'held_on' => $heldOn?->toDateString(),
                'signed_at' => $entry->signed_at?->toDateString(),
                'next_due_on' => $entry->next_due_on?->toDateString(),
                'valid_on' => $entry->next_due_on === null || $entry->next_due_on->greaterThanOrEqualTo($on),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, LearningCertificate>  $entries
     * @return list<array<string, mixed>>
     */
    private function certificateRows(Collection $entries, Carbon $on): array {
        $rows = [];
        foreach ($entries as $entry) {
            // `issued_on` ist Pflicht — ein Zertifikat ohne Ausstellungsdatum
            // gibt es nicht.
            if ($entry->issued_on->greaterThan($on)) {
                continue;
            }

            // Über getAttribute: der Datums-Cast lässt den Typ sonst als
            // nicht-nullbar erscheinen, obwohl die Spalte nullable ist.
            $revoked = $entry->getAttribute('revoked_at');
            $revokedOn = $revoked instanceof Carbon && $revoked->lessThanOrEqualTo($on);

            $rows[] = [
                'course' => (string) ($entry->course->title ?? ''),
                'number' => $entry->number,
                'issued_on' => $entry->issued_on->toDateString(),
                'valid_until' => $entry->valid_until?->toDateString(),
                // Ein Widerruf wirkt ab seinem Zeitpunkt, nicht rückwirkend.
                'revoked' => $revokedOn,
                'valid_on' => $this->isValidOn($entry->issued_on, $entry->valid_until, $on) && ! $revokedOn,
            ];
        }

        return $rows;
    }

    private function isValidOn(?Carbon $from, ?Carbon $until, Carbon $on): bool {
        if ($from !== null && $from->greaterThan($on)) {
            return false;
        }

        return $until === null || $until->greaterThanOrEqualTo($on);
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
     * @return array{people: int, ready: int, expired: int, open_obligations: int, earliest_expiry: string|null, tone: string}
     */
    public function coverageSummary(Collection $users, ?Carbon $on = null): array {
        $on ??= Carbon::today();

        $ready = 0;
        $expired = 0;
        $open = 0;
        $earliest = null;

        foreach ($this->forUsers($users, $on) as $row) {
            $rowExpired = false;

            foreach (array_merge($row['qualifications'], $row['certificates']) as $entry) {
                if (($entry['valid_on'] ?? false) !== true) {
                    $rowExpired = true;

                    continue;
                }

                $until = $entry['valid_until'] ?? null;

                if (is_string($until) && ($earliest === null || $until < $earliest)) {
                    $earliest = $until;
                }
            }

            $obligations = (int) $row['open_obligations'];
            $open += $obligations;

            if ($rowExpired) {
                $expired++;
            }

            if (! $rowExpired && $obligations === 0) {
                $ready++;
            }
        }

        return [
            'people' => $users->count(),
            'ready' => $ready,
            'expired' => $expired,
            'open_obligations' => $open,
            'earliest_expiry' => $earliest,
            // Die Ampel bewertet nicht die Person, sondern die Besetzbarkeit.
            'tone' => match (true) {
                $expired > 0 => 'error',
                $open > 0 => 'warning',
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
