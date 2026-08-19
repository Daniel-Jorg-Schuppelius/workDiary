<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediumTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Enums\Access\AccessMediumStatus;
use App\Enums\Task\TaskStatus;
use App\Models\{AccessMedium, Organization, Task, User};
use App\Services\Access\AccessMediumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zutrittsmedien Stufe 1 (Feature 092, MVP-657–659).
 *
 * Kern der Prüfung: **Eine Verlustmeldung hinterlässt zwingend eine
 * Sperr-Aufgabe**, deren Erledigung der Sperr-Nachweis ist; die Mediennummer
 * existiert nur als Hash; jedes Medium hat genau einen Status.
 */
final class AccessMediumTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function medium(array $attributes = []): AccessMedium {
        return AccessMedium::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'type' => 'transponder',
            'number_hash' => AccessMedium::hashNumber('TRX-0001-9876'),
            'number_suffix' => '9876',
            'label' => 'Haupteingang',
            'system_name' => 'Salto KS',
            'status' => AccessMediumStatus::InStock->value,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_number_is_stored_hashed_only(): void {
        $this->actingAs($this->admin)->post(route('access-media.store'), [
            'type' => 'card',
            'number' => 'CARD-4711-0042',
        ])->assertRedirect();

        $medium = AccessMedium::query()->firstOrFail();
        $this->assertSame('0042', $medium->number_suffix);
        $this->assertNotSame('CARD-4711-0042', $medium->number_hash);
        $this->assertSame(AccessMedium::hashNumber('CARD-4711-0042'), $medium->number_hash);
    }

    /** Dieselbe Nummer darf je Organisation nur einmal existieren. */
    public function test_duplicate_number_is_rejected(): void {
        $this->medium(['number_hash' => AccessMedium::hashNumber('CARD-1')]);

        $this->actingAs($this->admin)->post(route('access-media.store'), [
            'type' => 'card',
            'number' => 'CARD-1',
        ])->assertStatus(422);
    }

    public function test_issue_and_take_back_write_the_history(): void {
        $medium = $this->medium();
        $service = app(AccessMediumService::class);

        $service->issue($medium, ['holder_name' => 'Fensterputz Blank', 'holder_company' => 'Blank GmbH'], $this->admin);
        $this->assertSame(AccessMediumStatus::Issued, $medium->fresh()?->status);
        $this->assertSame('Fensterputz Blank · Blank GmbH', $medium->fresh()?->holderDisplay());

        $service->takeBack($medium->fresh(), $this->admin, 'unbeschädigt');
        $fresh = $medium->fresh();
        $this->assertSame(AccessMediumStatus::InStock, $fresh?->status);
        $this->assertNull($fresh?->holder_name);
        $this->assertSame(2, $medium->handovers()->count());
    }

    /** Ohne Inhaber keine Ausgabe. */
    public function test_issue_without_holder_is_refused(): void {
        $this->expectException(\RuntimeException::class);
        app(AccessMediumService::class)->issue($this->medium(), [], $this->admin);
    }

    /** Der Kern: Verlust erzeugt zwingend die Sperr-Aufgabe. */
    public function test_loss_report_creates_the_blocking_task(): void {
        $medium = $this->medium();

        $task = app(AccessMediumService::class)->reportLost($medium, $this->admin, 'im Zug liegen gelassen');

        $fresh = $medium->fresh();
        $this->assertSame(AccessMediumStatus::Lost, $fresh?->status);
        $this->assertSame($task->id, $fresh?->block_task_id);
        $this->assertStringContainsString('9876', $task->title);
        $this->assertStringContainsString('Salto KS', $task->title);
        $this->assertNotNull($task->due_date);
    }

    /** Der Sperr-Nachweis erledigt die Aufgabe und setzt blocked. */
    public function test_confirm_blocked_completes_the_task(): void {
        $medium = $this->medium();
        app(AccessMediumService::class)->reportLost($medium, $this->admin);

        app(AccessMediumService::class)->confirmBlocked($medium->fresh(), $this->admin);

        $fresh = $medium->fresh();
        $this->assertSame(AccessMediumStatus::Blocked, $fresh?->status);
        $this->assertNotNull($fresh?->blocked_at);
        $this->assertSame(TaskStatus::Done, Task::query()->findOrFail($fresh?->block_task_id)->status);
    }

    /** Ein ausgegebenes Medium lässt sich nicht ausmustern. */
    public function test_issued_medium_cannot_be_retired(): void {
        $medium = $this->medium();
        app(AccessMediumService::class)->issue($medium, ['holder_user_id' => $this->admin->id], $this->admin);

        $this->expectException(\RuntimeException::class);
        app(AccessMediumService::class)->retire($medium->fresh(), $this->admin);
    }

    public function test_foreign_organization_media_are_invisible(): void {
        $other = Organization::factory()->create();
        AccessMedium::query()->create([
            'organization_id' => $other->id,
            'type' => 'card',
            'number_hash' => AccessMedium::hashNumber('FREMD-1'),
            'number_suffix' => 'MD-1',
            'label' => 'Fremdmedium',
            'status' => AccessMediumStatus::InStock->value,
        ]);
        $this->medium();

        $this->actingAs($this->admin)
            ->get(route('access-media.index'))
            ->assertOk()
            ->assertSee('Haupteingang')
            ->assertDontSee('Fremdmedium');
    }

    public function test_plain_user_without_asset_rights_is_denied(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('access-media.index'))->assertForbidden();
    }

    /** Folgepunkt 092: Die Ausgabe hält den Unterschrifts-Verweis fest. */
    public function test_issue_stores_the_signature_reference(): void {
        $medium = $this->medium();
        $holder = User::factory()->create(['organization_id' => $this->organization->id]);

        $handover = app(\App\Services\Access\AccessMediumService::class)->issue($medium, [
            'holder_user_id' => $holder->id,
            'signature_token' => 'sig-4711',
        ], $this->admin);

        $this->assertSame('sig-4711', $handover->signature_token);
    }

    /** Folgepunkt 092 (Offboarding): Wer geht, gibt erst ab. */
    public function test_member_removal_is_blocked_while_media_are_issued(): void {
        $medium = $this->medium();
        $holder = User::factory()->create(['organization_id' => $this->organization->id]);
        app(\App\Services\Access\AccessMediumService::class)->issue($medium, [
            'holder_user_id' => $holder->id,
        ], $this->admin);

        $this->actingAs($this->admin)
            ->delete(route('org.members.destroy', ['member' => $holder]))
            ->assertRedirect();

        $this->assertNotNull($holder->fresh());

        // Nach der Rücknahme ist der Weg frei.
        app(\App\Services\Access\AccessMediumService::class)->takeBack($medium->fresh(), $this->admin);
        $this->actingAs($this->admin)
            ->delete(route('org.members.destroy', ['member' => $holder]))
            ->assertRedirect();
        $this->assertNull(User::query()->find($holder->id));
    }
}
