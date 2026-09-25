<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolCaptureUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolItemType, ProtocolStatus, ProtocolType};
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolItem};
use App\Services\Protocol\Fields\ProtocolItemFields;
use App\Services\Protocol\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-883: Protokolle in der Oberfläche anlegen, Punkte erfassen, abschließen. */
class ProtocolCaptureUiTest extends TestCase {
    use RefreshDatabase;

    private User $user;

    private DiaryEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        $this->user = User::factory()->user()->create();
        $this->entry = DiaryEntry::factory()->for($this->user)->create(['organization_id' => $this->user->organization_id]);
    }

    private function protocol(): Protocol {
        return app(ProtocolService::class)->create($this->entry, $this->user, ['title' => 'Wartung Heizung', 'type' => ProtocolType::Maintenance->value]);
    }

    public function test_create_dialog_and_store_by_sqid_redirect_to_protocol(): void {
        $this->actingAs($this->user)
            ->get(route('diary.show', $this->entry))
            ->assertOk()
            ->assertSee(route('protocols.create', ['subject_kind' => 'diary', 'subject' => $this->entry->sqid]));

        $this->actingAs($this->user)
            ->get(route('protocols.create', ['subject_kind' => 'diary', 'subject' => $this->entry->sqid]))
            ->assertOk()
            ->assertSee('name="subject_id" value="' . $this->entry->sqid . '"', false);

        $response = $this->actingAs($this->user)->post(route('protocols.store'), [
            'subject_kind' => 'diary',
            'subject_id' => $this->entry->sqid,
            'type' => ProtocolType::Acceptance->value,
            'title' => 'Abnahme Bad',
        ]);

        $protocol = Protocol::query()->where('title', 'Abnahme Bad')->firstOrFail();
        $response->assertRedirect(route('protocols.show', $protocol));
        $this->assertSame($this->entry->id, (int) $protocol->subject_id);
    }

    public function test_items_are_added_with_configuration_and_filled_through_the_field_schema(): void {
        $protocol = $this->protocol();
        $this->actingAs($this->user)->get(route('protocols.items.create', $protocol))->assertOk();

        $this->actingAs($this->user)->post(route('protocols.items.store', $protocol), [
            'label' => 'Zustand Brenner',
            'item_type' => ProtocolItemType::Choice->value,
            'options' => "Gut\nVerschlissen\nDefekt",
        ])->assertRedirect();
        $this->actingAs($this->user)->post(route('protocols.items.store', $protocol), [
            'label' => 'Abgastemperatur',
            'item_type' => ProtocolItemType::Number->value,
            'unit' => '°C',
            'min' => '0',
            'max' => '250',
        ])->assertRedirect();

        $choice = ProtocolItem::query()->where('label', 'Zustand Brenner')->firstOrFail();
        $number = ProtocolItem::query()->where('label', 'Abgastemperatur')->firstOrFail();
        $this->assertSame(['gut', 'verschlissen', 'defekt'], array_column($choice->value_json['options'], 'key'));
        $this->assertSame('°C', $number->value_json['unit']);

        $this->actingAs($this->user)->get(route('protocols.items.fill-form', $choice))
            ->assertOk()
            ->assertSee('Verschlissen');

        $this->actingAs($this->user)->put(route('protocols.items.fill', $choice), [
            'values' => [ProtocolItemFields::key($choice) => 'verschlissen'],
            'note' => 'Düse tauschen',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->user)->put(route('protocols.items.fill', $number), [
            'values' => [ProtocolItemFields::key($number) => '182.5'],
        ])->assertSessionHasNoErrors();

        $choice->refresh();
        $number->refresh();
        $this->assertSame('verschlissen', $choice->value_json['selected']);
        $this->assertCount(3, $choice->value_json['options'], 'Konfiguration bleibt beim Ausfüllen erhalten');
        $this->assertSame('Düse tauschen', $choice->note);
        $this->assertSame(182.5, $number->value_json['value']);
        $this->assertSame('°C', $number->value_json['unit']);

        $this->actingAs($this->user)->get(route('protocols.show', $protocol))
            ->assertOk()
            ->assertSee(route('protocols.items.fill-form', $choice), false)
            ->assertSee('Verschlissen');
    }

    public function test_defect_creates_open_issue_and_measurement_appends_samples(): void {
        $protocol = $this->protocol();
        $service = app(ProtocolService::class);
        $defect = $service->addItem($protocol, $this->user, ['label' => 'Mangel', 'item_type' => ProtocolItemType::Defect->value]);
        $series = $service->addItem($protocol, $this->user, ['label' => 'Druck', 'item_type' => ProtocolItemType::MeasurementTimestamped->value]);

        $this->actingAs($this->user)->get(route('protocols.items.fill-form', $defect))->assertOk()->assertSee('name="values[' . ProtocolItemFields::key($defect) . '][severity]"', false);
        $this->actingAs($this->user)->put(route('protocols.items.fill', $defect), [
            'values' => [ProtocolItemFields::key($defect) => ['severity' => 'high', 'description' => 'Ausdehnungsgefäß undicht']],
        ])->assertSessionHasNoErrors();
        foreach (['1.4', '1.6'] as $reading) {
            $this->actingAs($this->user)->put(route('protocols.items.fill', $series), [
                'values' => [ProtocolItemFields::key($series) => $reading],
            ])->assertSessionHasNoErrors();
        }

        $this->assertNotNull($defect->fresh()->value_json['open_issue_id'] ?? null);
        $this->assertSame(1, OpenIssue::query()->where('source_type', 'protocolDefect')->count());
        $this->assertSame([1.4, 1.6], array_column($series->fresh()->value_json['samples'], 'value'));
    }

    public function test_review_can_be_returned_to_draft_and_signed_through_dialogs(): void {
        $protocol = $this->protocol();
        $admin = User::factory()->admin()->create(['organization_id' => $this->user->organization_id]);

        $this->actingAs($this->user)->post(route('protocols.transition', [$protocol, 'requestReview']))->assertSessionHasNoErrors();
        $this->assertSame(ProtocolStatus::InReview, $protocol->fresh()->status);

        $this->actingAs($this->user)->get(route('protocols.transition-form', [$protocol, 'returnToDraft']))->assertOk();
        $this->actingAs($this->user)->post(route('protocols.transition', [$protocol, 'returnToDraft']), ['reason' => 'Foto fehlt'])->assertSessionHasNoErrors();
        $this->assertSame(ProtocolStatus::Draft, $protocol->fresh()->status);

        $this->actingAs($admin)->get(route('protocols.transition-form', [$protocol, 'sign']))->assertOk();
        $this->actingAs($admin)->get(route('protocols.signature-tokens.create', $protocol))->assertOk();
        $this->actingAs($admin)->post(route('protocols.transition', [$protocol, 'sign']), [
            'with_signature' => '1',
            'signature' => ['signer_name' => 'Erika Muster', 'role' => 'contractor', 'method' => 'onscreen'],
        ])->assertSessionHasNoErrors();

        $protocol->refresh();
        $this->assertSame(ProtocolStatus::Signed, $protocol->status);
        $this->assertSame(1, $protocol->signatures()->count());
    }

    public function test_foreign_subject_cannot_open_create_dialog(): void {
        $foreign = DiaryEntry::factory()->create();

        $this->actingAs($this->user)
            ->get(route('protocols.create', ['subject_kind' => 'diary', 'subject' => $foreign->sqid]))
            ->assertNotFound();
    }
}
