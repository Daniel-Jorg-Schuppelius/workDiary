<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderProcedureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\Applications\TenderProcedureType;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\User;
use ERechnungToolkit\Enums\GaebAwardCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vergabevorgang (MVP-625): Vergabestelle, Verfahrensart, Ort und Fristen.
 *
 * Die Verfahrensart folgt dem **Recht**, nicht dem Format: Unter- und
 * oberschwellig gelten verschiedene Regelwerke, und die UVgO kennt Arten, die
 * GAEB nicht hat.
 */
final class TenderProcedureTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * Die Schwellenwertlage entscheidet, welche Verfahren zulässig sind — eine
     * Öffentliche Ausschreibung ist kein Offenes Verfahren, sondern deren
     * unterschwellige Entsprechung.
     */
    public function test_threshold_separates_the_two_bodies_of_law(): void {
        $below = TenderProcedureType::forThreshold(false);
        $above = TenderProcedureType::forThreshold(true);

        $this->assertContains(TenderProcedureType::PublicInvitation, $below);
        $this->assertContains(TenderProcedureType::OpenProcedure, $above);
        $this->assertNotContains(TenderProcedureType::OpenProcedure, $below);

        // Die UVgO-Arten gibt es nur unterschwellig.
        $this->assertContains(TenderProcedureType::DirectOrder, $below);
        $this->assertContains(TenderProcedureType::NegotiatedAward, $below);
    }

    /**
     * GAEB ist VOB-zentriert: Verhandlungsvergabe und Direktauftrag stammen aus
     * der UVgO und haben dort keine Entsprechung. Sie zu verbiegen wäre
     * schlimmer, als das Feld leer zu lassen.
     */
    public function test_uvgo_procedures_have_no_gaeb_counterpart(): void {
        $this->assertNull(TenderProcedureType::NegotiatedAward->toGaeb());
        $this->assertNull(TenderProcedureType::DirectOrder->toGaeb());

        $this->assertSame(GaebAwardCategory::OpenProcedure, TenderProcedureType::OpenProcedure->toGaeb());
        $this->assertSame(GaebAwardCategory::PublicInvitation, TenderProcedureType::PublicInvitation->toGaeb());
    }

    /** „Mit Teilnahmewettbewerb" ist eine eigene Art, kein Zusatz. */
    public function test_call_for_participation_is_part_of_the_procedure(): void {
        $this->assertFalse(TenderProcedureType::RestrictedInvitation->hasCallForParticipation());
        $this->assertTrue(TenderProcedureType::RestrictedInvitationWithCall->hasCallForParticipation());
        $this->assertTrue(TenderProcedureType::CompetitiveDialogue->hasCallForParticipation());
    }

    /** Ein Vergabevorgang lässt sich mit allen Angaben anlegen. */
    public function test_tender_can_be_stored_with_procedure_details(): void {
        $this->actingAs($this->admin)->post(route('tenders.store'), [
            'title' => 'Neubau Kita — Rohbau',
            'kind' => 'tender',
            'awarding_body' => 'Stadt Bonn, Amt 65',
            'procedure_no' => 'VG-2026-0815',
            'procedure_type' => TenderProcedureType::PublicInvitation->value,
            'above_threshold' => '0',
            'lot_no' => '3',
            'cpv_codes' => '45210000-2, 45262500-6',
            'nuts_code' => 'DEA22',
            'platform' => 'Deutsches Vergabeportal',
            'notice_url' => 'https://example.org/bekanntmachung/815',
            'participation_deadline' => '2026-09-01',
            'submission_deadline' => '2026-09-15',
            'binding_until' => '2026-11-15',
        ])->assertRedirect();

        $tender = ApplicationOpportunity::query()->firstOrFail();

        $this->assertSame('Stadt Bonn, Amt 65', $tender->awarding_body);
        $this->assertSame(TenderProcedureType::PublicInvitation, $tender->procedure_type);
        $this->assertFalse($tender->above_threshold);
        // Die Komma-Liste wird zu einer Liste, nicht zu einem Text.
        $this->assertSame(['45210000-2', '45262500-6'], $tender->cpv_codes);
        $this->assertSame('DEA22', $tender->nuts_code);
        $this->assertSame('2026-11-15', $tender->binding_until?->toDateString());
    }

    /** Ein CPV-Code hat acht Stellen — alles andere wird abgewiesen. */
    public function test_malformed_cpv_code_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('tenders.store'), [
            'title' => 'Test',
            'kind' => 'tender',
            'cpv_codes' => 'abc',
        ])->assertSessionHasErrors('cpv_codes.0');
    }

    /**
     * Der Fristenwächter (MVP-626) meldet die Angebotsfrist im Vorlauf. Anders
     * als die meisten Fristen ist sie eine **Ausschlussfrist**: Danach ist die
     * Teilnahme vorbei.
     */
    public function test_deadline_scanner_reports_the_submission_deadline(): void {
        ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Neubau Kita — Rohbau',
            'kind' => 'tender',
            'status' => 'in_progress',
            'submission_deadline' => now()->addDays(2)->toDateString(),
            'responsible_user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        \Illuminate\Support\Facades\Notification::fake();
        $this->artisan('notifications:scan-deadlines')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->admin,
            \App\Notifications\GenericEventNotification::class,
            fn ($notification): bool => str_contains(
                (string) ($notification->toArray($this->admin)['message_key'] ?? ''),
                'tender_submission_due_soon'
            )
        );
    }

    /**
     * Die Bindefrist läuft umgekehrt: Nach ihr ist der **Bieter** frei, das
     * Angebot also nicht mehr verbindlich. Sie braucht einen eigenen Lauf,
     * weil sie weder „fällig" noch „überfällig" im üblichen Sinn ist.
     */
    public function test_binding_period_is_watched_separately(): void {
        ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Sanierung Turnhalle',
            'kind' => 'tender',
            'status' => 'submitted',
            'binding_until' => now()->addDays(3)->toDateString(),
            'responsible_user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        \Illuminate\Support\Facades\Notification::fake();
        $this->artisan('notifications:scan-deadlines')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $this->admin,
            \App\Notifications\GenericEventNotification::class,
            fn ($notification): bool => str_contains(
                (string) ($notification->toArray($this->admin)['message_key'] ?? ''),
                'tender_binding_expiring'
            )
        );
    }

    /**
     * Der Zuschlag kommt als Datei, nicht als Klick (MVP-628): Eine importierte
     * **Auftragserteilung** (X86) schließt den Vergabevorgang, an dem das
     * Leistungsverzeichnis hängt.
     */
    public function test_awarded_x86_import_wins_the_tender(): void {
        $boq = \App\Models\BillOfQuantity::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rohbau',
            'status' => 'draft',
        ]);
        $tender = ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Neubau Kita',
            'kind' => 'tender',
            'status' => 'submitted',
            'bill_of_quantity_id' => $boq->id,
            'created_by' => $this->admin->id,
        ]);

        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA86/3.3">
          <GAEBInfo><Version>3.3</Version><VersDate>2021-05</VersDate><Date>2026-08-17</Date></GAEBInfo>
          <PrjInfo><NamePrj>Neubau Kita</NamePrj></PrjInfo>
          <Award><DP>86</DP><AwardInfo><Cur>EUR</Cur></AwardInfo>
            <BoQ ID="B1"><BoQInfo><Name>Rohbau</Name><LblBoQ>Rohbau</LblBoQ><OutlCompl>OutTxt</OutlCompl>
              <BoQBkdn><Type>Item</Type><Length>4</Length><Num>Yes</Num></BoQBkdn></BoQInfo>
              <BoQBody><Itemlist><Item ID="I1" RNoPart="0010"><Qty>10</Qty><QU>m3</QU>
                <Description><OutlineText><OutlTxt><TextOutlTxt><span>Aushub</span></TextOutlTxt></OutlTxt></OutlineText></Description>
              </Item></Itemlist></BoQBody></BoQ></Award>
        </GAEB>
        XML;

        app(\App\Services\Gaeb\GaebImportService::class)->import(
            $xml,
            'zuschlag.x86',
            $this->organization->id,
            ['bill_of_quantity_id' => $boq->id, 'created_by' => $this->admin->id],
        );

        $this->assertSame('won', $tender->fresh()->status);
    }

    /** Die Detailansicht zeigt das Verfahren, sobald eines erfasst ist. */
    public function test_show_page_displays_the_procedure(): void {
        $tender = ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Neubau Kita',
            'kind' => 'tender',
            'status' => 'captured',
            'awarding_body' => 'Stadt Bonn',
            'procedure_type' => TenderProcedureType::RestrictedInvitationWithCall,
            'above_threshold' => false,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('tenders.show', $tender))
            ->assertOk()
            ->assertSee('Stadt Bonn')
            ->assertSee(TenderProcedureType::RestrictedInvitationWithCall->label());
    }
}
