<?php

/*
 * Filename     : BranchProfileProcedureTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Procedure\ProcedureStepType;
use App\Models\{Organization, ProcedureStepDef, ProcedureTemplate, User};
use App\Services\Classification\BranchProfileInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prozedurvorlagen der Branchenprofile (Vollscan 2026-09-15, `C2-02` / `MVP-798`).
 *
 * Der Installer überspringt Vorlagen ohne Name oder Schritte still — die Doku
 * der Profile listet sie trotzdem vorbehaltlos. Gemeldet waren 22 Platzhalter
 * in fünf Profilen; beim Füllen zeigte sich, dass elf weitere Profile noch 53
 * tragen. Dieses Gate hält die gefüllten Profile bei null und lässt die
 * Restschuld nur schrumpfen, nie wachsen.
 */
class BranchProfileProcedureTemplateTest extends TestCase {
    use RefreshDatabase;

    /**
     * Höchstzahl an Platzhaltern je Profil — seit MVP-835 (2026-09-22) überall
     * null; ein neues Profil steht mit 0 drin und darf keine Platzhalter mitbringen.
     *
     * @var array<string, int>
     */
    private const PLACEHOLDER_BUDGET = [
        'anlagenwartung' => 0,
        'bau-ausbau' => 0,
        'druck-kopiershop' => 0,
        'elektro' => 0,
        'facility' => 0,
        'galabau' => 0,
        'gebaeudereinigung' => 0,
        'handwerk' => 0,
        'it' => 0,
        'kfz-fuhrparkservice' => 0,
        'partyservice' => 0,
        'pflege' => 0,
        'shk' => 0,
        'sicherheitsdienst' => 0,
        'spedition' => 0,
        'steuerberater' => 0,
        'taxi-mietwagen' => 0,
        'veranstalter' => 0,
        'veranstaltungstechnik' => 0,
    ];

    public function test_placeholder_procedure_templates_never_grow(): void {
        $types = array_map(static fn (ProcedureStepType $type): string => $type->value, ProcedureStepType::cases());
        $violations = [];

        foreach ($this->profileCodes() as $code) {
            $placeholders = 0;
            foreach ($this->templatesOf($code) as $row) {
                if (trim((string) ($row['name'] ?? '')) === '' || ($row['steps'] ?? []) === []) {
                    $placeholders++;

                    continue;
                }
                foreach ((array) $row['steps'] as $step) {
                    if (($step['code'] ?? '') === '' || ($step['label'] ?? '') === '' || ! in_array($step['step_type'] ?? '', $types, true)) {
                        $violations[] = "{$code}: {$row['code']} hat einen Schritt ohne Code, Beschriftung oder gültigen Typ";
                    }
                }
            }

            $budget = self::PLACEHOLDER_BUDGET[$code] ?? null;
            if ($budget === null) {
                $violations[] = "{$code}: neues Profil ohne Eintrag in PLACEHOLDER_BUDGET";
            } elseif ($placeholders > $budget) {
                $violations[] = "{$code}: {$placeholders} Platzhalter, erlaubt sind {$budget}";
            } elseif ($placeholders < $budget) {
                $violations[] = "{$code}: nur noch {$placeholders} Platzhalter — PLACEHOLDER_BUDGET auf {$placeholders} senken";
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_budget_lists_no_missing_profiles(): void {
        $this->assertSame([], array_values(array_diff(array_keys(self::PLACEHOLDER_BUDGET), $this->profileCodes())));
    }

    public function test_every_profile_publishes_every_declared_template(): void {
        foreach ($this->profileCodes() as $code) {
            $organization = Organization::factory()->create();
            $actor = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $organization->id]);

            $result = (new BranchProfileInstaller)->install($organization, $code, $actor);
            $declared = $this->templatesOf($code);

            $this->assertSame(count($declared), $result['created']['procedure_templates'], "{$code}: nicht jede Vorlage wurde angelegt");
            $this->assertSame(0, $result['skipped']['procedure_templates'], "{$code}: Vorlagen still übersprungen");

            foreach ($declared as $row) {
                $template = ProcedureTemplate::query()->where('organization_id', $organization->id)->where('code', $row['code'])->firstOrFail();
                $version = $template->versions()->firstOrFail();

                $this->assertTrue($version->isPublished(), "{$row['code']} ist nicht veröffentlicht");
                $this->assertSame(
                    count((array) $row['steps']),
                    ProcedureStepDef::query()->where('procedure_template_version_id', $version->id)->count(),
                    "{$row['code']}: Schrittzahl weicht ab",
                );
            }
        }
    }

    /** @return list<string> */
    private function profileCodes(): array {
        $codes = array_map(static fn (string $file): string => basename($file, '.php'), (array) glob(database_path('data/branchprofiles/*.php')));
        sort($codes);

        return $codes;
    }

    /** @return list<array<string, mixed>> */
    private function templatesOf(string $code): array {
        $profile = require database_path("data/branchprofiles/{$code}.php");

        return array_values((array) ($profile['procedure_templates'] ?? []));
    }
}
