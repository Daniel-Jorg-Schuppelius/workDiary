<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssessmentSnapshotService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Isms;

use App\Models\Isms\{IsmsApplicabilityStatement, IsmsNormStatus, IsmsScope};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Stichtags-Rekonstruktion (Nachtrag 046b): schreibt bei jeder
 * Bewertungsänderung (SoA-Aussage, Norm-Konformitätsstatus) einen
 * append-only Snapshot und rekonstruiert daraus den Stand zu Datum T
 * (je Subjekt der letzte Snapshot ≤ T). Der Live-Prüferzugang bleibt
 * bewusst außen vor (Feature 033, blocked).
 */
class AssessmentSnapshotService {
    /** Von den Model-saved-Hooks aufgerufen (AppServiceProvider::boot). */
    public function record(Model $subject): void {
        $payload = match (true) {
            $subject instanceof IsmsApplicabilityStatement => [
                'applicable' => (bool) $subject->getAttribute('applicable'),
                'implementation_status' => $this->enumValue($subject->getAttribute('implementation_status')),
                'justification' => $subject->getAttribute('justification'),
                'requirement_id' => (int) $subject->getAttribute('isms_requirement_id'),
            ],
            $subject instanceof IsmsNormStatus => [
                'norm' => (string) $subject->getAttribute('norm'),
                'edition' => (string) $subject->getAttribute('edition'),
                'status' => $this->enumValue($subject->getAttribute('status')),
                'profile_version' => $subject->getAttribute('profile_version'),
            ],
            default => null,
        };
        if ($payload === null) {
            return;
        }

        DB::table('isms_assessment_snapshots')->insert([
            'organization_id' => (int) $subject->getAttribute('organization_id'),
            'isms_scope_id' => $subject->getAttribute('isms_scope_id'),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (int) $subject->getKey(),
            'payload' => json_encode($payload),
            'recorded_at' => now(),
        ]);
    }

    private function enumValue(mixed $value): string {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) ($value ?? '');
    }

    /**
     * Bewertungsstand zu Datum T: je Subjekt der letzte Snapshot ≤ T.
     *
     * @return array{as_of: string, statements: array{total: int, applicable: int}, norm_statuses: list<array{norm: string, edition: string, status: string}>}
     */
    public function stateAt(IsmsScope $scope, CarbonInterface $asOf): array {
        $rows = DB::table('isms_assessment_snapshots')
            ->where('organization_id', $scope->organization_id)
            ->where(function ($query) use ($scope): void {
                $query->where('isms_scope_id', $scope->id)->orWhereNull('isms_scope_id');
            })
            ->where('recorded_at', '<=', $asOf)
            ->orderBy('recorded_at')
            ->get();

        // Letzter Snapshot je Subjekt gewinnt (append-only, chronologisch).
        $latest = [];
        foreach ($rows as $row) {
            $latest[$row->subject_type . '#' . $row->subject_id] = $row;
        }

        $statements = ['total' => 0, 'applicable' => 0];
        $normStatuses = [];
        foreach ($latest as $row) {
            /** @var array<string, mixed> $payload */
            $payload = (array) json_decode((string) $row->payload, true);
            if (str_contains($row->subject_type, 'ApplicabilityStatement') || isset($payload['implementation_status'])) {
                $statements['total']++;
                if (($payload['applicable'] ?? false) === true) {
                    $statements['applicable']++;
                }
            } elseif (isset($payload['norm'])) {
                $normStatuses[] = [
                    'norm' => (string) $payload['norm'],
                    'edition' => (string) $payload['edition'],
                    'status' => (string) $payload['status'],
                ];
            }
        }

        return [
            'as_of' => $asOf->toDateString(),
            'statements' => $statements,
            'norm_statuses' => $normStatuses,
        ];
    }
}
