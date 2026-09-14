<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5LaunchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningCmi5AuState, LearningCmi5Registration, LearningCmi5Session, LearningCmi5Unit, LearningEnrollment, LearningXapiDocument};
use App\Models\User;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use ELearningToolkit\Cmi5\{Cmi5, Cmi5Exception, LaunchData, LaunchMode, LaunchUrl, LmsStatements};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;

/**
 * Start einer AU (cmi5 8.1, 9.6.3): Registrierung, offene Sitzungen abbrechen,
 * neue Sitzung, LMS.LaunchData, launched-Statement, Start-URL.
 */
final class LearningCmi5LaunchService {
    public function __construct(
        private readonly LearningCmi5Runtime $runtime,
        private readonly LearningCmi5DocumentStore $documents,
    ) {}

    /**
     * @param  string  $auUrl  Voll qualifizierte Adresse der AU ohne Startparameter
     * @return string Start-URL mit den fünf cmi5-Parametern
     *
     * @throws Cmi5Exception wenn die AU-Adresse einen reservierten Parameter schon nutzt
     */
    public function launch(LearningEnrollment $enrollment, User $learner, LearningCmi5Unit $au, string $auUrl, ?string $returnUrl = null): string {
        $now = CarbonImmutable::now();

        return DB::transaction(function () use ($enrollment, $learner, $au, $auUrl, $returnUrl, $now): string {
            $registration = LearningCmi5Registration::query()->firstOrCreate(
                ['learning_enrollment_id' => $enrollment->id, 'learning_cmi5_package_id' => $au->learning_cmi5_package_id],
                ['organization_id' => $enrollment->organization_id, 'registration' => Str::uuid()->toString()],
            );
            $actor = LearningCmi5Runtime::actorFor($learner);
            $agentHash = (string) LearningCmi5Runtime::agentHash($actor);
            $state = LearningCmi5AuState::query()
                ->where('learning_cmi5_registration_id', $registration->id)
                ->where('learning_cmi5_unit_id', $au->id)
                ->first();

            $this->abandonOpenSessions($enrollment, $registration, $au, $state, $actor, $now);

            // Der Fetch-Token steht nur in der Start-URL; gespeichert wird sein Abdruck.
            $fetchToken = Str::random(64);
            $session = LearningCmi5Session::query()->create([
                'organization_id' => $enrollment->organization_id,
                'learning_cmi5_registration_id' => $registration->id,
                'learning_cmi5_unit_id' => $au->id,
                'session_id' => Str::uuid()->toString(),
                'launch_mode' => LaunchMode::Normal->value,
                'fetch_token_hash' => CryptoHelper::hash($fetchToken),
                'launched_at' => $now,
                'expires_at' => $now->addSeconds((int) config('learning.cmi5.session_ttl', 28800)),
            ]);

            $launchUrl = LaunchUrl::build(
                $auUrl,
                LearningCmi5Runtime::endpoint(),
                route('learning.cmi5.lrs.fetch', ['token' => $fetchToken]),
                $actor,
                $registration->registration,
                $au->activity_id,
            );

            $toolkitUnit = $this->runtime->toolkitUnit($au);
            $toolkitSession = $this->runtime->session($session, $au, $registration, $state);
            $launchData = json_encode(LaunchData::document($toolkitUnit, $toolkitSession, $returnUrl), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->documents->put($enrollment->organization_id, LearningXapiDocument::KIND_STATE, $au->activity_id, $agentHash, $registration->registration, Cmi5::STATE_LAUNCH_DATA, $launchData, 'application/json');
            $this->documents->ensureLearnerPreferences($enrollment->organization_id, $agentHash, LearningCmi5Runtime::languageTag());
            $this->runtime->record($enrollment, LmsStatements::launched(Str::uuid()->toString(), $actor, $toolkitSession, $toolkitUnit, $auUrl, $now), $now);

            // AUs mit moveOn NotApplicable sind schon mit dem Start erfüllt.
            $this->runtime->evaluate($registration, $session, $now);

            return $launchUrl;
        });
    }

    /**
     * Eine neue Sitzung beendet jede offene derselben AU (cmi5 9.3.8).
     *
     * @param  array<string, mixed>  $actor
     */
    private function abandonOpenSessions(LearningEnrollment $enrollment, LearningCmi5Registration $registration, LearningCmi5Unit $au, ?LearningCmi5AuState $state, array $actor, CarbonImmutable $now): void {
        $open = LearningCmi5Session::query()
            ->where('learning_cmi5_registration_id', $registration->id)
            ->where('learning_cmi5_unit_id', $au->id)
            ->whereNull('terminated_at')
            ->whereNull('abandoned_at')
            ->lockForUpdate()
            ->get();

        foreach ($open as $session) {
            $toolkitSession = $this->runtime->session($session, $au, $registration, $state);
            $session->abandoned_at = Carbon::instance($now);
            $session->save();

            // Ohne initialized hat die AU die Sitzung nie aufgenommen — es gibt nichts abzubrechen.
            if ($session->initialized_at === null) {
                continue;
            }

            $end = $session->last_statement_at ?? $session->initialized_at;
            $seconds = (int) round(abs($end->diffInSeconds($session->launched_at)));

            $this->runtime->record($enrollment, LmsStatements::abandoned(Str::uuid()->toString(), $actor, $toolkitSession, 'PT' . $seconds . 'S', $now), $now);
        }
    }
}
