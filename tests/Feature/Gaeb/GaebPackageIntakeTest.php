<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPackageIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\GaebImportStatus;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\{Document, GaebImport, User};
use App\Services\Gaeb\GaebPackageIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/**
 * Paketeingang für Vergabeunterlagen (MVP-627).
 *
 * Vergabestellen liefern ein ZIP: Leistungsverzeichnis, Bewerbungsbedingungen,
 * Pläne. Der Eingang trennt beides — GAEB wird **vorgeschlagen**, nicht
 * importiert.
 */
final class GaebPackageIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Storage::fake('local');
    }

    /** Ein knappes, aber vollständiges X83 — mit Position, sonst scheitert der Preflight. */
    private function gaebXml(string $project = 'Neubau Kita'): string {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA83/3.3">
          <GAEBInfo><Version>3.3</Version><Date>2026-08-18</Date><ProgSystem>WorkDiary Test</ProgSystem></GAEBInfo>
          <PrjInfo><NamePrj>{$project}</NamePrj><Cur>EUR</Cur></PrjInfo>
          <Award>
            <DP>83</DP>
            <Cur>EUR</Cur>
            <BoQ ID="BOQ-1">
              <BoQInfo>
                <Name>{$project}</Name>
                <BoQBkdn><Type>BoQLevel</Type><Length>3</Length><Num>Yes</Num></BoQBkdn>
                <BoQBkdn><Type>Item</Type><Length>4</Length><Num>Yes</Num></BoQBkdn>
              </BoQInfo>
              <BoQBody>
                <BoQCtgy RNoPart="001" ID="C-001">
                  <LblTx><p><span>Baustelleneinrichtung</span></p></LblTx>
                  <BoQBody>
                    <Itemlist>
                      <Item RNoPart="0010" ID="I-1">
                        <Qty>1.000</Qty>
                        <QU>psch</QU>
                        <Description>
                          <CompleteText>
                            <OutlineText><OutlTxt><TextOutlTxt><p><span>Baustelle einrichten</span></p></TextOutlTxt></OutlTxt></OutlineText>
                          </CompleteText>
                        </Description>
                      </Item>
                    </Itemlist>
                  </BoQBody>
                </BoQCtgy>
              </BoQBody>
            </BoQ>
          </Award>
        </GAEB>
        XML;
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string {
        $path = tempnam(sys_get_temp_dir(), 'gaeb-package-test-');
        $archive = new ZipArchive;
        $archive->open((string) $path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $archive->addFromString($name, $contents);
        }
        $archive->close();

        $result = (string) file_get_contents((string) $path);
        unlink((string) $path);

        return $result;
    }

    private function opportunity(): ApplicationOpportunity {
        return ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Neubau Kita',
            'kind' => 'tender',
            'status' => 'in_progress',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * GAEB wird vorgeschlagen, nicht importiert: Ein Paket kann mehrere Lose
     * enthalten, von denen nur eines interessiert.
     */
    public function test_gaeb_files_become_proposals_and_the_rest_becomes_documents(): void {
        $opportunity = $this->opportunity();
        $package = $this->zip([
            'LV_Los1.x83' => $this->gaebXml('Los 1'),
            'Bewerbungsbedingungen.txt' => 'Bitte alle Vordrucke ausfüllen.',
        ]);

        $result = app(GaebPackageIntakeService::class)->intake(
            $package, 'Vergabeunterlagen.zip', $this->organization->id, $this->admin, $opportunity
        );

        $this->assertCount(1, $result['gaeb']);
        $this->assertSame(1, $result['documents']);

        $proposal = GaebImport::query()->firstOrFail();
        $this->assertSame(GaebImportStatus::Pending, $proposal->status);
        $this->assertSame('LV_Los1.x83', $proposal->filename);
        $this->assertSame('Vergabeunterlagen.zip', $proposal->package_name);
        $this->assertSame($opportunity->id, $proposal->application_opportunity_id);
        // Die Datei liegt bereit — importiert wird erst auf Zuruf.
        $this->assertNotNull($proposal->stored_path);
        Storage::disk('local')->assertExists((string) $proposal->stored_path);

        // Das Restdokument hängt an der Akte.
        $document = Document::query()->firstOrFail();
        $this->assertSame($opportunity->id, $document->documentable_id);
    }

    /** Die Familie kommt aus dem Inhalt — die Endung darf lügen. */
    public function test_format_is_detected_from_content_not_extension(): void {
        $package = $this->zip(['unterlagen.dat' => $this->gaebXml()]);

        $result = app(GaebPackageIntakeService::class)->intake(
            $package, 'Paket.zip', $this->organization->id, $this->admin, $this->opportunity()
        );

        $this->assertCount(1, $result['gaeb']);
        $this->assertSame(\ERechnungToolkit\Enums\GaebFormat::DaXml->value, $result['gaeb'][0]->source_format);
    }

    /** Portale stellen Unterlagen bei jeder Berichtigung erneut bereit. */
    public function test_same_file_is_not_proposed_twice(): void {
        $opportunity = $this->opportunity();
        $package = $this->zip(['LV.x83' => $this->gaebXml()]);

        app(GaebPackageIntakeService::class)->intake($package, 'Paket.zip', $this->organization->id, $this->admin, $opportunity);
        $second = app(GaebPackageIntakeService::class)->intake($package, 'Paket.zip', $this->organization->id, $this->admin, $opportunity);

        $this->assertSame([], $second['gaeb']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, GaebImport::query()->count());
    }

    /** Eine Einzeldatei ist ein Paket mit einem Eintrag. */
    public function test_single_file_needs_no_archive(): void {
        $result = app(GaebPackageIntakeService::class)->intake(
            $this->gaebXml(), 'LV.x83', $this->organization->id, $this->admin, $this->opportunity()
        );

        $this->assertCount(1, $result['gaeb']);
        $this->assertNull($result['gaeb'][0]->package_name);
    }

    /**
     * Ohne Vergabevorgang fehlt der Akte-Bezug: Restdokumente blind ins DMS zu
     * legen, machte sie unauffindbar.
     */
    public function test_documents_need_a_case_to_belong_to(): void {
        $package = $this->zip(['LV.x83' => $this->gaebXml(), 'Plan.txt' => 'Grundriss']);

        $result = app(GaebPackageIntakeService::class)->intake(
            $package, 'Paket.zip', $this->organization->id, $this->admin
        );

        $this->assertCount(1, $result['gaeb']);
        $this->assertSame(0, $result['documents']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, Document::query()->count());
    }

    /** Ein Archiv aus fremder Hand darf nicht bestimmen, wohin geschrieben wird. */
    public function test_path_traversal_entries_are_skipped(): void {
        $package = $this->zip(['../evil.x83' => $this->gaebXml()]);

        $result = app(GaebPackageIntakeService::class)->intake(
            $package, 'Paket.zip', $this->organization->id, $this->admin, $this->opportunity()
        );

        $this->assertSame([], $result['gaeb']);
    }

    /**
     * Vollscan 2026-08-23, E7: Die Summe der entpackten Größen wurde nie
     * geprüft — ein kleiner Upload konnte sich im Speicher entfalten.
     */
    public function test_oversized_entries_are_rejected_before_extraction(): void {
        // 65 MB Nullbytes komprimieren auf wenige KB — genau das Profil einer ZIP-Bomb.
        $package = $this->zip(['bombe.x83' => str_repeat("\0", 65 * 1024 * 1024)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage((string) __('Das Paket überschreitet die zulässige entpackte Größe.'));

        app(GaebPackageIntakeService::class)->intake(
            $package, 'Paket.zip', $this->organization->id, $this->admin, $this->opportunity()
        );
    }

    // ── Oberfläche ───────────────────────────────────────────────────────

    public function test_upload_and_accept_creates_the_bill_of_quantities(): void {
        $opportunity = $this->opportunity();
        $package = $this->zip(['LV.x83' => $this->gaebXml()]);

        $this->actingAs($this->admin)->post(route('bill-of-quantities.packages.store'), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('Vergabeunterlagen.zip', $package),
            'opportunity' => $opportunity->sqid,
        ])->assertRedirect();

        $proposal = GaebImport::query()->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('bill-of-quantities.packages.accept', $proposal))
            ->assertRedirect();

        $opportunity->refresh();
        $this->assertNotNull($opportunity->bill_of_quantity_id);
        // Der Vorschlag ist aufgegangen — es bleibt der Importlauf.
        $this->assertSame(0, GaebImport::query()->where('status', GaebImportStatus::Pending)->count());
    }

    public function test_discarding_removes_the_stored_file(): void {
        $result = app(GaebPackageIntakeService::class)->intake(
            $this->gaebXml(), 'LV.x83', $this->organization->id, $this->admin, $this->opportunity()
        );
        $proposal = $result['gaeb'][0];
        $path = (string) $proposal->stored_path;

        $this->actingAs($this->admin)
            ->delete(route('bill-of-quantities.packages.discard', $proposal))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, GaebImport::query()->count());
    }

    public function test_package_list_renders(): void {
        app(GaebPackageIntakeService::class)->intake(
            $this->gaebXml(), 'LV_Los1.x83', $this->organization->id, $this->admin, $this->opportunity()
        );

        $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.packages'))
            ->assertOk()
            ->assertSee('LV_Los1.x83');
    }
}
