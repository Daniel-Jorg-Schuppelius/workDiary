<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemValidatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolItemResult, ProtocolItemType, ProtocolType};
use App\Exceptions\ProtocolValidationException;
use App\Models\{DiaryEntry, OpenIssue, Protocol, ProtocolItem, User};
use App\Services\Protocol\{ProtocolItemValidator, ProtocolService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolItemValidatorTest extends TestCase {
    use RefreshDatabase;

    private ProtocolService $service;
    private ProtocolItemValidator $validator;

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(ProtocolService::class);
        $this->validator = app(ProtocolItemValidator::class);
    }

    public function test_boolean_derives_ok_for_true(): void {
        $item = $this->itemOfType(ProtocolItemType::Boolean, ['value' => true]);
        $this->assertSame(ProtocolItemResult::Ok, $this->validator->deriveResult($item));
        $this->assertSame([], $this->validator->validate($item));
    }

    public function test_boolean_derives_notok_for_false(): void {
        $item = $this->itemOfType(ProtocolItemType::Boolean, ['value' => false]);
        $this->assertSame(ProtocolItemResult::NotOk, $this->validator->deriveResult($item));
    }

    public function test_number_within_tolerance_is_ok(): void {
        $item = $this->itemOfType(ProtocolItemType::Number, [
            'value' => 12.0, 'unit' => 'bar',
            'tolerance_min' => 10, 'tolerance_max' => 14,
        ]);
        $this->assertSame(ProtocolItemResult::Ok, $this->validator->deriveResult($item));
        $this->assertSame([], $this->validator->validate($item));
    }

    public function test_number_out_of_tolerance_is_notok(): void {
        $item = $this->itemOfType(ProtocolItemType::Number, [
            'value' => 20.0, 'tolerance_min' => 10, 'tolerance_max' => 14,
        ]);
        $this->assertSame(ProtocolItemResult::NotOk, $this->validator->deriveResult($item));
    }

    public function test_number_min_max_validation(): void {
        $item = $this->itemOfType(ProtocolItemType::Number, [
            'value' => 5, 'min' => 10,
        ]);
        $errors = $this->validator->validate($item);
        $this->assertNotEmpty($errors);
    }

    public function test_choice_invalid_when_not_in_options(): void {
        $item = $this->itemOfType(ProtocolItemType::Choice, [
            'selected' => 'x',
            'options' => [['key' => 'a', 'label' => 'A']],
        ]);
        $this->assertNotEmpty($this->validator->validate($item));
    }

    public function test_multichoice_requires_array(): void {
        $item = $this->itemOfType(ProtocolItemType::Multichoice, ['selected' => 'x']);
        $this->assertNotEmpty($this->validator->validate($item));
    }

    public function test_text_min_length(): void {
        $item = $this->itemOfType(ProtocolItemType::Text, [
            'text' => 'hi', 'min_length' => 5,
        ]);
        $this->assertNotEmpty($this->validator->validate($item));
    }

    public function test_date_invalid(): void {
        $item = $this->itemOfType(ProtocolItemType::Date, ['value' => 'no-date']);
        $this->assertNotEmpty($this->validator->validate($item));
    }

    public function test_photo_requires_attachment_ids(): void {
        $item = $this->itemOfType(ProtocolItemType::Photo, ['attachment_ids' => [1, 2], 'min_count' => 3]);
        $errors = $this->validator->validate($item);
        $this->assertNotEmpty($errors);
    }

    public function test_defect_requires_severity_and_description(): void {
        $item = $this->itemOfType(ProtocolItemType::Defect, ['severity' => 'bogus', 'description' => 'x']);
        $errors = $this->validator->validate($item);
        $this->assertNotEmpty($errors);
    }

    public function test_measurement_requires_samples(): void {
        $item = $this->itemOfType(ProtocolItemType::MeasurementTimestamped, ['samples' => 'nope']);
        $this->assertNotEmpty($this->validator->validate($item));
    }

    public function test_required_item_unfilled_blocks(): void {
        $item = $this->itemOfType(ProtocolItemType::Text, null, required: true);
        $this->assertNotEmpty($this->validator->validate($item));
    }

    public function test_group_skips_validation(): void {
        $item = $this->itemOfType(ProtocolItemType::Group, null, required: true);
        $this->assertSame([], $this->validator->validate($item));
    }

    public function test_request_review_blocks_when_required_unfilled(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'Pflicht-Test',
        ]);
        $this->service->addItem($protocol, $creator, [
            'label' => 'Pflicht',
            'item_type' => ProtocolItemType::Text->value,
            'required' => true,
        ]);

        $this->expectException(ProtocolValidationException::class);
        $this->service->requestReview($protocol->refresh(), $creator);
    }

    public function test_defect_item_creates_open_issue_on_fill(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Defect->value,
            'title' => 'Mangelaufnahme',
        ]);
        $item = $this->service->addItem($protocol, $creator, [
            'label' => 'Leckage Heizraum',
            'item_type' => ProtocolItemType::Defect->value,
        ]);

        $filled = $this->service->fillItem($item, $creator, [
            'value_json' => [
                'severity' => 'high',
                'description' => 'Wasser tritt am Vorlauf aus',
            ],
        ]);

        $this->assertSame(ProtocolItemResult::NotOk, $filled->result);
        $this->assertIsInt($filled->value_json['open_issue_id'] ?? null);
        $this->assertNotNull(OpenIssue::query()->find($filled->value_json['open_issue_id']));
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private function itemOfType(ProtocolItemType $type, ?array $value, bool $required = false): ProtocolItem {
        [$creator, $entry] = $this->makeContext();
        $protocol = Protocol::factory()
            ->for($entry, 'subject')
            ->state([
                'created_by_user_id' => $creator->id,
                'organization_id' => $creator->organization_id,
            ])
            ->create();

        return ProtocolItem::query()->create([
            'protocol_id' => $protocol->id,
            'sort_order' => 0,
            'item_type' => $type->value,
            'label' => 'X',
            'required' => $required,
            'value_json' => $value,
        ]);
    }

    /**
     * @return array{0: User, 1: DiaryEntry}
     */
    private function makeContext(): array {
        $creator = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($creator)->create();

        return [$creator, $entry];
    }
}
