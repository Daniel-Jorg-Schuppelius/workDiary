<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5RecordStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningCmi5AuState, LearningCmi5Registration, LearningCmi5Session, LearningCmi5Unit, LearningEnrollment, LearningXapiStatement};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use ELearningToolkit\Cmi5\{FetchError, FetchResponse, StatementRules};
use ELearningToolkit\XApi\{Statement, StatementException, StatementValidator, Verbs};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;

/**
 * Fetch-URL und Statements des cmi5-LRS.
 *
 * Ein Statement kommt nur durch, wenn es gültiges xAPI ist, vom Actor der Sitzung
 * stammt und die cmi5-Regeln der Sitzung einhält. Erst dann ändert es den Zustand.
 */
final class LearningCmi5RecordStore {
    public function __construct(
        private readonly LearningCmi5Runtime $runtime,
        private readonly StatementValidator $validator,
    ) {}

    /**
     * Fetch-URL einlösen (cmi5 8.2.3): genau einmal je Sitzung.
     *
     * @return array<string, string>
     */
    public function fetch(string $token): array {
        $session = LearningCmi5Session::query()->withoutGlobalScopes()
            ->where('fetch_token_hash', CryptoHelper::hash($token))
            ->first();

        if ($session === null) {
            return FetchResponse::error(FetchError::SecurityError, 'Unknown fetch URL.');
        }

        if ($session->hasEnded() || $session->expires_at->isPast()) {
            return FetchResponse::error(FetchError::AlreadyInUseOrExpired, 'The session has ended or expired.');
        }

        $authToken = Str::random(48);
        // Bedingtes Update: Zwei gleichzeitige Abrufe bekommen nie beide einen Token.
        $claimed = LearningCmi5Session::query()->withoutGlobalScopes()
            ->whereKey($session->id)
            ->whereNull('fetch_used_at')
            ->update(['fetch_used_at' => now(), 'auth_token_hash' => CryptoHelper::hash($authToken)]);

        if ($claimed !== 1) {
            return FetchResponse::error(FetchError::AlreadyInUseOrExpired, 'The fetch URL has already been used.');
        }

        return FetchResponse::token($authToken);
    }

    /**
     * Statements einer Sitzung annehmen — ganz oder gar nicht (xAPI 1.0.3).
     *
     * @param  list<array<mixed>>  $statements
     * @return list<string> Statement-IDs in Eingangsreihenfolge
     *
     * @throws Cmi5LrsRejection
     */
    public function store(LearningCmi5Session $authenticated, array $statements): array {
        return DB::transaction(function () use ($authenticated, $statements): array {
            // Statements einer Sitzung ändern ihren Zustand der Reihe nach.
            $session = LearningCmi5Session::query()->whereKey($authenticated->id)->lockForUpdate()->firstOrFail();
            $registration = LearningCmi5Registration::query()->findOrFail($session->learning_cmi5_registration_id);
            $au = LearningCmi5Unit::query()->findOrFail($session->learning_cmi5_unit_id);
            $enrollment = LearningEnrollment::query()->findOrFail($registration->learning_enrollment_id);
            $actor = LearningCmi5Runtime::actorForSession($session) ?? throw new Cmi5LrsRejection(403, 'learner_missing');
            $actorHash = LearningCmi5Runtime::agentHash($actor);
            $state = LearningCmi5AuState::query()->firstOrNew(
                ['learning_cmi5_registration_id' => $registration->id, 'learning_cmi5_unit_id' => $au->id],
                ['organization_id' => $session->organization_id],
            );
            $now = CarbonImmutable::now();
            $stamp = Carbon::instance($now);
            $ids = [];

            foreach ($statements as $raw) {
                $raw['id'] = is_string($raw['id'] ?? null) ? strtolower($raw['id']) : Str::uuid()->toString();

                try {
                    $statement = Statement::fromArray($raw, $this->validator);
                } catch (StatementException $e) {
                    throw new Cmi5LrsRejection(400, $e->reason);
                }

                $agent = is_array($raw['actor'] ?? null) ? $raw['actor'] : [];

                if (LearningCmi5Runtime::agentHash($agent) !== $actorHash) {
                    throw new Cmi5LrsRejection(403, 'actor_mismatch');
                }

                $ids[] = $raw['id'];

                // Doppelte Zustellung ist bei xAPI normal: gleiche ID, gleicher Inhalt → nichts zu tun.
                if ($this->isDuplicate($raw)) {
                    continue;
                }

                $before = $this->runtime->session($session, $au, $registration, $state->exists ? $state : null);
                $violations = StatementRules::check($statement, $before);

                if ($violations !== []) {
                    throw new Cmi5LrsRejection(403, $violations[0]->reason);
                }

                $after = StatementRules::apply($statement, $before);

                if ($after->initialized && $session->initialized_at === null) {
                    $session->initialized_at = $stamp;
                }

                if ($after->terminated && $session->terminated_at === null) {
                    $session->terminated_at = $stamp;
                }

                $session->last_statement_at = $stamp;
                $session->save();

                if ($after->completed && $state->completed_at === null) {
                    $state->completed_at = $stamp;
                }

                if ($after->passed && $state->passed_at === null) {
                    $state->passed_at = $stamp;
                }

                if ($statement->verbId === Verbs::FAILED && $state->failed_at === null) {
                    $state->failed_at = $stamp;
                }

                if ($state->isDirty(['completed_at', 'passed_at', 'failed_at'])) {
                    $state->save();
                }

                $this->runtime->record($enrollment, $raw, $now);
            }

            $this->runtime->evaluate($registration, $session, $now);

            return $ids;
        });
    }

    /** @return array<mixed>|null */
    public function find(LearningCmi5Session $session, string $statementId): ?array {
        $record = LearningXapiStatement::query()
            ->where('learning_enrollment_id', $session->registration?->learning_enrollment_id)
            ->where('statement_id', strtolower($statementId))
            ->first();

        return $record?->statement();
    }

    /** @return list<array<mixed>> neueste zuerst, nur die der eigenen Einschreibung */
    public function recent(LearningCmi5Session $session, int $limit): array {
        $statements = [];
        $records = LearningXapiStatement::query()
            ->where('learning_enrollment_id', $session->registration?->learning_enrollment_id)
            ->orderByDesc('stored_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($records as $record) {
            $statements[] = $record->statement();
        }

        return $statements;
    }

    /**
     * @param  array<mixed>  $raw
     *
     * @throws Cmi5LrsRejection bei gleicher ID mit anderem Inhalt
     */
    private function isDuplicate(array $raw): bool {
        $existing = LearningXapiStatement::query()->where('statement_id', $raw['id'])->first();

        if ($existing === null) {
            return false;
        }

        $strip = static fn (array $statement): array => array_diff_key($statement, array_flip(['stored', 'authority', 'version']));

        if ($strip($existing->statement()) != $strip($raw)) {
            throw new Cmi5LrsRejection(409, 'statement_conflict');
        }

        return true;
    }
}
