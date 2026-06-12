<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRequirementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\{ControlImplementationStatus, RequirementSource};
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsRequirement, IsmsScope};
use App\Models\{Organization, User};
use App\Services\Isms\RequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anforderungen + SoA-Aussagen (Feature 044/046): Normkatalog-Import über
 * die Normprofil-Registry (profile-Pflichtparameter, idempotent, kein
 * Überschreiben, Statements je gewähltem Scope), Scope-/Norm-Filter auf
 * der Listenseite und im SoA, ensureStatementsForScope, SoA-Regel am
 * Statement, eigene Anforderungen, Mandantengrenze.
 */
class IsmsRequirementTest extends TestCase {
    use RefreshDatabase;

    public function test_catalog_import_creates_requirements_and_statements_for_default_scope(): void {
        $admin = User::factory()->admin()->create();

        $this->importCatalog($admin)->assertRedirect();

        app()->instance('currentOrganization', $admin->organization);
        // 27 HLS-Hauptkapitel + 93 Annex-A-Referenzen.
        $this->assertSame(120, IsmsRequirement::query()->count());
        $this->assertSame(
            120,
            IsmsRequirement::query()->where('source', RequirementSource::Catalog->value)->count(),
        );
        $this->assertSame(120, IsmsApplicabilityStatement::query()->count(), 'Je Anforderung ein Statement im Default-Scope');

        /** @var IsmsScope $scope */
        $scope = IsmsScope::query()->where('is_default', true)->firstOrFail();
        $this->assertSame((int) $admin->organization_id, (int) $scope->organization_id);

        $this->assertDatabaseHas('isms_requirements', [
            'organization_id' => $admin->organization_id,
            'norm' => 'ISO/IEC 27001',
            'edition' => '2022',
            'ref_no' => 'A.5.1',
        ]);
        $this->assertDatabaseHas('isms_requirements', ['ref_no' => 'A.8.34']);
        $this->assertDatabaseHas('isms_requirements', [
            'norm' => 'ISO/IEC 27001',
            'ref_no' => '4.1',
        ]);
    }

    public function test_catalog_import_requires_valid_profile(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.requirements.index'))
            ->post(route('isms.requirements.import'))
            ->assertRedirect(route('isms.requirements.index'))
            ->assertSessionHasErrors('profile');

        $this->actingAs($admin)
            ->from(route('isms.requirements.index'))
            ->post(route('isms.requirements.import'), ['profile' => 'iso99999-0000'])
            ->assertRedirect(route('isms.requirements.index'))
            ->assertSessionHasErrors('profile');

        app()->instance('currentOrganization', $admin->organization);
        $this->assertSame(0, IsmsRequirement::query()->count());
    }

    public function test_catalog_import_is_idempotent(): void {
        $admin = User::factory()->admin()->create();

        $this->importCatalog($admin)->assertRedirect();
        $this->importCatalog($admin)->assertRedirect();

        app()->instance('currentOrganization', $admin->organization);
        $this->assertSame(120, IsmsRequirement::query()->count(), 'Doppelter Import legt keine Duplikate an');
        $this->assertSame(120, IsmsApplicabilityStatement::query()->count());
        $this->assertSame(1, IsmsScope::query()->where('is_default', true)->count(), 'Genau ein Default-Scope');
    }

    public function test_each_norm_profile_imports_idempotently(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $expected = [
            'iso27001-2022' => 120,
            'iso27701-2025' => 27,
            'iso9001-2015' => 27,
            'iso22301-2019' => 27,
            'iso45001-2018' => 27,
            'iso37301-2021' => 27,
            'iso42001-2023' => 27,
        ];

        $total = 0;
        foreach ($expected as $profile => $count) {
            $this->importCatalog($admin, $profile)->assertRedirect();
            $this->importCatalog($admin, $profile)->assertRedirect();
            $total += $count;

            $this->assertSame($total, IsmsRequirement::query()->count(), "Profil {$profile}: idempotenter Import");
        }

        $this->assertSame($total, IsmsApplicabilityStatement::query()->count());
        $this->assertDatabaseHas('isms_requirements', ['norm' => 'ISO 9001', 'edition' => '2015', 'ref_no' => '10.2']);
        $this->assertDatabaseHas('isms_requirements', ['norm' => 'ISO/IEC 42001', 'edition' => '2023', 'ref_no' => '4.3']);
    }

    public function test_import_into_second_scope_creates_only_statements_no_duplicate_requirements(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $this->importCatalog($admin)->assertRedirect();

        $second = IsmsScope::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Rechenzentrum Süd',
        ]);

        $this->importCatalog($admin, 'iso27001-2022', $second)->assertRedirect(
            route('isms.requirements.index', ['scope' => $second->sqid]),
        );

        $this->assertSame(120, IsmsRequirement::query()->count(), 'Keine doppelten Requirements je org+norm+edition+ref_no');
        $this->assertSame(240, IsmsApplicabilityStatement::query()->count(), 'Statements existieren je Scope');
        $this->assertSame(120, IsmsApplicabilityStatement::query()->where('isms_scope_id', $second->id)->count());
    }

    public function test_ensure_statements_for_scope_is_idempotent_and_supports_norm_filter(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $this->importCatalog($admin)->assertRedirect();
        $this->importCatalog($admin, 'iso9001-2015')->assertRedirect();

        $second = IsmsScope::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Standort Nord',
        ]);

        // Nur die gewählte Norm …
        $this->actingAs($admin)
            ->post(route('isms.statements.ensure', $second), ['norm' => 'ISO 9001|2015'])
            ->assertRedirect(route('isms.requirements.index', ['scope' => $second->sqid, 'norm' => 'ISO 9001|2015']));
        $this->assertSame(27, IsmsApplicabilityStatement::query()->where('isms_scope_id', $second->id)->count());

        // … dann alle; doppelt ausgeführt bleibt es idempotent.
        $this->actingAs($admin)
            ->post(route('isms.statements.ensure', $second), ['norm' => 'all'])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('isms.statements.ensure', $second), ['norm' => 'all'])
            ->assertRedirect();
        $this->assertSame(147, IsmsApplicabilityStatement::query()->where('isms_scope_id', $second->id)->count());

        // Service direkt: keine weiteren Statements nötig.
        $service = app(RequirementService::class);
        $this->assertSame(0, $service->ensureStatementsForScope($second));
    }

    public function test_index_supports_scope_switch_and_norm_filter(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $this->importCatalog($admin)->assertRedirect();
        $this->importCatalog($admin, 'iso9001-2015')->assertRedirect();

        $second = IsmsScope::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Werk Ost',
        ]);

        // Norm-Filter: nur ISO-9001-Anforderungen sichtbar.
        $response = $this->actingAs($admin)
            ->get(route('isms.requirements.index', ['norm' => 'ISO 9001|2015']))
            ->assertOk();
        $requirements = $response->viewData('requirements');
        $this->assertSame(27, $requirements->count());
        $this->assertTrue($requirements->every(fn(IsmsRequirement $r): bool => $r->norm === 'ISO 9001'));

        // Scope-Wechsel: zweiter Scope ohne Statements ⇒ Seite bietet das
        // Anlegen an (missingStatements) und zeigt keine Statements.
        $response = $this->actingAs($admin)
            ->get(route('isms.requirements.index', ['scope' => $second->sqid]))
            ->assertOk()
            ->assertSee($second->name);
        $this->assertTrue($response->viewData('missingStatements'));
        $this->assertSame(0, $response->viewData('statements')->count());
        $this->assertTrue($second->is($response->viewData('scope')));

        // Ungültiger Scope-Param fällt auf den Default-Scope zurück.
        $response = $this->actingAs($admin)
            ->get(route('isms.requirements.index', ['scope' => 'ungueltig']))
            ->assertOk();
        $this->assertTrue((bool) $response->viewData('scope')->is_default);
    }

    public function test_soa_respects_scope_param_and_norm_filter(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $this->importCatalog($admin)->assertRedirect();
        $this->importCatalog($admin, 'iso9001-2015')->assertRedirect();

        $second = IsmsScope::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'SoA-Zweitscope',
        ]);
        $this->actingAs($admin)
            ->post(route('isms.statements.ensure', $second), ['norm' => 'ISO 9001|2015'])
            ->assertRedirect();

        // Default-Scope: alle 147 Aussagen.
        $response = $this->actingAs($admin)->get(route('isms.soa'))->assertOk();
        $this->assertSame(147, $response->viewData('statements')->count());

        // Zweiter Scope (Dialog) + Norm-Filter (Druckansicht).
        $response = $this->actingAs($admin)
            ->get(route('isms.soa', ['scope' => $second->sqid]))
            ->assertOk()
            ->assertSee('SoA-Zweitscope');
        $this->assertSame(27, $response->viewData('statements')->count());

        $response = $this->actingAs($admin)
            ->get(route('isms.soa', ['print' => 1, 'norm' => 'ISO 9001|2015']))
            ->assertOk()
            ->assertSee(__('isms.soa.heading'));
        $this->assertSame(27, $response->viewData('statements')->count());
        $this->assertSame('ISO 9001:2015', $response->viewData('normLabel'));
    }

    public function test_soa_with_foreign_scope_sqid_falls_back_to_own_default_scope(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-soa-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);

        app()->instance('currentOrganization', $otherAdmin->organization);
        $foreignScope = IsmsScope::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremder Geltungsbereich',
        ]);

        app()->instance('currentOrganization', $admin->organization);
        $this->importCatalog($admin, 'iso9001-2015')->assertRedirect();

        $response = $this->actingAs($admin)
            ->get(route('isms.soa', ['scope' => $foreignScope->sqid]))
            ->assertOk()
            ->assertDontSee('Fremder Geltungsbereich');
        $this->assertTrue((bool) $response->viewData('scope')->is_default, 'Fremder Scope fällt auf eigenen Default-Scope zurück');

        $response = $this->actingAs($admin)
            ->get(route('isms.requirements.index', ['scope' => $foreignScope->sqid]))
            ->assertOk()
            ->assertDontSee('Fremder Geltungsbereich');
        $this->assertTrue((bool) $response->viewData('scope')->is_default);
    }

    public function test_ensure_statements_for_foreign_scope_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-ensure-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);

        app()->instance('currentOrganization', $otherAdmin->organization);
        $foreignScope = IsmsScope::factory()->create(['organization_id' => $otherOrg->id]);

        app()->instance('currentOrganization', $admin->organization);
        $this->actingAs($admin)
            ->post(route('isms.statements.ensure', $foreignScope), ['norm' => 'all'])
            ->assertNotFound();
    }

    public function test_catalog_import_does_not_overwrite_maintained_statements(): void {
        $admin = User::factory()->admin()->create();

        $this->importCatalog($admin)->assertRedirect();

        app()->instance('currentOrganization', $admin->organization);
        /** @var IsmsRequirement $requirement */
        $requirement = IsmsRequirement::query()->where('ref_no', 'A.8.4')->firstOrFail();
        /** @var IsmsApplicabilityStatement $statement */
        $statement = $requirement->statements()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('isms.statements.update', $statement), [
                'applicable' => '0',
                'justification' => 'Kein Zugriff auf Quellcode nötig.',
                'implementation_status' => 'open',
            ])
            ->assertRedirect();

        $this->importCatalog($admin)->assertRedirect();

        $statement->refresh();
        $this->assertFalse($statement->applicable, 'Re-Import überschreibt gepflegte SoA-Aussage nicht');
        $this->assertSame('Kein Zugriff auf Quellcode nötig.', $statement->justification);
    }

    public function test_not_applicable_without_justification_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $statement = $this->makeStatement($admin);

        $this->actingAs($admin)
            ->from(route('isms.requirements.index'))
            ->put(route('isms.statements.update', $statement), [
                'applicable' => '0',
                'justification' => '',
                'implementation_status' => 'open',
            ])
            ->assertRedirect(route('isms.requirements.index'))
            ->assertSessionHasErrors('justification');

        $this->assertTrue($statement->refresh()->applicable, 'Statement bleibt unverändert anwendbar');
    }

    public function test_not_applicable_with_justification_forces_status_not_applicable(): void {
        $admin = User::factory()->admin()->create();
        $statement = $this->makeStatement($admin);

        $this->actingAs($admin)
            ->put(route('isms.statements.update', $statement), [
                'applicable' => '0',
                'justification' => 'Kein eigener Quellcode — Entwicklung ist vollständig ausgelagert.',
                'implementation_status' => 'open',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $statement->refresh();
        $this->assertFalse($statement->applicable);
        $this->assertSame(ControlImplementationStatus::NotApplicable, $statement->implementation_status);
        $this->assertNotNull($statement->justification);
    }

    public function test_reactivating_statement_resets_not_applicable_status(): void {
        $admin = User::factory()->admin()->create();
        $statement = $this->makeStatement($admin, notApplicable: true);

        $this->actingAs($admin)
            ->put(route('isms.statements.update', $statement), [
                'applicable' => '1',
                'justification' => $statement->justification,
                'implementation_status' => 'notApplicable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $statement->refresh();
        $this->assertTrue($statement->applicable);
        $this->assertSame(ControlImplementationStatus::Open, $statement->implementation_status);
    }

    public function test_custom_requirement_create_creates_statement_and_catalog_refs_stay_immutable(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)
            ->post(route('isms.requirements.store'), [
                'norm' => 'Eigene',
                'edition' => '-',
                'ref_no' => 'M-01',
                'title' => 'Notfallübung jährlich',
            ])
            ->assertRedirect();

        /** @var IsmsRequirement $requirement */
        $requirement = IsmsRequirement::query()->where('ref_no', 'M-01')->firstOrFail();
        $this->assertSame(RequirementSource::Custom, $requirement->source);
        $this->assertSame(1, $requirement->statements()->count(), 'Statement im Default-Scope wird mit angelegt');

        // Katalog-Anforderung: Norm/Edition/Ref-Nr. bleiben unveränderlich.
        $catalog = IsmsRequirement::factory()->catalog('A.5.1')->create(['organization_id' => $admin->organization_id]);
        $this->actingAs($admin)
            ->put(route('isms.requirements.update', $catalog), [
                'ref_no' => 'A.99.99',
                'title' => 'Eigener Kurztitel',
            ])
            ->assertRedirect();

        $catalog->refresh();
        $this->assertSame('A.5.1', $catalog->ref_no, 'Referenz bleibt unveränderlich');
        $this->assertSame('Eigener Kurztitel', $catalog->title, 'Nur der Kurztitel ist pflegbar');
    }

    public function test_regular_user_cannot_access_requirements_or_import(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('isms.requirements.index'))->assertForbidden();
        $this->actingAs($user)->post(route('isms.requirements.import'), ['profile' => 'iso27001-2022'])->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_import_or_edit_statements(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();

        $this->actingAs($gf)->get(route('isms.requirements.index'))->assertOk();
        $this->actingAs($gf)->post(route('isms.requirements.import'), ['profile' => 'iso27001-2022'])->assertForbidden();

        $admin = User::factory()->admin()->create(['organization_id' => $gf->organization_id]);
        $statement = $this->makeStatement($admin);

        $this->actingAs($gf)
            ->put(route('isms.statements.update', $statement), [
                'applicable' => '1',
                'implementation_status' => 'implemented',
            ])
            ->assertForbidden();

        $scope = IsmsScope::query()->where('is_default', true)->firstOrFail();
        $this->actingAs($gf)
            ->post(route('isms.statements.ensure', $scope), ['norm' => 'all'])
            ->assertForbidden();
    }

    public function test_cross_organization_requirement_and_statement_are_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-req-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreignStatement = $this->makeStatement($otherAdmin);
        $foreignRequirement = $foreignStatement->requirement;

        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)
            ->put(route('isms.requirements.update', $foreignRequirement), [
                'norm' => 'Eigene',
                'edition' => '-',
                'ref_no' => $foreignRequirement->ref_no,
                'title' => 'Hijack',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('isms.statements.update', $foreignStatement), [
                'applicable' => '1',
                'implementation_status' => 'implemented',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijack', $foreignRequirement->refresh()->title);
        $this->assertNotSame(
            ControlImplementationStatus::Implemented,
            $foreignStatement->refresh()->implementation_status,
        );
    }

    /**
     * Normkatalog-Import über die Route (profile-Pflichtparameter; optional
     * gewählter Scope).
     */
    private function importCatalog(User $actor, string $profile = 'iso27001-2022', ?IsmsScope $scope = null): \Illuminate\Testing\TestResponse {
        $payload = ['profile' => $profile];
        if ($scope !== null) {
            $payload['scope'] = $scope->sqid;
        }

        return $this->actingAs($actor)->post(route('isms.requirements.import'), $payload);
    }

    /**
     * Anforderung + Statement im Default-Scope der Organisation des Users.
     */
    private function makeStatement(User $owner, bool $notApplicable = false): IsmsApplicabilityStatement {
        app()->instance('currentOrganization', $owner->organization);

        $scope = IsmsScope::query()->firstOrCreate(
            ['organization_id' => $owner->organization_id, 'is_default' => true],
            ['name' => 'Gesamtorganisation'],
        );

        $requirement = IsmsRequirement::factory()->create(['organization_id' => $owner->organization_id]);

        $factory = IsmsApplicabilityStatement::factory();
        if ($notApplicable) {
            $factory = $factory->notApplicable();
        }

        return $factory->create([
            'organization_id' => $owner->organization_id,
            'isms_scope_id' => $scope->id,
            'isms_requirement_id' => $requirement->id,
        ])->load('requirement');
    }
}
