<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderProfileTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\{RenderDocumentKind, RenderProfileStatus};
use App\Models\DocumentDesign\{DocumentRenderProfile, DocumentRenderProfileVersion};
use App\Models\{Organization, User};
use App\Services\DocumentDesign\{RenderPreflightService, RenderProfileService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-297/298/300/304: Profilversionierung, Preflight-Gates,
 * Unveränderlichkeit aktivierter Versionen, Zuweisung und Fallback.
 */
class RenderProfileTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeOrgAdmin(): array {
        $org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        return [$org, $admin];
    }

    public function test_profile_creation_starts_with_draft_version_and_safe_defaults(): void {
        [$org, $admin] = $this->makeOrgAdmin();

        $this->actingAs($admin)->post(route('admin.document-design.profiles.store'), [
            'name' => 'Standardbogen',
            'document_kinds' => ['invoice'],
        ])->assertRedirect();

        $profile = DocumentRenderProfile::query()->firstOrFail();
        $this->assertSame(RenderProfileStatus::Draft, $profile->status);
        $version = $profile->versions()->firstOrFail();
        $this->assertTrue($version->isDraft());
        // Standardlayout = heutige Ausgabe: 20-mm-Ränder, kein Fenster.
        $this->assertSame(20.0, (float) $version->layout['content_first']['top']);
        $this->assertNull($version->layout['address_window']);
    }

    public function test_draft_update_rejects_letterhead_declaration_for_dynamic_blocks(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $profile = app(RenderProfileService::class)->createProfile($org, 'P', ['invoice'], false, $admin);

        $response = $this->actingAs($admin)->putJson(
            route('admin.document-design.draft.update', $profile->sqid),
            ['block_rules' => ['totals' => ['state' => 'provided_by_letterhead', 'confirmed' => true]]],
        );

        $response->assertStatus(422);
    }

    public function test_activation_is_blocked_when_mandatory_block_is_not_applicable(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'P', ['invoice'], false, $admin);
        $version = $profile->versions()->firstOrFail();

        // Steuerangaben sind für Rechnungen Pflicht (MVP-298).
        $rules = $version->block_rules;
        $rules['tax_identity'] = ['state' => 'not_applicable'];
        $service->updateDraft($version, ['block_rules' => $rules], $admin);

        $result = $service->activate($version->fresh(), $admin);
        $this->assertFalse($result->ok());
        $this->assertSame('mandatory_missing', $result->errors[0]['code']);
        $this->assertSame(RenderProfileStatus::Draft, $profile->fresh()->status);
    }

    public function test_activation_freezes_version_and_supersedes_previous(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'P', ['invoice'], true, $admin);
        $v1 = $profile->versions()->firstOrFail();

        $this->assertTrue($service->activate($v1, $admin)->ok());
        $this->assertSame(DocumentRenderProfileVersion::STATUS_ACTIVE, $v1->fresh()->status);
        $this->assertSame($v1->id, $profile->fresh()->active_version_id);

        // Aktivierte Version ist unveränderlich (Model-Guard).
        $this->expectException(\RuntimeException::class);
        $frozen = $v1->fresh();
        $frozen->layout = array_merge($frozen->layout, ['blocked_areas' => [['page' => 'all', 'x' => 1, 'y' => 1, 'width' => 5, 'height' => 5, 'label' => '']]]);
        $frozen->save();
    }

    public function test_new_draft_from_superseded_version_is_rollback_path(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'P', ['invoice'], false, $admin);
        $v1 = $profile->versions()->firstOrFail();
        $service->activate($v1, $admin);

        $v2 = $service->newDraftFrom($v1->fresh(), $admin);
        $this->assertSame(2, $v2->version);
        $this->assertTrue($v2->isDraft());
        $service->activate($v2, $admin);
        $this->assertSame(DocumentRenderProfileVersion::STATUS_SUPERSEDED, $v1->fresh()->status);

        // Rollback: alter Stand wird als NEUE Entwurfsversion übernommen.
        $v3 = $service->newDraftFrom($v1->fresh(), $admin);
        $this->assertSame(3, $v3->version);
        $this->assertEquals($v1->layout, $v3->layout);
    }

    public function test_resolve_prefers_kind_profile_over_default(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = app(RenderProfileService::class);

        $default = $service->createProfile($org, 'Default', [], true, $admin);
        $service->activate($default->versions()->firstOrFail(), $admin);

        $invoiceProfile = $service->createProfile($org, 'Rechnungen', ['invoice'], false, $admin);
        $service->activate($invoiceProfile->versions()->firstOrFail(), $admin);

        $resolved = $service->resolveFor($org, RenderDocumentKind::Invoice);
        $this->assertSame($invoiceProfile->fresh()->active_version_id, $resolved?->id);

        // Andere Dokumentart → org-weites Standardprofil.
        $resolvedOther = $service->resolveFor($org, RenderDocumentKind::Protocol);
        $this->assertSame($default->fresh()->active_version_id, $resolvedOther?->id);

        // Fremde Organisation → kein Profil (Systemfallback).
        $other = Organization::factory()->create();
        $this->assertNull($service->resolveFor($other, RenderDocumentKind::Invoice));
    }

    public function test_preflight_reports_contrast_and_blocked_area_overlap(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'P', [], false, $admin);
        $version = $profile->versions()->firstOrFail();

        $layout = $version->layout;
        $layout['blocked_areas'] = [['page' => 'first', 'x' => 100, 'y' => 100, 'width' => 40, 'height' => 40, 'label' => 'Siegel']];
        $service->updateDraft($version, [
            'layout' => $layout,
            'table_style' => ['preset' => 'clear', 'overrides' => ['text_color' => '#fefefe']], // weiß auf weiß
        ], $admin);

        $result = app(RenderPreflightService::class)->check($version->fresh(), []);
        $codes = array_column($result->errors, 'code');
        $this->assertContains('blocked_overlap', $codes);
        $this->assertContains('contrast', $codes);
    }

    public function test_assign_permission_allows_assignment_but_not_asset_upload(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = app(RenderProfileService::class);
        $profile = $service->createProfile($org, 'P', [], false, $admin);

        $assigner = User::factory()->user()->create(['organization_id' => $org->id]);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        \Spatie\Permission\Models\Permission::findOrCreate(\App\Enums\User\Permission::DocumentDesignAssign->value, 'web');
        $assigner->givePermissionTo(\App\Enums\User\Permission::DocumentDesignAssign->value);

        $this->actingAs($assigner)->post(route('admin.document-design.assign', $profile->sqid), [
            'document_kinds' => ['report'],
        ])->assertRedirect();
        $this->assertSame(['report'], $profile->fresh()->document_kinds);

        $this->actingAs($assigner)->post(route('admin.document-design.assets.store'), [
            'name' => 'X',
            'page_role' => 'first',
        ])->assertForbidden();
    }
}
