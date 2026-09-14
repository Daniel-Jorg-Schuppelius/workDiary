<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5Runtime.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningCmi5AuState, LearningCmi5Package, LearningCmi5Registration, LearningCmi5Session, LearningCmi5Unit, LearningEnrollment, LearningXapiStatement};
use App\Models\User;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use ELearningToolkit\Cmi5\{Actor, AssignableUnit, LaunchMode, LmsStatements, MoveOn, SatisfiedScope, Session};
use ELearningToolkit\XApi\Verbs;
use Illuminate\Support\{Carbon, Str};

/**
 * Gemeinsame Bausteine von Start und LRS (Feature 149): Actor, Sitzungszustand,
 * Statement-Ablage und die Erfüllung von Block und Kurs.
 */
final class LearningCmi5Runtime {
    /** Pfad des LRS unter der Anwendungs-URL. */
    public const ENDPOINT_PATH = 'lrs/cmi5';

    public function __construct(private readonly LearningEnrollmentService $enrollments) {}

    /** Basisadresse für die Start-URL; xAPI erwartet den Schrägstrich am Ende. */
    public static function endpoint(): string {
        return rtrim(url(self::ENDPOINT_PATH), '/') . '/';
    }

    /**
     * Actor des Lernenden (cmi5 9.2). Die Sqid statt Name oder Mail: Externe AUs
     * bekommen den Actor in der Start-URL zu sehen.
     *
     * @return array<string, mixed>
     */
    public static function actorFor(User $learner): array {
        return Actor::forAccount(self::homePage(), $learner->sqid);
    }

    /**
     * Actor zur Sitzung — immer über Registrierung und Einschreibung, nie aus der Anfrage.
     *
     * @return array<string, mixed>|null
     */
    public static function actorForSession(LearningCmi5Session $session): ?array {
        $learner = $session->registration?->enrollment?->user;

        return $learner instanceof User ? self::actorFor($learner) : null;
    }

    /**
     * Abdruck eines Agents über sein Konto; andere Kennungen vergibt das LMS nicht.
     *
     * @param  array<mixed>  $agent
     */
    public static function agentHash(array $agent): ?string {
        $account = $agent['account'] ?? null;

        if (! is_array($account) || ! is_string($account['homePage'] ?? null) || ! is_string($account['name'] ?? null)) {
            return null;
        }

        return CryptoHelper::hash($account['homePage'] . "\n" . $account['name']);
    }

    public static function languageTag(): string {
        return str_replace('_', '-', app()->getLocale());
    }

    /** AU aus der Datenbank als Toolkit-Objekt — die Kursstruktur selbst liegt nicht mehr vor. */
    public function toolkitUnit(LearningCmi5Unit $au): AssignableUnit {
        return new AssignableUnit(
            $au->publisher_id,
            [self::languageTag() => $au->title],
            [],
            $au->url,
            $au->moveOn(),
            $au->masteryScore(),
            $au->launchMethod(),
            $au->launch_parameters,
            $au->entitlement_key,
            null,
            [],
        );
    }

    public function session(LearningCmi5Session $session, LearningCmi5Unit $au, LearningCmi5Registration $registration, ?LearningCmi5AuState $state): Session {
        return new Session(
            registration: $registration->registration,
            activityId: $au->activity_id,
            sessionId: $session->session_id,
            publisherId: $au->publisher_id,
            launchMode: LaunchMode::tryFrom($session->launch_mode) ?? LaunchMode::Normal,
            moveOn: $au->moveOn(),
            masteryScore: $au->masteryScore(),
            completed: $state?->completed_at !== null,
            passed: $state?->passed_at !== null,
            waived: $state?->waived_at !== null,
            initialized: $session->initialized_at !== null,
            terminated: $session->terminated_at !== null,
            abandoned: $session->abandoned_at !== null,
        );
    }

    /**
     * Statement ablegen — mit `stored` und `authority`, wie ein LRS es setzt.
     *
     * @param  array<mixed>  $statement
     */
    public function record(LearningEnrollment $enrollment, array $statement, CarbonImmutable $at): LearningXapiStatement {
        $statement['stored'] = $at->utc()->format('Y-m-d\TH:i:s.v\Z');
        $statement['authority'] = ['objectType' => 'Agent', 'account' => ['homePage' => self::homePage(), 'name' => 'lms']];
        $statement['version'] ??= '1.0.0';
        $verb = is_array($statement['verb'] ?? null) ? $statement['verb'] : [];
        $object = is_array($statement['object'] ?? null) ? $statement['object'] : [];

        return LearningXapiStatement::query()->create([
            'organization_id' => $enrollment->organization_id,
            'learning_enrollment_id' => $enrollment->id,
            'statement_id' => is_string($statement['id'] ?? null) ? strtolower($statement['id']) : null,
            'verb' => is_string($verb['id'] ?? null) ? $verb['id'] : null,
            'object_id' => is_string($object['id'] ?? null) ? mb_substr($object['id'], 0, 500) : null,
            'payload' => json_encode($statement, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'stored_at' => $at,
        ]);
    }

    /**
     * Block und Kurs erfüllt? Schreibt die satisfied-Statements (cmi5 9.3.9) je
     * einmal und schließt die Lerneinheit, sobald der Kurs erfüllt ist.
     */
    public function evaluate(LearningCmi5Registration $registration, LearningCmi5Session $session, CarbonImmutable $at): void {
        $package = LearningCmi5Package::query()->find($registration->learning_cmi5_package_id);
        $enrollment = LearningEnrollment::query()->find($registration->learning_enrollment_id);
        $learner = $enrollment?->user;

        if ($package === null || $enrollment === null || $learner === null) {
            return;
        }

        $units = $package->units()->get();
        $states = LearningCmi5AuState::query()
            ->where('learning_cmi5_registration_id', $registration->id)
            ->get()
            ->keyBy('learning_cmi5_unit_id');

        /** @var array<string, true> $satisfied */
        $satisfied = [];

        foreach ($units as $au) {
            /** @var LearningCmi5AuState|null $state */
            $state = $states->get($au->id);
            // Ein Erlass zählt wie erfüllt; NotApplicable ist es ohne jedes Zutun.
            $done = $au->moveOn() === MoveOn::NotApplicable
                || ($state !== null && ($state->waived_at !== null || $au->moveOn()->isSatisfiedBy($state->completed_at !== null, $state->passed_at !== null)));

            if (! $done) {
                continue;
            }

            $satisfied[$au->publisher_id] = true;

            if ($state !== null && $state->satisfied_at === null) {
                $state->satisfied_at = Carbon::instance($at);
                $state->save();
            }
        }

        $actor = self::actorFor($learner);

        foreach ($package->blocks ?? [] as $block) {
            if ($block['units'] !== [] && array_diff($block['units'], array_keys($satisfied)) === []) {
                $this->satisfy($enrollment, $actor, $registration, $session, $block['activity_id'], $block['publisher_id'], SatisfiedScope::Block, $at);
            }
        }

        if ($units->isEmpty() || count($satisfied) !== $units->count() || $registration->satisfied_at !== null) {
            return;
        }

        $this->satisfy($enrollment, $actor, $registration, $session, $package->activity_id, $package->course_id, SatisfiedScope::Course, $at);
        $registration->satisfied_at = Carbon::instance($at);
        $registration->save();

        $unit = $package->unit;

        if ($unit !== null && ! $enrollment->status->isFinal()) {
            $this->enrollments->completeUnit($enrollment, $unit);
        }
    }

    /** @param  array<string, mixed>  $actor */
    private function satisfy(LearningEnrollment $enrollment, array $actor, LearningCmi5Registration $registration, LearningCmi5Session $session, string $lmsObjectId, string $publisherId, SatisfiedScope $scope, CarbonImmutable $at): void {
        $recorded = LearningXapiStatement::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('verb', Verbs::SATISFIED)
            ->where('object_id', $lmsObjectId)
            ->exists();

        if ($recorded) {
            return;
        }

        $this->record($enrollment, LmsStatements::satisfied(Str::uuid()->toString(), $actor, $registration->registration, $session->session_id, $lmsObjectId, $publisherId, $scope, $at), $at);
    }

    private static function homePage(): string {
        return rtrim((string) config('app.url'), '/');
    }
}
