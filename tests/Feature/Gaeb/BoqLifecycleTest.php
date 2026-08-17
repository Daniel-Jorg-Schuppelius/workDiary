<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqChangeOrderStatus, BoqItemStatus, BoqItemType, BoqProgressSource, GaebPhase};
use App\Models\{BillOfQuantity, BoqItem, User};
use App\Services\Gaeb\{BoqCostingService, BoqExportService, BoqProgressService, BoqWorkflowException, BoqWorkflowService, GaebImportService};
use CommonToolkit\ValueObjects\Money;
use Database\Seeders\GaebDemoSeeder;
use ERechnungToolkit\Enums\GaebFormat;
use ERechnungToolkit\Parsers\GaebDaXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 049, MVP-083..086: Aufmaß/Fortschritt + Nachkalkulation, LV-Workflow
 * (Status/Nachtrag/Restleistung), GAEB-Export-Roundtrip und Demo-Daten.
 */
final class BoqLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function importedBoq(): BillOfQuantity {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x86.xml', $this->organization->id);

        return BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
    }

    public function test_progress_updates_executed_remaining_and_status(): void {
        $boq = $this->importedBoq();
        $item = $boq->items()->where('reference_no', '01.0010')->firstOrFail();

        app(BoqProgressService::class)->record($item, '60', ['source' => BoqProgressSource::Measurement]);

        $item->refresh();
        $this->assertSame(60.0, $item->executedQuantity());
        $this->assertSame(40.0, $item->remainingQuantity());
        $this->assertSame(BoqItemStatus::InProgress, $item->status);
    }

    public function test_costing_summarizes_planned_and_executed(): void {
        $boq = $this->importedBoq();
        // Abrechenbar mit Preis: 01.0010 (100×12.50) + 02.0010 (250×89.90).
        $item = $boq->items()->where('reference_no', '01.0010')->firstOrFail();
        app(BoqProgressService::class)->record($item, '100');

        $summary = app(BoqCostingService::class)->summarize($boq->fresh() ?? $boq);

        $this->assertEqualsWithDelta(1250.0 + 22475.0, $summary['planned'], 0.01);
        $this->assertEqualsWithDelta(1250.0, $summary['executed'], 0.01);
    }

    public function test_workflow_guards_invalid_transitions(): void {
        $boq = $this->importedBoq();
        $workflow = app(BoqWorkflowService::class);

        $workflow->transitionBill($boq, BoqItemStatus::Ordered); // imported → ordered erlaubt
        $this->assertSame(BoqItemStatus::Ordered, $boq->fresh()?->status);

        $this->expectException(BoqWorkflowException::class);
        $workflow->transitionBill($boq, BoqItemStatus::Quoted); // ordered → quoted verboten
    }

    public function test_addendum_is_created_as_own_item(): void {
        $boq = $this->importedBoq();
        $before = $boq->items()->count();

        $item = app(BoqWorkflowService::class)->createAddendum($boq, [
            'reference_no' => 'N01',
            'short_text' => 'Nachtrag',
            'quantity' => '5',
            'unit' => 'm',
            'unit_price' => '10',
        ]);

        $this->assertTrue($item->is_addendum);
        $this->assertSame(BoqItemStatus::Draft, $item->status);
        $this->assertSame($before + 1, $boq->items()->count());
    }

    public function test_remaining_items_excludes_completed(): void {
        $boq = $this->importedBoq();
        $workflow = app(BoqWorkflowService::class);

        // 02.0010 voll aufmessen und abschließen → fällt aus der Restleistung.
        $done = $boq->items()->where('reference_no', '02.0010')->firstOrFail();
        app(BoqProgressService::class)->record($done, '250');
        $done->forceFill(['status' => BoqItemStatus::Completed])->save();

        $remainingRefs = $workflow->remainingItems($boq)->pluck('reference_no')->all();

        $this->assertContains('01.0010', $remainingRefs);
        $this->assertNotContains('02.0010', $remainingRefs);
    }

    public function test_export_roundtrips_and_is_recorded(): void {
        $boq = $this->importedBoq();
        $service = app(BoqExportService::class);

        $result = $service->export($boq, GaebPhase::Award);

        // Protokolliert.
        $this->assertDatabaseHas('boq_exports', [
            'bill_of_quantity_id' => $boq->id,
            'phase' => GaebPhase::Award->value,
            'file_hash' => $result['export']->file_hash,
        ]);

        // Deterministisch: gleicher Inhalt → gleicher Hash.
        $this->assertSame($result['export']->file_hash, $service->contentHash($boq, GaebPhase::Award));

        // Roundtrip: Export erneut parsen → gleiche Ordnungszahlen.
        $parsed = (new GaebDaXmlParser)->parse($result['xml']);
        $refs = [];
        foreach ($parsed->getItems() as $item) {
            $refs[] = $item->getReference();
        }
        $this->assertEqualsCanonicalizing(['01.0001', '01.0010', '01.0020', '02.0010'], $refs);
    }

    /**
     * Feature 108, MVP-615: Die Indexstufe muss als Attribut RNoIndex exportiert
     * werden — in RNoPart wäre sie eine unzulässige Verlängerung der
     * Positionsstufe und der Roundtrip bräche.
     */
    public function test_export_writes_ordinal_index_as_rnoindex_attribute(): void {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x83_index.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x83_index.xml', $this->organization->id);
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);

        $result = app(BoqExportService::class)->export($boq, GaebPhase::RequestForBid);

        $this->assertStringContainsString('RNoIndex="A"', $result['xml']);
        $this->assertStringNotContainsString('RNoPart="0010.A"', $result['xml']);

        $refs = [];
        foreach ((new GaebDaXmlParser)->parse($result['xml'])->getItems() as $item) {
            $refs[] = $item->getReference();
        }
        $this->assertEqualsCanonicalizing(
            ['001.001.0010', '001.001.0010.1', '001.001.0010.A', '999.999.9999', '999.999.9999.z'],
            $refs,
        );
    }

    /**
     * Feature 108, MVP-615: Zuschlagsposition, Hinweistext, Bedarfs-/Grund-/
     * Alternativkennzeichnung und Unterbeschreibungen überstehen Import und
     * Export unverändert.
     */
    public function test_item_traits_survive_import_and_export(): void {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x83_traits.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x83_traits.xml', $this->organization->id);
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);

        $this->assertSame(11, $boq->items()->count());

        $markup = $boq->items()->where('reference_no', '001.0040')->firstOrFail();
        $this->assertSame(BoqItemType::Markup, $markup->type);
        $this->assertSame('AllInCat', $markup->markup_type);

        $base = $boq->items()->where('reference_no', '001.0020')->firstOrFail();
        $this->assertSame(BoqItemType::Base, $base->type);
        $this->assertSame('1', $base->alternative_group);
        $this->assertSame(0, $base->alternative_no);

        $leading = $boq->items()->where('reference_no', '001.0050')->firstOrFail();
        $this->assertCount(2, (array) $leading->sub_descriptions);

        $exported = app(BoqExportService::class)->export($boq, GaebPhase::RequestForBid)['xml'];

        $this->assertStringContainsString('<MarkupItem', $exported);
        $this->assertStringContainsString('<MarkupType>AllInCat</MarkupType>', $exported);
        $this->assertStringContainsString('<Remark', $exported);
        $this->assertStringContainsString('<Provis>WithTotal</Provis>', $exported);
        $this->assertStringContainsString('<ALNSerNo>0</ALNSerNo>', $exported);
        $this->assertStringContainsString('<SumDescr>Yes</SumDescr>', $exported);
        $this->assertStringContainsString('<ComplTSB>Yes</ComplTSB>', $exported);
        $this->assertStringContainsString('<TextComplement MarkLbl="60" Kind="Bidder">', $exported);
        $this->assertStringNotContainsString('[[TC:', $exported);

        $reparsed = (new GaebDaXmlParser)->parse($exported);
        $this->assertSame(11, $reparsed->countItems());
        $types = [];
        foreach ($reparsed->getItems() as $item) {
            $types[$item->getReference()] = $item->getType()->value;
        }
        $this->assertSame(BoqItemType::Markup->value, $types['001.0040']);
        $this->assertSame(BoqItemType::Note->value, $types['001.0060']);
        $this->assertSame(BoqItemType::Optional->value, $types['001.0010.1']);

        // Textergänzungen kommen mit unveränderten Nummern zurück.
        $marks = [];
        foreach ($reparsed->getItems() as $candidate) {
            if ($candidate->getReference() === '001.0070') {
                foreach ($candidate->getTextComplements() as $complement) {
                    $marks[] = $complement->getMark();
                }
            }
        }
        $this->assertSame(['60', '61'], $marks);
    }

    /**
     * Feature 108, MVP-617: Die vom Auftraggeber vorgegebenen EP-Anteile und die
     * kalkulierten Beträge gehören in die Angebotsdatei zurück.
     */
    public function test_unit_price_breakdown_survives_roundtrip(): void {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x83_traits.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x83_traits.xml', $this->organization->id);
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);

        $this->assertCount(4, (array) $boq->up_components);

        // Die Vorgabe des Auftraggebers (Anzahl, Bezeichnungen, Aufgliederungs-
        // kennzeichen) gehört in die Dokumentphasen — hier X86.
        $award = app(BoqExportService::class)->export($boq, GaebPhase::Award)['xml'];

        $this->assertStringContainsString('<NoUPComps>4</NoUPComps>', $award);
        $this->assertStringContainsString('<LblUPComp1 Type="Wages">Lohn</LblUPComp1>', $award);
        $this->assertStringContainsString('<UPBkdn>Yes</UPBkdn>', $award);
        $this->assertCount(4, (array) (new GaebDaXmlParser)->parse($award)->getUpComponents());

        // Kalkuliert wird in WorkDiary, zurück geht die Angebotsphase X84 — dort
        // stehen nur noch die Werte, keine Bezeichnungen (X84-Schema).
        $exported = app(BoqExportService::class)->export($boq, GaebPhase::Bid)['xml'];

        $this->assertStringNotContainsString('<NoUPComps>', $exported);
        $this->assertStringNotContainsString('<LblUPComp1', $exported);
        $this->assertStringNotContainsString('<UPBkdn>', $exported);
        // Der Exporter kürzt Nachkommanullen wie bei UP/IT.
        $this->assertStringContainsString('<UPComp1>12</UPComp1>', $exported);

        $reparsed = (new GaebDaXmlParser)->parse($exported);
        foreach ($reparsed->getItems() as $item) {
            if ($item->getReference() === '001.0010') {
                $this->assertSame(
                    ['12.0000', '5.0000', '2.0000', '1.0000'],
                    array_map(static fn (Money $share): string => $share->getAmount(), $item->getUnitPriceComponents())
                );
            }
        }
    }

    /**
     * Feature 108, MVP-624: Nachträge tragen Nummer und Status aus GAEB — der
     * frühere `STLNo`-Zweig prüfte ein Element, das es im Schema nicht gibt.
     */
    public function test_change_order_number_and_status_survive_roundtrip(): void {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x83_traits.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x83_traits.xml', $this->organization->id);
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);

        $addendum = $boq->items()->where('reference_no', '001.0080')->firstOrFail();
        $this->assertTrue($addendum->is_addendum);
        $this->assertSame('N1', $addendum->change_order_no);
        $this->assertSame(BoqChangeOrderStatus::Offered, $addendum->change_order_status);
        $this->assertFalse($addendum->change_order_status->isFinal());

        // Normale Positionen sind keine Nachträge mehr, nur weil sie eine
        // Katalognummer führen.
        $this->assertFalse($boq->items()->where('reference_no', '001.0010')->firstOrFail()->is_addendum);

        // Die Nachtragsnummer reist nicht im Angebot: Der Bieter antwortet auf
        // ein Dokument, das die Nummern bereits kennt — das X84-Schema kennt
        // `CONo` an der Position gar nicht.
        $bid = app(BoqExportService::class)->export($boq, GaebPhase::Bid)['xml'];
        $this->assertStringNotContainsString('<CONo>', $bid);

        $award = app(BoqExportService::class)->export($boq, GaebPhase::Award)['xml'];
        $this->assertStringContainsString('<CONo>N1</CONo>', $award);
        $this->assertStringContainsString('<COStatus>Offered</COStatus>', $award);
    }

    public function test_http_export_and_progress_for_manager(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $boq = $this->importedBoq();
        $item = $boq->items()->where('reference_no', '01.0010')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('bill-of-quantities.items.progress', $item), ['quantity' => '25'])
            ->assertRedirect();
        $this->assertSame(25.0, $item->fresh()?->executedQuantity());

        $this->actingAs($admin)
            ->get(route('bill-of-quantities.export', $boq))
            ->assertOk()
            ->assertHeader('content-type', 'application/xml');
    }

    public function test_demo_seeder_builds_full_flow(): void {
        (new GaebDemoSeeder)->run($this->organization);

        $boq = BillOfQuantity::query()->where('organization_id', $this->organization->id)->firstOrFail();
        $this->assertTrue($boq->items()->where('is_addendum', true)->exists());
        $this->assertTrue(BoqItem::query()->where('bill_of_quantity_id', $boq->id)->whereHas('progress')->exists());
        $this->assertDatabaseHas('boq_exports', ['bill_of_quantity_id' => $boq->id]);
    }

    /**
     * Zurück geht das Herkunftsformat (Feature 108, D6): Wer als GAEB 90
     * importiert, bekommt GAEB 90 — und erfährt, was die Wandlung gekostet hat.
     */
    public function test_export_follows_the_source_format_and_logs_losses(): void {
        $records = [
            str_pad('00', 10) . '83' . str_pad('Musterprojekt', 50) . '1122PPPPI90',
            '1111       N',
            '12Erdarbeiten',
            '211111  10 NNN         00000051300m2  ',
            '25Boden loesen',
            '99' . str_repeat(' ', 66) . '000001',
        ];
        $file = '';
        foreach ($records as $index => $body) {
            $file .= str_pad(substr($body, 0, 74), 74) . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT) . "\r\n";
        }

        $import = app(GaebImportService::class)->import($file, 'vergabe.d83', $this->organization->id);
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);

        $this->assertSame('gaeb90', $boq->source_format);
        $this->assertSame('gaeb90', $import->source_format);

        $result = app(BoqExportService::class)->export($boq, GaebPhase::Award);

        $this->assertSame('gaeb90', $result['export']->format);
        // 80-Zeichen-Raster statt XML.
        $this->assertStringNotContainsString('<?xml', $result['xml']);
        $this->assertSame(80, strlen(explode("\r\n", $result['xml'])[0]));

        // Ausdrücklich nach DA XML: dann steht XML da und nichts geht verloren.
        $asXml = app(BoqExportService::class)->export($boq, GaebPhase::Award, null, GaebFormat::DaXml);
        $this->assertSame('daxml', $asXml['export']->format);
        $this->assertStringContainsString('<?xml', $asXml['xml']);
        $this->assertSame([], $asXml['losses']);
        $this->assertNull($asXml['export']->losses);
    }

    /**
     * Kostengruppen & Co. (Feature 109, MVP-636/588): Katalogzuordnungen und
     * Teilmengen überstehen Import und Export. Ohne Persistenz wirft der Import
     * genau die Information weg, wegen der AVA-Anbieter mit „DIN 276" werben.
     */
    public function test_catalog_assignments_and_splits_survive_import_and_export(): void {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA83/3.3">
  <GAEBInfo><Version>3.3</Version><VersDate>2021-05</VersDate><Date>2026-01-01</Date></GAEBInfo>
  <PrjInfo><NamePrj>Kostengruppen</NamePrj><Cur>EUR</Cur></PrjInfo>
  <Award><DP>83</DP><BoQ ID="B1">
    <BoQInfo>
      <Name>1</Name>
      <Ctlg><CtlgID>KG</CtlgID><CtlgType>cost group DIN 276 2018-12</CtlgType><CtlgName>Kostengruppen</CtlgName></Ctlg>
      <Ctlg><CtlgID>GEB</CtlgID><CtlgType>locality</CtlgType><CtlgName>Gebaeude</CtlgName></Ctlg>
    </BoQInfo>
    <BoQBody><BoQCtgy ID="C1" RNoPart="001">
      <LblTx><p><span>Erdarbeiten</span></p></LblTx>
      <CtlgAssign><CtlgID>KG</CtlgID><CtlgCode>300</CtlgCode></CtlgAssign>
      <BoQBody><Itemlist>
        <Item ID="I1" RNoPart="0010">
          <Qty>450.000</Qty>
          <QtySplit><Qty>300.000</Qty>
            <CtlgAssign><CtlgID>KG</CtlgID><CtlgCode>310</CtlgCode></CtlgAssign>
            <CtlgAssign><CtlgID>GEB</CtlgID><CtlgCode>H1</CtlgCode></CtlgAssign>
          </QtySplit>
          <QtySplit><Qty>150.000</Qty>
            <CtlgAssign><CtlgID>KG</CtlgID><CtlgCode>320</CtlgCode></CtlgAssign>
          </QtySplit>
          <QU>m3</QU>
          <CtlgAssign><CtlgID>KG</CtlgID><CtlgCode>310</CtlgCode></CtlgAssign>
          <Description><OutlineText><OutlTxt><TextOutlTxt><p><span>Boden loesen</span></p></TextOutlTxt></OutlTxt></OutlineText></Description>
        </Item>
      </Itemlist></BoQBody>
    </BoQCtgy></BoQBody>
  </BoQ></Award>
</GAEB>
XML;

        $import = app(GaebImportService::class)->import($xml, 'kostengruppen.x83', $this->organization->id);
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);

        // Katalogdefinitionen: der Typ trägt die Ausgabe der Norm.
        $this->assertSame(2, $boq->catalogs()->count());
        $kg = $boq->catalogs()->where('catalog_key', 'KG')->firstOrFail();
        $this->assertSame('cost group DIN 276 2018-12', $kg->type);
        $this->assertTrue($kg->isCostGroup());

        $item = $boq->items()->where('reference_no', '001.0010')->firstOrFail();
        $this->assertSame(['310'], $item->catalogAssignments()->pluck('code')->all());

        // Die Teilmengen tragen ihre eigenen Zuordnungen — 300 m³ auf KG 310,
        // 150 m³ auf KG 320.
        $splits = $item->quantitySplits()->get();
        $this->assertCount(2, $splits);
        $this->assertSame(['310', 'H1'], $splits[0]->catalogAssignments()->pluck('code')->all());
        $this->assertSame(['320'], $splits[1]->catalogAssignments()->pluck('code')->all());

        $section = $boq->sections()->where('reference_no', '001')->firstOrFail();
        $this->assertSame(['300'], $section->catalogAssignments()->pluck('code')->all());

        // Und alles kommt beim Export wieder heraus.
        $exported = app(BoqExportService::class)->export($boq, GaebPhase::RequestForBid)['xml'];
        $this->assertStringContainsString('<CtlgID>KG</CtlgID>', $exported);
        $this->assertStringContainsString('<CtlgCode>310</CtlgCode>', $exported);
        $this->assertStringContainsString('<QtySplit>', $exported);
        $this->assertStringContainsString('cost group DIN 276 2018-12', $exported);
    }

    /**
     * Aufmaß aus der X31 (MVP-571): Die Ansätze werden nachgerechnet und als
     * Mengenfortschritt gebucht — bei der Mengenermittlung gilt die selbst
     * errechnete Summe, nicht die gelieferte.
     */
    public function test_x31_takeoff_becomes_measured_progress(): void {
        // Zwei Rechtecke: 2,000 × 3,000 und 1,000 × 1,000 = 7,000 m².
        // Das Raster ist positionsgebunden (REB-VB 23.003), deshalb gebaut
        // statt getippt: Kennzeichen Sp. 13, Formel 30-31, Werte à 7 ab Sp. 34.
        $row = static function (string $kind, string $explanation, string $formula, array $values, string $address): string {
            $line = str_repeat(' ', 12) . $kind . str_pad(mb_substr($explanation, 0, 9), 9)
                . str_repeat(' ', 7) . str_pad($formula, 2) . '  ';
            foreach (array_pad($values, 5, '') as $value) {
                $line .= str_pad((string) $value, 7, ' ', STR_PAD_LEFT);
            }
            $line .= ' ' . $address;

            return $line . str_repeat(' ', max(0, 80 - mb_strlen($line)));
        };
        $rows = [
            $row('*', 'Aufmaß am 11 12 2020', '', [], '0001B0'),
            $row(' ', 'Wand A', '04', ['2000', '3000'], '0001C0'),
            $row(' ', 'Wand B', '04', ['1000', '1000'], '0001D0'),
        ];
        $items = '';
        foreach ($rows as $row) {
            $items .= '<QDetermItem><QTakeoff Row="' . htmlspecialchars($row, ENT_XML1) . '"/></QDetermItem>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA31/3.3">'
            . '<GAEBInfo><Version>3.3</Version><VersDate>2021-05</VersDate><Date>2026-01-01</Date></GAEBInfo>'
            . '<QtyDeterm><DP>31</DP><BoQ ID="B1"><BoQBody><Itemlist>'
            . '<Item ID="I1" RNoPart="0010"><QtyDeterm>' . $items . '</QtyDeterm></Item>'
            . '</Itemlist></BoQBody></BoQ></QtyDeterm></GAEB>';

        $import = app(GaebImportService::class)->import($xml, 'aufmass.x31', $this->organization->id);
        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
        $item = $boq->items()->firstOrFail();

        $progress = $item->progress()->get();
        $this->assertCount(1, $progress);
        $this->assertEqualsWithDelta(7.0, (float) (string) $progress[0]->quantity, 0.0001);
        $this->assertSame('measurement', $progress[0]->source->value);
        // Der Kommentar zählt nicht mit - zwei Zeilen tragen die Menge.
        $this->assertStringContainsString('2', (string) $progress[0]->note);
    }
}
