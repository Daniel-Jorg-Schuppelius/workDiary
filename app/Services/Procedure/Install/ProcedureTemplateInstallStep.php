<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplateInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure\Install;

use App\Models\Platform\{Organization, User};
use App\Models\Procedure\ProcedureTemplate;
use App\Services\Classification\Contracts\ProfileInstallStep;
use App\Services\Procedure\ProcedureTemplateService;

/** Prozedurvorlagen eines Branchenprofils (MVP-710): idempotent, veröffentlichte Versionen bleiben unangetastet. */
final class ProcedureTemplateInstallStep implements ProfileInstallStep {
    public function __construct(private readonly ProcedureTemplateService $templates) {}

    public function key(): string {
        return 'procedure_templates';
    }

    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = (string) ($row['code'] ?? '');
                if ($code === '') {
                    continue;
                }

                $existing = ProcedureTemplate::query()
                    ->where('organization_id', $organization->id)
                    ->where('code', $code)
                    ->first();

                // Vorlage existiert bereits (oder lokal angepasst): idempotent überspringen, nie überschreiben
                // (auch nicht bei force – eine veröffentlichte Prozedurversion ist unveränderlich).
                if ($existing instanceof ProcedureTemplate) {
                    $skipped++;

                    continue;
                }

                // Vollständige Vorlage (Name/Schritte) nur bei deklarativer Beschreibung UND vorhandenem Akteur (die
                // Version braucht einen Autor). Reine Code-Platzhalter ohne Schritte werden als Folgearbeit übersprungen.
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                /** @var list<array<string, mixed>> $steps */
                $steps = (array) ($row['steps'] ?? []);
                if ($name === '' || $steps === [] || ! $actor instanceof User) {
                    $skipped++;

                    continue;
                }

                $service = $this->templates;
                $template = $service->create($organization, $actor, [
                    'code' => $code,
                    'name' => $name,
                    'description' => isset($row['description']) ? (string) $row['description'] : null,
                    'domain' => isset($row['domain']) ? (string) $row['domain'] : null,
                    'active' => true,
                ]);

                $version = $template->versions()->firstOrFail();
                if (isset($row['risk_level'])) {
                    $service->updateVersion($version, ['risk_level' => (string) $row['risk_level']]);
                }

                $normalizedSteps = [];
                foreach ($steps as $step) {
                    $stepCode = (string) ($step['code'] ?? '');
                    $stepType = (string) ($step['step_type'] ?? '');
                    $stepLabel = (string) ($step['label'] ?? '');
                    if ($stepCode === '' || $stepType === '' || $stepLabel === '') {
                        continue;
                    }

                    $normalizedSteps[] = [
                        'code' => $stepCode,
                        'step_type' => $stepType,
                        'label' => $stepLabel,
                        'description' => isset($step['description']) ? (string) $step['description'] : null,
                        'required' => (bool) ($step['required'] ?? true),
                        'blocking' => (bool) ($step['blocking'] ?? true),
                        'requires_second_person' => (bool) ($step['requires_second_person'] ?? false),
                        'requires_proof_type' => isset($step['requires_proof_type']) ? (string) $step['requires_proof_type'] : null,
                    ];
                }

                $service->syncSteps($version, $normalizedSteps);
                $service->publish($version, $actor);

                $created++;
            }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
