<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseFileSectionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Timeline;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\ProtocolStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\Asset\{Asset, AssetAssignment};
use App\Models\Attachments\{Attachment, AttachmentConfirmation};
use App\Models\Classification\Tag;
use App\Models\Communication\{Comment, CommunicationNote};
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Document\Document;
use App\Models\Material\MaterialUsage;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Protocol\Protocol;
use App\Models\Time\{TimeEntry, Timesheet};
use App\Models\Weather\WeatherSnapshot;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Dom\HTMLDocument;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die Fallakte hat einen Datenteil für zwei Fassungen (Audit 2026-10, k4-10):
 * `diary/_case_file_sections` rendert den Bildschirm (`$static = false`) und
 * das PDF (`$static = true`). Beide zeigen dieselben Abschnitte und Daten;
 * nur die PDF-Fassung ist statisch.
 */
class CaseFileSectionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private User $colleague;

    /** Sieht alle Zeiten, darf aber weder Auftrag noch Protokoll ändern. */
    private User $reader;

    private DiaryEntry $order;

    private Asset $subjectAsset;

    private Asset $issuedAsset;

    private Protocol $signedProtocol;

    private Protocol $draftProtocol;

    private Attachment $internalAttachment;

    private Attachment $confirmedAttachment;

    private ?string $pdfHtml = null;

    /** @var list<bool> */
    private array $staticFlags = [];

    protected function setUp(): void {
        parent::setUp();

        // Termine und „erstellt am" hängen an der Uhr.
        $this->travelTo('2026-03-10 09:30:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->owner = $this->orgUser(['name' => 'Olga Inhaberin']);
        $this->colleague = $this->orgUser(['name' => 'Kurt Kollege']);
        $this->reader = User::factory()->buchhaltung()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Berta Buchhaltung',
        ]);
        $this->order = $this->orderWithAllSections();

        // Das HTML vor der PDF-Erzeugung, mit den Daten des PDF-Wegs.
        $this->mock(DocumentDesignRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('renderPdf')->andReturnUsing(function (RenderDocumentKind $kind, string $view, array $data): string {
                $this->assertSame(RenderDocumentKind::CaseFile, $kind);
                $this->pdfHtml = View::make($view, $data)->render();

                return '%PDF-1.4';
            });
        });
        View::composer('diary._case_file_sections', function (ViewContract $view): void {
            $this->staticFlags[] = $view->getData()['static'];
        });
    }

    public function test_screen_and_pdf_show_the_same_sections_and_data(): void {
        [$screen, $pdf] = $this->renderBoth($this->reader);

        $this->assertSame([false, true], $this->staticFlags, 'Beide Fassungen binden die gemeinsame Teilvorlage ein.');
        $this->assertStringNotContainsString('<form', $screen, 'Die Vergleichsperson darf nichts ändern — sonst stehen Knöpfe statt Text in den Zellen.');

        $screenDoc = $this->parse($screen);
        $pdfDoc = $this->parse($pdf);
        $sections = $this->sections($pdfDoc);

        $this->assertSame([
            __('timeline.case.master_data'),
            __('timeline.case.times'),
            __('timeline.case.material'),
            __('timeline.case.protocols'),
            __('timeline.case.open_issues'),
            __('timeline.case.assets'),
            __('timeline.case.communication'),
            __('timeline.case.documents'),
            __('timeline.case.attachments'),
            __('timeline.case.comments'),
            __('timeline.title.section'),
        ], array_keys($sections));
        $this->assertSame($this->sections($screenDoc), $sections);

        $this->assertSame(
            $this->words((string) $screenDoc->querySelector('h1')?->textContent),
            $this->words((string) $pdfDoc->querySelector('h1')?->textContent),
        );
        // Kopfzeile: gleich bis auf den Intern-Hinweis der PDF-Fassung.
        $this->assertSame(
            $this->words(__('timeline.case.internal_notice') . ' ' . $screenDoc->querySelector('.meta')?->textContent),
            $this->words((string) $pdfDoc->querySelector('.meta')?->textContent),
        );
    }

    public function test_pdf_variant_is_static_even_for_editors(): void {
        [, $pdf] = $this->renderBoth($this->owner);

        $doc = $this->parse($pdf);

        $interactive = [];
        foreach ($doc->querySelectorAll('a, [href], form, button, input, select, textarea, script, [data-tip]') as $element) {
            $interactive[] = $element->localName;
        }
        $this->assertSame([], $interactive, 'Die PDF-Fassung bleibt ohne Links, Formulare, Skripte und Tooltips.');

        $text = $this->words((string) $doc->body?->textContent);
        $this->assertStringContainsString($this->words(__('timeline.case.internal_notice')), $text);
        // Was am Bildschirm Link, Formular oder Badge ist, steht hier als Text.
        foreach ([
            'Abnahmeprotokoll Heizung',
            'Tagesbericht Entwurf',
            'Heizanlage Keller',
            'Messkoffer 7',
            'internes-foto.jpg ' . $this->owner->name . ' ' . __('Intern'),
            'abnahme-foto.jpg ' . $this->colleague->name . ' ' . __('Freigegeben') . ' ' . __('Vom Kunden bestätigt am :date', ['date' => Carbon::parse('2026-03-08 14:00:00')->fdate()]),
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertStringNotContainsString(__('weather.attach.button'), $text);
    }

    public function test_screen_variant_keeps_actions_links_and_forms(): void {
        [$screen] = $this->renderBoth($this->owner);
        $doc = $this->parse($screen);

        // Aktionsleiste
        $this->assertNotNull($doc->querySelector('.actions.no-print button[data-print]'));
        $this->assertSame(
            [route('diary.case-file.pdf', $this->order), route('diary.show', $this->order)],
            $this->attributes($doc, '.actions a', 'href'),
        );
        $this->assertStringContainsString('window.print', (string) $doc->querySelector('script')?->textContent);

        // Links auf Protokolle und Assets, Glossar-Tooltip an der Überschrift
        $links = $this->attributes($doc, 'table a', 'href');
        $this->assertContains(route('protocols.show', $this->signedProtocol), $links);
        $this->assertContains(route('protocols.show', $this->draftProtocol), $links);
        $this->assertContains(route('assets.show', $this->subjectAsset), $links);
        $this->assertContains(route('assets.show', $this->issuedAsset), $links);
        $this->assertNotNull($doc->querySelector('h2 [data-tip]'));

        // Formulare: Wetter-Abruf nur am Entwurf ohne Messwert, Kundenfreigabe je Anhang
        $this->assertEqualsCanonicalizing([
            route('protocols.weather', $this->draftProtocol),
            route('attachments.customer-visibility', $this->internalAttachment),
            route('attachments.customer-visibility', $this->confirmedAttachment),
        ], $this->attributes($doc, 'form', 'action'));
        $this->assertCount(3, $doc->querySelectorAll('form input[name="_token"]'));
        $this->assertCount(2, $doc->querySelectorAll('form input[name="_method"][value="PATCH"]'));

        $this->assertStringNotContainsString(
            $this->words(__('timeline.case.internal_notice')),
            $this->words((string) $doc->body?->textContent),
        );
    }

    /** @return array{0: string, 1: string} Bildschirm-HTML und PDF-HTML */
    private function renderBoth(User $viewer): array {
        $screen = $this->actingAs($viewer)->get(route('diary.case-file', $this->order))->assertOk();

        $this->pdfHtml = null;
        $this->actingAs($viewer)->get(route('diary.case-file.pdf', $this->order))->assertOk();
        $this->assertNotNull($this->pdfHtml);

        return [(string) $screen->getContent(), $this->pdfHtml];
    }

    private function parse(string $html): HTMLDocument {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    /** @return array<string, list<list<string>>> Überschrift → Zeilen → Zelltexte */
    private function sections(HTMLDocument $doc): array {
        $sections = [];
        foreach ($doc->querySelectorAll('h2') as $heading) {
            $block = $heading->nextElementSibling;
            $rows = [];
            foreach ($block?->querySelectorAll('tr') ?? [] as $row) {
                $cells = [];
                foreach ($row->querySelectorAll('th, td') as $cell) {
                    $cells[] = $this->words((string) $cell->textContent);
                }
                $rows[] = $cells;
            }
            $sections[$this->words((string) $heading->textContent)] = $rows;
        }

        return $sections;
    }

    /** @return list<string> */
    private function attributes(HTMLDocument $doc, string $selector, string $attribute): array {
        $values = [];
        foreach ($doc->querySelectorAll($selector) as $element) {
            $values[] = (string) $element->getAttribute($attribute);
        }

        return $values;
    }

    /** Text ohne Leerraum-Unterschiede und ohne den Trenner „·", den die PDF-Fassung statt des Badges setzt. */
    private function words(string $text): string {
        return trim((string) preg_replace('/[\s·]+/u', ' ', $text));
    }

    private function orderWithAllSections(): DiaryEntry {
        $orgId = (int) $this->organization->id;

        $customer = Customer::factory()->create([
            'organization_id' => $orgId,
            'name' => 'Kunde Musterbau',
            'company' => 'Musterbau GmbH & Co. KG',
            'created_by' => $this->owner->id,
        ]);
        $project = Project::create([
            'organization_id' => $orgId,
            'customer_id' => $customer->id,
            'name' => 'Sanierung Rathaus',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->owner->id,
            'billable' => true,
        ]);
        $this->subjectAsset = Asset::factory()->create(['organization_id' => $orgId, 'name' => 'Heizanlage Keller']);
        $this->issuedAsset = Asset::factory()->create(['organization_id' => $orgId, 'name' => 'Messkoffer 7']);

        $order = DiaryEntry::factory()->for($this->owner)->create([
            'organization_id' => $orgId,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'asset_id' => $this->subjectAsset->id,
            'title' => 'Heizungswartung Gebäude A',
            'content' => "Brenner prüfen\nDüse tauschen",
            'response' => 'Erledigt.',
            'start_at' => '2026-03-02 08:00:00',
            'end_at' => '2026-03-02 12:30:00',
        ]);
        $order->tags()->attach(Tag::create(['name' => 'heizung', 'organization_id' => $orgId])->id);

        // Zeiten und Material
        $timesheet = Timesheet::create([
            'organization_id' => $orgId,
            'project_id' => $project->id,
            'user_id' => $this->owner->id,
            'work_date' => '2026-03-02',
            'status' => TimesheetStatus::Draft->value,
        ]);
        foreach ([[$this->owner, '2026-03-02', 90, $timesheet->id], [$this->colleague, '2026-03-03', 120, null]] as [$user, $date, $minutes, $timesheetId]) {
            TimeEntry::create([
                'organization_id' => $orgId,
                'project_id' => $project->id,
                'diary_entry_id' => $order->id,
                'timesheet_id' => $timesheetId,
                'user_id' => $user->id,
                'date' => $date,
                'minutes' => $minutes,
                'kind' => TimeEntryKind::Work->value,
                'billable' => true,
                'description' => 'Montage',
            ]);
        }
        MaterialUsage::create([
            'organization_id' => $orgId,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kupferrohr 15mm',
            'quantity' => '2.5',
            'unit' => 'm',
        ]);

        // Protokolle: unterschrieben mit Wetter, Entwurf ohne (Abruf-Formular)
        $weather = WeatherSnapshot::create([
            'organization_id' => $orgId,
            'geo_lat' => '52.5200000',
            'geo_lng' => '13.4050000',
            'snapshot_date' => '2026-03-06',
            'provider' => 'open-meteo',
            'fetched_at' => '2026-03-06 18:00:00',
            'temp_min' => 3.5,
            'temp_max' => 11.25,
            'precipitation_mm' => 0.4,
            'wind_gust_kmh' => 38,
            'weather_code' => 3,
            'raw' => ['ok' => true],
            'created_by' => $this->owner->id,
        ]);
        $protocol = [
            'organization_id' => $orgId,
            'subject_type' => $order->getMorphClass(),
            'subject_id' => $order->id,
            'created_by_user_id' => $this->owner->id,
        ];
        $this->signedProtocol = Protocol::factory()->signed()->create($protocol + [
            'title' => 'Abnahmeprotokoll Heizung',
            'occurred_at' => '2026-03-06 10:00:00',
            'weather_snapshot_id' => $weather->id,
        ]);
        $this->signedProtocol->tags()->attach(Tag::create(['name' => 'abnahme', 'organization_id' => $orgId])->id);
        $this->draftProtocol = Protocol::factory()->create($protocol + [
            'title' => 'Tagesbericht Entwurf',
            'occurred_at' => '2026-03-05 09:00:00',
            'status' => ProtocolStatus::Draft->value,
        ]);

        OpenIssue::factory()->create([
            'organization_id' => $orgId,
            'subject_type' => $order->getMorphClass(),
            'subject_id' => $order->id,
            'created_by_user_id' => $this->owner->id,
            'title' => 'Dichtung nachbessern',
        ]);
        AssetAssignment::factory()->create([
            'organization_id' => $orgId,
            'asset_id' => $this->issuedAsset->id,
            'diary_entry_id' => $order->id,
            'assigned_to_user_id' => $this->owner->id,
            'checked_out_at' => '2026-03-01 07:00:00',
        ]);
        CommunicationNote::factory()->for($order, 'notable')->create([
            'organization_id' => $orgId,
            'created_by_user_id' => $this->owner->id,
            'occurred_at' => '2026-03-02 14:00:00',
            'subject' => 'Telefonat Terminabstimmung',
        ]);
        Document::factory()->create([
            'organization_id' => $orgId,
            'documentable_type' => $order->getMorphClass(),
            'documentable_id' => $order->id,
            'created_by_user_id' => $this->owner->id,
            'title' => 'Wartungsvertrag',
        ]);

        // Anhänge: intern sowie freigegeben und vom Kunden bestätigt
        $attachment = [
            'organization_id' => $orgId,
            'attachable_type' => $order->getMorphClass(),
            'attachable_id' => $order->id,
        ];
        $this->internalAttachment = Attachment::factory()->create($attachment + [
            'user_id' => $this->owner->id,
            'original_name' => 'internes-foto.jpg',
            'customer_visible' => false,
            'created_at' => '2026-03-02 10:00:00',
        ]);
        $this->confirmedAttachment = Attachment::factory()->create($attachment + [
            'user_id' => $this->colleague->id,
            'original_name' => 'abnahme-foto.jpg',
            'customer_visible' => true,
            'created_at' => '2026-03-06 10:30:00',
        ]);
        AttachmentConfirmation::create([
            'organization_id' => $orgId,
            'attachment_id' => $this->confirmedAttachment->id,
            'user_id' => $this->colleague->id,
            'confirmed_at' => '2026-03-08 14:00:00',
        ]);

        Comment::factory()->create([
            'commentable_type' => $order->getMorphClass(),
            'commentable_id' => $order->id,
            'user_id' => $this->owner->id,
            'body' => 'Interner Vermerk',
        ]);

        return $order;
    }
}
