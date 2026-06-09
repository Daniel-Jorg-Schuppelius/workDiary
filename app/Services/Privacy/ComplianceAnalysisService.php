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
use App\Models\Privacy\{ComplianceFinding, Dpia, JointControllerAgreement, MeasureAssignment, ProcessingActivity, ProcessingAgreement, Processor};
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
        $defs = (array) config('dataprotection.compliance.requirements', []);
        $warnDays = (int) config('dataprotection.expiry_warning_days', 30);
        $orgId = $organization->id;

        /** @var list<array<string, mixed>> $gaps */
        $gaps = [];

        // 1) Auftragsverarbeiter ohne AVV
        foreach (Processor::query()->where('organization_id', $orgId)->where('role', 'processor')->get() as $p) {
            if (! ProcessingAgreement::query()->where('processor_id', $p->id)->exists()) {
                $gaps[] = ['key' => 'avv_required', 'status' => 'missing', 'processor_id' => $p->id,
                    'trigger' => "Auftragsverarbeiter „{$p->name}“ ohne AVV"];
            }
        }
        // 2) Ablaufende/abgelaufene AVV
        foreach (ProcessingAgreement::query()->where('organization_id', $orgId)
            ->whereNotNull('valid_until')->whereDate('valid_until', '<=', $now->copy()->addDays($warnDays))->get() as $a) {
            $gaps[] = ['key' => 'avv_current', 'status' => 'expiring', 'agreement_id' => $a->id,
                'trigger' => "AVV „{$a->title}“ läuft ab oder ist abgelaufen"];
        }
        // 3) Gemeinsam Verantwortliche ohne GVV
        foreach (Processor::query()->where('organization_id', $orgId)->where('role', 'joint_controller')->get() as $p) {
            if (! JointControllerAgreement::query()->where('partner_id', $p->id)->exists()) {
                $gaps[] = ['key' => 'gvv_required', 'status' => 'missing', 'processor_id' => $p->id,
                    'trigger' => "Gemeinsam Verantwortlicher „{$p->name}“ ohne GVV"];
            }
        }
        // 4) DSFA-Bedarf ohne abgeschlossene DSFA
        foreach (ProcessingActivity::query()->where('organization_id', $orgId)->where('dsfa_required', true)->get() as $act) {
            $dpia = Dpia::query()->where('activity_id', $act->id)->first();
            if ($dpia === null || $dpia->outcome->value === 'open') {
                $gaps[] = ['key' => 'dpia_required', 'status' => 'missing', 'activity_id' => $act->id,
                    'trigger' => "„{$act->name}“ mit DSFA-Bedarf ohne abgeschlossene DSFA"];
            }
        }
        // 5) Verarbeitungstaetigkeit ohne zugeordnete TOM
        foreach (ProcessingActivity::query()->where('organization_id', $orgId)->get() as $act) {
            if (! MeasureAssignment::query()->where('activity_id', $act->id)->exists()) {
                $gaps[] = ['key' => 'tom_assigned', 'status' => 'missing', 'activity_id' => $act->id,
                    'trigger' => "„{$act->name}“ ohne zugeordnete TOM"];
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
            $def = $defs[$g['key']] ?? ['label' => $g['key'], 'category' => null];
            $finding->fill([
                'label' => $def['label'],
                'category' => $def['category'] ?? null,
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
