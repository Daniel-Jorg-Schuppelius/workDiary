<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepConfigTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureStepRunStatus, ProcedureStepType};
use App\Models\Procedure\ProcedureLibraryStep;
use App\Services\Procedure\StepConfigForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Procedure\Concerns\BuildsProcedureRuns;
use Tests\TestCase;

/** Schrittkonfiguration je Typ im Designer und in der Schrittbibliothek (MVP-1569). */
class ProcedureStepConfigTest extends TestCase {
    use BuildsProcedureRuns;
    use RefreshDatabase;

    public function test_designer_stores_the_configuration_of_each_step_type(): void {
        $lead = $this->lead();
        $template = $this->draftTemplate($lead, []);

        $this->actingAs($lead)->put(route('procedures.update', $template), [
            'name' => 'Prüfablauf',
            'steps' => [
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis', 'required' => 'on', 'options' => 'in Ordnung, Mangel , nicht geprüft', 'unit' => 'V'],
                ['code' => 'spannung', 'step_type' => 'number', 'label' => 'Spannung', 'unit' => 'V', 'min' => '207', 'max' => '253', 'options' => 'a, b'],
                ['code' => 'trocknen', 'step_type' => 'wait', 'label' => 'Trocknen', 'wait_seconds' => '900'],
                ['code' => 'sichern', 'step_type' => 'backup', 'label' => 'Sichern', 'backup_scope' => 'config', 'verify_method' => 'checksum', 'storage_target' => 'attachment', 'min_size_kb' => '4', 'max_age_minutes' => '60', 'requires_prior_backup' => 'on'],
                ['code' => 'update', 'step_type' => 'confirm', 'label' => 'Update', 'requires_prior_backup' => 'on', 'prior_backup_max_age_minutes' => '120'],
                ['code' => 'freigabe', 'step_type' => 'confirm', 'label' => 'Freigabe', 'requires_second_person' => 'on', 'second_person_role' => 'teamleitung', 'second_person_min_qualification' => 'EFK', 'second_person_allow_self' => 'on'],
                ['code' => 'ohne', 'step_type' => 'confirm', 'label' => 'Ohne Vier-Augen', 'second_person_role' => 'teamleitung'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('procedures.edit', $template));

        $config = $this->draftOf($template)->steps()->get()->pluck('config', 'code');
        $this->assertSame(['options' => ['in Ordnung', 'Mangel', 'nicht geprüft']], $config['ergebnis']);
        $this->assertSame(['unit' => 'V', 'min' => 207, 'max' => 253], $config['spannung']);
        $this->assertSame(['wait_seconds' => 900], $config['trocknen']);
        $this->assertSame(['backup_scope' => 'config', 'min_size_kb' => 4, 'max_age_minutes' => 60, 'verify_method' => 'checksum', 'storage_target' => 'attachment'], $config['sichern']);
        $this->assertSame(['requires_prior_backup' => true, 'prior_backup_max_age_minutes' => 120], $config['update']);
        $this->assertSame(['second_person_role' => 'teamleitung', 'second_person_min_qualification' => 'EFK', 'second_person_self_exclusion' => false], $config['freigabe']);
        $this->assertNull($config['ohne']);

        $this->actingAs($lead)->get(route('procedures.edit', $template))->assertOk()
            ->assertSee(__('procedure.field.options'))
            ->assertSee('in Ordnung, Mangel, nicht geprüft')
            ->assertSee(__('procedure.field.requiresPriorBackup'));
    }

    public function test_saving_in_the_designer_keeps_configuration_keys_it_does_not_manage(): void {
        $lead = $this->lead();
        $template = $this->draftTemplate($lead, [
            ['code' => 'messung', 'step_type' => ProcedureStepType::Number->value, 'label' => 'Messung', 'config' => [
                'unit' => 'bar', 'spc' => ['target' => 6, 'tolerance' => 0.5], 'signature_required' => true, 'checksum_algo' => 'sha512',
            ]],
        ]);
        $form = app(StepConfigForm::class)->toForm($this->draftOf($template)->steps()->firstOrFail()->config);

        // Der Designer liefert die fremden Schlüssel im verborgenen Feld zurück.
        $this->actingAs($lead)->get(route('procedures.edit', $template))->assertOk()->assertSee('checksum_algo');
        $this->actingAs($lead)->put(route('procedures.update', $template), [
            'name' => 'Prüfablauf',
            'steps' => [['code' => 'druck', 'step_type' => 'number', 'label' => 'Druck', 'unit' => 'mbar', 'config_extra' => $form['config_extra']]],
        ])->assertSessionHasNoErrors();

        $config = $this->draftOf($template)->steps()->firstOrFail()->config;
        $this->assertSame('mbar', $config['unit']);
        $this->assertSame(['target' => 6, 'tolerance' => 0.5], $config['spc']);
        $this->assertTrue($config['signature_required']);
        $this->assertSame('sha512', $config['checksum_algo']);

        // Ein eingeschmuggelter gepflegter Schlüssel im verborgenen Feld gilt nicht.
        $this->actingAs($lead)->put(route('procedures.update', $template), [
            'name' => 'Prüfablauf',
            'steps' => [['code' => 'druck', 'step_type' => 'confirm', 'label' => 'Druck', 'config_extra' => '{"second_person_self_exclusion":false,"note":"x"}']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['note' => 'x'], $this->draftOf($template)->steps()->firstOrFail()->config);
    }

    public function test_library_step_carries_its_configuration_into_the_draft(): void {
        $lead = $this->lead();

        $this->actingAs($lead)->post(route('procedures.library.store'), [
            'code' => 'sichtpruefung', 'step_kind' => 'choice', 'label' => 'Sichtprüfung', 'is_required' => '1',
            'options' => 'bestanden, nicht bestanden', 'wait_seconds' => '30',
        ])->assertSessionHasNoErrors();
        $library = ProcedureLibraryStep::query()->firstOrFail();
        $this->assertSame(['options' => ['bestanden', 'nicht bestanden']], $library->config);

        $this->actingAs($lead)->get(route('procedures.library.edit', $library))->assertOk()->assertSee('bestanden, nicht bestanden');
        $this->actingAs($lead)->put(route('procedures.library.update', $library), [
            'code' => 'sichtpruefung', 'step_kind' => 'number', 'label' => 'Sichtprüfung', 'unit' => 'mm', 'max' => '2.5',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['unit' => 'mm', 'max' => 2.5], $library->fresh()->config);

        $template = $this->draftTemplate($lead, []);
        $this->actingAs($lead)->post(route('procedures.library.insert', $template), ['library_steps' => [$library->sqid]])->assertSessionHasNoErrors();
        $this->assertSame(['unit' => 'mm', 'max' => 2.5], $this->draftOf($template)->steps()->firstOrFail()->config);
    }

    public function test_choice_step_offers_its_options_and_rejects_other_values(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [
            ['code' => 'ergebnis', 'step_type' => ProcedureStepType::Choice->value, 'label' => 'Ergebnis', 'config' => ['options' => ['bestanden', 'Mangel']]],
        ]), $lead);
        $step = $this->stepOf($run, 'ergebnis');

        $this->actingAs($lead)->get(route('procedure-runs.show', $run))->assertOk()->assertSee('bestanden')->assertSee('Mangel');
        $this->actingAs($lead)->post(route('procedure-runs.steps.execute', [$run, $step]), ['status' => 'done', 'value' => 'vielleicht'])->assertSessionHasErrors('value');
        $this->actingAs($lead)->post(route('procedure-runs.steps.execute', [$run, $step]), ['status' => 'done', 'value' => 'Mangel'])->assertSessionHas('success');
        $this->assertSame('Mangel', data_get($step->fresh()->value_json, 'value'));
    }

    /** Viele Profilvorlagen tragen Auswahlschritte ohne Optionen: sie bleiben abschließbar und nehmen Freitext. */
    public function test_required_choice_step_without_options_can_be_completed_with_free_text(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [
            ['code' => 'ursache', 'step_type' => ProcedureStepType::Choice->value, 'label' => 'Ursache einstufen', 'required' => true],
        ]), $lead);
        $step = $this->stepOf($run, 'ursache');

        $this->actingAs($lead)->get(route('procedure-runs.show', $run))->assertOk()->assertSee('name="value"', false);
        $this->actingAs($lead)->post(route('procedure-runs.steps.execute', [$run, $step]), ['status' => 'done', 'value' => 'Verschleiß'])->assertSessionHas('success');
        $this->assertSame(ProcedureStepRunStatus::Done, $step->fresh()->status);
        $this->assertSame('Verschleiß', data_get($step->fresh()->value_json, 'value'));
        $this->actingAs($lead)->post(route('procedure-runs.complete', $run))->assertSessionHas('success');
    }
}
