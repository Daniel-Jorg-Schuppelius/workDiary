<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\ProcedureStepType;
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureTemplate;
use App\Services\Procedure\WorkInstructionParser;
use CommonToolkit\Helper\Office\OfficeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** MVP-913: Arbeitsanweisung importieren — Parser, Vorschau, Entwurf. */
final class ProcedureImportTest extends TestCase {
    use RefreshDatabase;

    private const TEXT = "Arbeitsanweisung Filterwechsel\n1. Anlage abschalten\nHauptschalter auf 0 und gegen Wiedereinschalten sichern.\n2. Filter tauschen\n3) Differenzdruck messen und notieren\nSchritt 4: Foto vom neuen Filter\n- Abnahme durch Kunden unterschreiben lassen";

    public function test_parser_splits_numbered_steps_and_guesses_the_type(): void {
        $parsed = app(WorkInstructionParser::class)->parse(self::TEXT);

        $this->assertSame('Arbeitsanweisung Filterwechsel', $parsed['title']);
        $this->assertSame(['Anlage abschalten', 'Filter tauschen', 'Differenzdruck messen und notieren', 'Foto vom neuen Filter', 'Abnahme durch Kunden unterschreiben lassen'], array_column($parsed['steps'], 'label'));
        $this->assertSame('Hauptschalter auf 0 und gegen Wiedereinschalten sichern.', $parsed['steps'][0]['description']);
        $this->assertSame(['confirm', 'confirm', 'number', 'photo', 'signature'], array_column($parsed['steps'], 'step_type'));
        $this->assertSame(['Zeile eins', 'Zeile zwei'], array_column(app(WorkInstructionParser::class)->parse("Titel\nZeile eins\nZeile zwei")['steps'], 'label'));
    }

    public function test_preview_and_store_create_a_draft_procedure(): void {
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);

        $this->actingAs($lead)->get(route('procedures.import.form'))->assertOk()->assertSee('name="file"', false);
        $this->actingAs($lead)->post(route('procedures.import.preview'), ['file' => UploadedFile::fake()->createWithContent('filterwechsel.txt', self::TEXT)])
            ->assertOk()->assertSee('Differenzdruck messen und notieren')->assertSee('value="ARBEITSANWEISUNG_FILTERWECHSEL"', false);

        $this->actingAs($lead)->post(route('procedures.import.store'), [
            'name' => 'Filterwechsel', 'code' => 'FILTER',
            'steps' => [
                ['include' => '1', 'label' => 'Anlage abschalten', 'description' => 'Sichern', 'step_type' => ProcedureStepType::Confirm->value],
                ['include' => '0', 'label' => 'Filter tauschen', 'step_type' => ProcedureStepType::Confirm->value],
                ['include' => '1', 'label' => 'Foto vom neuen Filter', 'step_type' => ProcedureStepType::Photo->value],
            ],
        ])->assertRedirect();

        $template = ProcedureTemplate::query()->where('code', 'FILTER')->firstOrFail();
        $steps = $template->versions()->firstOrFail()->steps()->orderBy('sort_order')->get();
        $this->assertSame(['S01', 'S02'], $steps->pluck('code')->all());
        $this->assertSame(ProcedureStepType::Photo, $steps[1]->step_type);
        $this->assertNull($template->versions()->firstOrFail()->published_at, 'Entwurf');

        $this->actingAs($lead)->post(route('procedures.import.preview'), ['text' => ''])->assertSessionHasErrors('file');
    }

    public function test_word_documents_are_read_through_libreoffice(): void {
        if (! OfficeHelper::isAvailable()) {
            $this->markTestSkipped('LibreOffice nicht installiert.');
        }
        $lead = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $lead->organization);
        $dir = storage_path('app/tmp/import-test-' . uniqid());
        \CommonToolkit\Helper\FileSystem\Folder::create($dir, 0775, true);
        \CommonToolkit\Helper\FileSystem\File::write($dir . '/anweisung.txt', self::TEXT);
        $docx = OfficeHelper::convertToFile($dir . '/anweisung.txt', 'docx', $dir, 60.0);
        $this->assertNotNull($docx);

        $this->actingAs($lead)->post(route('procedures.import.preview'), ['file' => new UploadedFile($docx, 'anweisung.docx', null, null, true)])
            ->assertOk()->assertSee('Foto vom neuen Filter');
        \CommonToolkit\Helper\FileSystem\Folder::delete($dir, true);
    }
}
