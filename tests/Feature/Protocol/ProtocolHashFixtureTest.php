<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolHashFixtureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolItemType, ProtocolType};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Protocol\{Protocol, ProtocolItem};
use App\Services\Protocol\ProtocolHasher;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Eingefrorener Protokoll-Hash (MVP-867): ein Protokoll mit allen 16
 * Punktarten und festen `value_json`-Werten muss vor und nach der Umstellung
 * auf den Feldschema-Baustein dieselbe Kanonik und denselben SHA-256 liefern.
 * Neu einfrieren nur bewusst: PROTOCOL_HASH_RECORD=1.
 */
class ProtocolHashFixtureTest extends TestCase {
    use RefreshDatabase;

    private const FIXTURE = __DIR__ . '/../../Fixtures/protocols/hash-all-item-types.json';

    public function test_canonical_form_and_hash_of_all_item_types_are_frozen(): void {
        $creator = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($creator)->create();
        $protocol = Protocol::factory()->for($entry, 'subject')->state([
            'created_by_user_id' => $creator->id,
            'organization_id' => $creator->organization_id,
            'type' => ProtocolType::Acceptance->value,
            'title' => 'Abnahme Heizungsanlage',
            'description' => 'Fixture MVP-867',
            'occurred_at' => Carbon::parse('2026-09-24 10:00:00', 'UTC'),
            'revision' => 1,
            'state_initial' => null,
            'state_final' => null,
        ])->create();
        // Subjekt-ID deterministisch: Autoinkremente laufen je Worker weiter.
        $protocol->forceFill(['subject_id' => 4711])->save();

        foreach ($this->items() as $index => [$type, $value]) {
            ProtocolItem::query()->create([
                'protocol_id' => $protocol->id,
                'sort_order' => $index,
                'item_type' => $type->value,
                'label' => 'Punkt ' . ($index + 1) . ' ' . $type->value,
                'description' => $index % 2 === 0 ? 'Beschreibung ' . $index : null,
                'required' => $index % 3 === 0,
                'value_json' => $value,
                'note' => $index === 5 ? 'Notiz' : null,
            ]);
        }

        $canonical = app(ProtocolHasher::class)->canonicalize($protocol->fresh(['items']) ?? $protocol);
        $actual = ['sha256' => hash('sha256', $canonical), 'canonical' => $canonical];

        if (getenv('PROTOCOL_HASH_RECORD') === '1' || ! is_file(self::FIXTURE)) {
            file_put_contents(self::FIXTURE, JsonHelper::encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->markTestIncomplete('Hash-Fixture neu aufgezeichnet: ' . $actual['sha256']);
        }

        $expected = JsonHelper::decode((string) file_get_contents(self::FIXTURE), true);
        $this->assertSame($expected['canonical'], $canonical, 'Kanonische Form eines Protokolls hat sich geändert.');
        $this->assertSame($expected['sha256'], $actual['sha256']);
    }

    /** @return list<array{0: ProtocolItemType, 1: array<string, mixed>|null}> */
    private function items(): array {
        return [
            [ProtocolItemType::Group, null],
            [ProtocolItemType::Text, ['text' => 'Alles dicht', 'min_length' => 3]],
            [ProtocolItemType::Boolean, ['value' => true]],
            [ProtocolItemType::Choice, ['selected' => 'b', 'options' => [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B']], 'result_map' => ['a' => 'ok', 'b' => 'notok']]],
            [ProtocolItemType::Multichoice, ['selected' => ['x', 'z'], 'options' => [['key' => 'x', 'label' => 'X'], ['key' => 'y', 'label' => 'Y'], ['key' => 'z', 'label' => 'Z']]]],
            [ProtocolItemType::Number, ['value' => 12.5, 'unit' => 'bar', 'min' => 0, 'max' => 20, 'tolerance_min' => 10, 'tolerance_max' => 14]],
            [ProtocolItemType::Range, ['value' => 21, 'tolerance_min' => 18, 'tolerance_max' => 24]],
            [ProtocolItemType::Date, ['value' => '2026-09-24']],
            [ProtocolItemType::DateTime, ['value' => '2026-09-24T10:30:00+02:00']],
            [ProtocolItemType::Signature, ['signature_id' => 77]],
            [ProtocolItemType::Photo, ['attachment_ids' => [1, 2], 'min_count' => 1, 'min_per_phase' => ['before' => 1]]],
            [ProtocolItemType::File, ['attachment_ids' => [3]]],
            [ProtocolItemType::Defect, ['severity' => 'high', 'description' => 'Wasser tritt am Vorlauf aus', 'category' => 'leak', 'open_issue_id' => 9]],
            [ProtocolItemType::MeasurementTimestamped, ['samples' => [['value' => 1.5, 'at' => '2026-09-24T10:00:00+02:00'], ['value' => 1.7, 'at' => '2026-09-24T10:05:00+02:00']]]],
            [ProtocolItemType::ProcedureStep, ['procedure_run_id' => 5]],
            [ProtocolItemType::SignoffInternal, ['value' => true, 'user_id' => 3]],
        ];
    }
}
