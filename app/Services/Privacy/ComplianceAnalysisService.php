<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceAnalysisService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Organization;
use App\Models\Privacy\{ComplianceFinding, Dpia, JointControllerAgreement, MeasureAssignment, PrivacyAttachment, PrivacyRequirement, ProcessingActivity, ProcessingAgreement, Processor, TechnicalMeasure};
use Illuminate\Support\Carbon;

/**
 * Ermittelt Compliance-/Vertragsluecken regelbasiert aus den echten Daten der
 * Organisation (Verarbeitungstaetigkeiten, Dienstleister, Vertraege, DSFA, TOM).
 * Manuell gesetzte Stati (nicht anwendbar, Abweichung akzeptiert, vorhanden,
 * in Pruefung) werden respektiert; nur auto-erkannte Befunde aktualisiert die Analyse.
 */
class ComplianceAnalysisService {
    /** @return int Anzahl aktuell erkannter Luecken */
    public function run(Organization $organization): int {
        $now = Carbon::now();
        $warnDays = (int) config('dataprotection.expiry_warning_days', 30);
        $orgId = $organization->id;

        /** @var list<array<string, mixed>> $gaps */
        $gaps = [];
        foreach ($this->catalog($organization) as $requirement) {
            if (! $requirement->active) {
                continue;
            }
            foreach ($this->detectGaps($requirement->check_type, (int) $orgId, $now, $warnDays) as $gap) {
                $gap['key'] = $requirement->requirement_key;
                $gap['label'] = $requirement->label;
                $gap['category'] = $requirement->category;
                $gaps[] = $gap;
            }
        }

        $seenIds = [];
        foreach ($gaps as $g) {
            $finding = ComplianceFinding::query()->firstOrNew([
                'organization_id' => $orgId,
                'requirement_key' => $g['key'],
                'activity_id' => $g['activity_id'] ?? null,
                'agreement_id' => $g['agreement_id'] ?? null,
                'processor_id' => $g['processor_id'] ?? null,
            ]);
            $isAuto = ! $finding->exists || (bool) $finding->auto_detected;
            $finding->fill([
                'label' => $g['label'],
                'category' => $g['category'],
                'trigger' => $g['trigger'],
                'detected_at' => $now,
            ]);
            if ($isAuto) {
                $finding->status = $g['status'];
                $finding->auto_detected = true;
            }
            $finding->save();
            $seenIds[] = $finding->id;
        }

        // Auto-erkannte Luecken, die nicht mehr auftreten → als behoben markieren.
        ComplianceFinding::query()
            ->where('organization_id', $orgId)
            ->where('auto_detected', true)
            ->whereIn('status', ['missing', 'expiring'])
            ->whereNotIn('id', $seenIds === [] ? [0] : $seenIds)
            ->update(['status' => 'present', 'trigger' => null, 'detected_at' => $now]);

        return count($gaps);
    }

    /**
     * Konfigurierbarer Anforderungskatalog (Nachtrag 043c): liest die
     * org-eigenen Katalogeinträge; beim ersten Lauf werden die
     * config-Defaults materialisiert (source=default), damit Admins sie
     * anschließend deaktivieren/umbenennen können. Branchenprofile können
     * weitere Vorlagen liefern (source=profile, BranchProfileInstaller).
     *
     * @return \Illuminate\Support\Collection<int, PrivacyRequirement>
     */
    public function catalog(Organization $organization): \Illuminate\Support\Collection {
        $existing = PrivacyRequirement::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        /** @var array<string, array{label?: string, category?: ?string}> $defaults */
        $defaults = (array) config('dataprotection.compliance.requirements', []);
        $missing = array_diff_key($defaults, $existing->keyBy('requirement_key')->all());
        foreach ($missing as $key => $def) {
            $existing->push(PrivacyRequirement::query()->create([
                'organization_id' => $organization->id,
                'requirement_key' => $key,
                'label' => (string) ($def['label'] ?? $key),
                'category' => $def['category'] ?? null,
                'check_type' => $key,
                'active' => true,
                'source' => 'default',
            ]));
        }

        return $existing->values();
    }

    /**
     * Prüf-Implementierungen je check_type. Liefert Lücken als Arrays mit
     * status/trigger und optionalem Bezug (activity_id/agreement_id/processor_id).
     *
     * @return list<array<string, mixed>>
     */
    private function detectGaps(string $checkType, int $orgId, Carbon $now, int $warnDays): array {
        $gaps = [];

        switch ($checkType) {
            case 'avv_required': // Auftragsverarbeiter ohne AVV
                foreach (Processor::query()->where('organization_id', $orgId)->where('role', 'processor')->get() as $p) {
                    if (! ProcessingAgreement::query()->where('processor_id', $p->id)->exists()) {
                        $gaps[] = ['status' => 'missing', 'processor_id' => $p->id,
                            'trigger' => "Auftragsverarbeiter „{$p->name}“ ohne AVV"];
                    }
                }
                break;
            case 'avv_current': // Ablaufende/abgelaufene AVV
                foreach (ProcessingAgreement::query()->where('organization_id', $orgId)
                    ->whereNotNull('valid_until')->whereDate('valid_until', '<=', $now->copy()->addDays($warnDays))->get() as $a) {
                    $gaps[] = ['status' => 'expiring', 'agreement_id' => $a->id,
                        'trigger' => "AVV „{$a->title}“ läuft ab oder ist abgelaufen"];
                }
                break;
            case 'gvv_required': // Gemeinsam Verantwortliche ohne GVV
                foreach (Processor::query()->where('organization_id', $orgId)->where('role', 'joint_controller')->get() as $p) {
                    if (! JointControllerAgreement::query()->where('partner_id', $p->id)->exists()) {
                        $gaps[] = ['status' => 'missing', 'processor_id' => $p->id,
                            'trigger' => "Gemeinsam Verantwortlicher „{$p->name}“ ohne GVV"];
                    }
                }
                break;
            case 'dpia_required': // DSFA-Bedarf ohne abgeschlossene DSFA
                foreach (ProcessingActivity::query()->where('organization_id', $orgId)->where('dsfa_required', true)->get() as $act) {
                    $dpia = Dpia::query()->where('activity_id', $act->id)->first();
                    if ($dpia === null || $dpia->outcome->value === 'open') {
                        $gaps[] = ['status' => 'missing', 'activity_id' => $act->id,
                            'trigger' => "„{$act->name}“ mit DSFA-Bedarf ohne abgeschlossene DSFA"];
                    }
                }
                break;
            case 'tom_assigned': // Verarbeitungstaetigkeit ohne zugeordnete TOM
                foreach (ProcessingActivity::query()->where('organization_id', $orgId)->get() as $act) {
                    if (! MeasureAssignment::query()->where('activity_id', $act->id)->exists()) {
                        $gaps[] = ['status' => 'missing', 'activity_id' => $act->id,
                            'trigger' => "„{$act->name}“ ohne zugeordnete TOM"];
                    }
                }
                break;
            case 'tom_proof_current': // TOM-Nachweise mit abgelaufenem Gültig-bis (043b)
                $expiring = PrivacyAttachment::query()
                    ->where('organization_id', $orgId)
                    ->where('attachable_type', TechnicalMeasure::class)
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<=', $now->copy()->addDays($warnDays))
                    ->get();
                if ($expiring->isNotEmpty()) {
                    $names = $expiring->map(fn(PrivacyAttachment $a): string => $a->filename)->implode(', ');
                    $gaps[] = ['status' => 'expiring',
                        'trigger' => 'TOM-Nachweise laufen ab oder sind abgelaufen: ' . $names];
                }
                break;
        }

        return $gaps;
    }

    /** Manuelle Statusentscheidung (z. B. „nicht anwendbar"/„Abweichung akzeptiert"). */
    public function override(ComplianceFinding $finding, string $status, ?string $justification, ?Carbon $dueAt = null): ComplianceFinding {
        $finding->forceFill([
            'status' => $status,
            'justification' => $justification,
            'due_at' => $dueAt?->toDateString(),
            'auto_detected' => false,
        ])->save();

        return $finding;
    }
}
