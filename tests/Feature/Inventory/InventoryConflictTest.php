<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Integration\ExternalConflictStatus;
use App\Enums\Inventory\StockState;
use App\Enums\User\{Permission as P, UserRole};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Integration\PendingExternalConflict;
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleRegistry;
use App\Services\Inventory\Contracts\ArticleConflictHandler;
use App\Services\Inventory\{InventoryConflictResolver, InventoryLedger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeArticleConflictHandler;
use Tests\TestCase;

/**
 * MVP-072: Auflösung kompensationspflichtiger Inventory-Outbox-Konflikte —
 * lokal belassen oder per Gegenbuchung ausgleichen. Seit der vierten
 * Entscheidungsrunde (2026-10-06): Reiter mit Zähler, Rechte je Art und drei
 * Wege für Artikelkonflikte (behalten, Fremdstand übernehmen, verwerfen).
 */
final class InventoryConflictTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryLedger $ledger;
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->ledger = app(InventoryLedger::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
        FakeArticleConflictHandler::reset();
    }

    private function bookedMovement(): StockMovement {
        return $this->ledger->receipt($this->variant, $this->warehouse, '50');
    }

    private function conflictFor(StockMovement $movement): PendingExternalConflict {
        return PendingExternalConflict::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl',
            'conflict_type' => 'inventory_outbox',
            'referenceable_type' => $movement->getMorphClass(),
            'referenceable_id' => $movement->id,
            'local_snapshot' => ['qty_base' => $movement->qty_base, 'stock_state' => $movement->stock_state->value],
            'remote_snapshot' => [],
            'status' => ExternalConflictStatus::Open,
        ]);
    }

    private function physical(): string {
        return $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical);
    }

    public function test_compensate_reverses_movement_and_closes_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $this->assertSame('50.0000', $this->physical());

        app(InventoryConflictResolver::class)->compensate($conflict, null);

        $this->assertSame('0.0000', $this->physical()); // Gegenbuchung hebt auf
        $this->assertSame(ExternalConflictStatus::Compensated, $conflict->fresh()->status);
    }

    public function test_keep_local_closes_conflict_without_reversal(): void {
        $conflict = $this->conflictFor($this->bookedMovement());

        app(InventoryConflictResolver::class)->keepLocal($conflict, null);

        $this->assertSame('50.0000', $this->physical()); // Bestand unverändert
        $this->assertSame(ExternalConflictStatus::ResolvedLocal, $conflict->fresh()->status);
    }

    public function test_compensate_rejects_already_resolved_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $conflict->forceFill(['status' => ExternalConflictStatus::Compensated])->save();

        $this->expectException(RuntimeException::class);
        app(InventoryConflictResolver::class)->compensate($conflict, null);
    }

    public function test_compensate_route_books_counter_movement(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $conflict = $this->conflictFor($this->bookedMovement());

        $this->actingAs($admin)
            ->post(route('inventory.conflicts.compensate', $conflict))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('0.0000', $this->physical());
        $this->assertSame(ExternalConflictStatus::Compensated, $conflict->fresh()->status);
    }

    /** Die Liste verkettete den Status früher mit dem Textschlüssel und wählte den Ton über Zeichenketten. */
    public function test_index_shows_label_and_tone_per_status(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $open = $this->conflictFor($this->bookedMovement());
        $compensated = $this->conflictFor($this->bookedMovement());
        $compensated->forceFill(['status' => ExternalConflictStatus::Compensated])->save();
        $kept = $this->conflictFor($this->bookedMovement());
        $kept->forceFill(['status' => ExternalConflictStatus::ResolvedLocal])->save();

        $html = (string) $this->actingAs($admin)
            ->get(route('inventory.conflicts.index', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/badge-warning"[^>]*>\s*Offen\s*</', $html);
        $this->assertMatchesRegularExpression('/badge-info"[^>]*>\s*Kompensiert\s*</', $html);
        $this->assertMatchesRegularExpression('/badge-success"[^>]*>\s*Lokal belassen\s*</', $html);
        // Auflösen bietet nur der offene Konflikt an.
        $this->assertSame(1, substr_count($html, route('inventory.conflicts.compensate', $open)));
        $this->assertStringNotContainsString(route('inventory.conflicts.compensate', $compensated), $html);
    }

    public function test_keep_local_rejects_already_resolved_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $conflict->forceFill(['status' => ExternalConflictStatus::ResolvedLocal])->save();

        $this->expectException(RuntimeException::class);
        app(InventoryConflictResolver::class)->keepLocal($conflict, null);
    }

    // ── Artikelkonflikte der Fremdsysteme (Entscheidung 2026-10-05) ──────

    /** @param array<string, mixed> $overrides */
    private function articleConflict(array $overrides = []): PendingExternalConflict {
        return PendingExternalConflict::query()->create($overrides + [
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'conflict_type' => PendingExternalConflict::TYPE_ARTICLE,
            'referenceable_type' => 'lexoffice_articles',
            'referenceable_id' => 4711,
            'external_id' => 'lex-8',
            'local_snapshot' => ['name' => 'Wartung lokal', 'article_number' => 'A-100', 'net_unit_price' => '100.0000', 'currency' => 'EUR'],
            'remote_snapshot' => ['name' => 'Wartung Lexware', 'article_number' => 'A-100', 'net_unit_price' => '120.0000', 'currency' => 'EUR', 'external_version' => 2],
            'diff_fields' => ['name', 'net_unit_price'],
            'status' => ExternalConflictStatus::Open,
        ]);
    }

    private function admin(): User {
        return User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** Vorher filterte die Liste auf `inventory_outbox` — Artikelkonflikte sah niemand. */
    public function test_article_conflict_is_listed_with_the_article_and_both_states(): void {
        $conflict = $this->articleConflict();

        $response = $this->actingAs($this->admin())->get(route('inventory.conflicts.index'))->assertOk();

        $this->assertSame([$conflict->id], $response->viewData('conflicts')->pluck('id')->all());
        $response->assertSee('Wartung lokal')
            ->assertSee('A-100')
            ->assertSee('Bezeichnung:')
            ->assertSee('lokal „Wartung lokal“ · Fremdsystem „Wartung Lexware“')
            ->assertSee('Nettopreis:')
            ->assertSee('lokal „100.0000“ · Fremdsystem „120.0000“')
            ->assertSee('action="' . route('inventory.conflicts.dismiss', $conflict) . '"', false)
            ->assertSee('action="' . route('inventory.conflicts.keep-local', $conflict) . '"', false)
            ->assertSee('action="' . route('inventory.conflicts.adopt-remote', $conflict) . '"', false)
            // Die Aktion nennt das meldende Plugin beim Namen, nicht bei der Kennung.
            ->assertSee('Lexoffice-Stand übernehmen')
            // Die Gegenbuchung gibt es nur für Lagerbewegungen.
            ->assertDontSee(route('inventory.conflicts.compensate', $conflict), false);
    }

    /** Altbestand hielt Preis und Steuersatz als serialisiertes Wertobjekt; fehlende Felder bleiben ein Strich. */
    public function test_article_conflict_reads_old_snapshots_and_missing_fields(): void {
        $this->articleConflict([
            'local_snapshot' => ['name' => 'Alt', 'net_unit_price' => ['amount' => '100.0000', 'currency' => 'EUR'], 'vat_rate' => ['value' => '19.00', 'scale' => 2]],
            'remote_snapshot' => ['name' => 'Alt', 'net_unit_price' => '120', 'vat_rate' => '7', 'gtin' => '4006381333931'],
            'diff_fields' => ['net_unit_price', 'vat_rate', 'gtin', 'unbekanntes_feld'],
        ]);

        $this->actingAs($this->admin())->get(route('inventory.conflicts.index'))->assertOk()
            ->assertSee('lokal „100.0000“ · Fremdsystem „120“')
            ->assertSee('lokal „19.00“ · Fremdsystem „7“')
            ->assertSee('GTIN:')
            ->assertSee('lokal „—“ · Fremdsystem „4006381333931“')
            ->assertSee('unbekanntes_feld:');
    }

    public function test_type_filter_narrows_the_list_and_survives_the_status_tabs(): void {
        $admin = $this->admin();
        $stock = $this->conflictFor($this->bookedMovement());
        $article = $this->articleConflict();
        $ids = fn (array $query): array => $this->actingAs($admin)->get(route('inventory.conflicts.index', $query))->assertOk()
            ->viewData('conflicts')->pluck('id')->sort()->values()->all();

        $this->assertSame([$stock->id, $article->id], $ids([]));
        $this->assertSame([$article->id], $ids(['type' => PendingExternalConflict::TYPE_ARTICLE]));
        $this->assertSame([$stock->id], $ids(['type' => PendingExternalConflict::TYPE_INVENTORY_OUTBOX]));
        // Unbekannte Art: kein Filter statt leerer Liste; andere Konfliktarten zeigt die Liste nie.
        PendingExternalConflict::query()->create(['conflict_type' => 'contact', 'referenceable_type' => 'customers', 'referenceable_id' => 1] + $article->only(['organization_id', 'plugin_id', 'local_snapshot', 'remote_snapshot', 'status']));
        $this->assertSame([$stock->id, $article->id], $ids(['type' => 'contact']));

        $article->forceFill(['status' => ExternalConflictStatus::Dismissed])->save();
        $this->assertSame([], $ids(['type' => PendingExternalConflict::TYPE_ARTICLE]));
        $this->assertSame([$article->id], $ids(['type' => PendingExternalConflict::TYPE_ARTICLE, 'status' => 'all']));

        $html = (string) $this->actingAs($admin)->get(route('inventory.conflicts.index', ['type' => PendingExternalConflict::TYPE_ARTICLE]))->getContent();
        $this->assertStringContainsString(e(route('inventory.conflicts.index', ['status' => 'all', 'type' => PendingExternalConflict::TYPE_ARTICLE])), $html);
        $this->assertMatchesRegularExpression('/<option value="article"\s+selected/', $html);
    }

    public function test_dismissing_an_article_conflict_closes_it_with_person_time_and_audit(): void {
        $this->travelTo('2026-10-05 12:00:00');
        // Buchhaltung trägt article.manage, aber kein Lagerrecht — Artikelkonflikte löst das Artikelrecht.
        $lead = $this->userWithRole(UserRole::Buchhaltung->value);
        $conflict = $this->articleConflict();

        $this->actingAs($lead)->post(route('inventory.conflicts.dismiss', $conflict))
            ->assertRedirect(route('inventory.conflicts.index'))
            ->assertSessionHas('success');

        $conflict->refresh();
        $this->assertSame(ExternalConflictStatus::Dismissed, $conflict->status);
        $this->assertSame($lead->id, $conflict->resolved_by);
        $this->assertTrue(now()->equalTo($conflict->resolved_at));

        $audit = AuditLog::query()->where('event', 'integration.conflict_resolved')->sole();
        $this->assertSame($lead->id, $audit->user_id);
        $this->assertSame($this->organization->id, $audit->organization_id);
        $this->assertSame($conflict->id, (int) $audit->auditable_id);
        $this->assertEquals(
            ['status' => 'dismissed', 'conflict_type' => 'article', 'plugin_id' => 'lexoffice', 'external_id' => 'lex-8', 'diff_fields' => ['name', 'net_unit_price']],
            $audit->changes,
        );
        $this->assertSame('Fremdsystem-Konflikt aufgelöst', __('audit-events.integration.conflict_resolved'));

        // Die offene Liste zeigt ihn nicht mehr, ein zweiter Aufruf ändert nichts.
        $this->actingAs($lead)->get(route('inventory.conflicts.index'))->assertOk()->assertDontSee('Wartung lokal');
        $this->travel(5)->minutes();
        $this->actingAs($lead)->post(route('inventory.conflicts.dismiss', $conflict))->assertSessionHas('error');
        $this->assertTrue($conflict->resolved_at->equalTo($conflict->fresh()->resolved_at));
        $this->assertSame(1, AuditLog::query()->where('event', 'integration.conflict_resolved')->count());
    }

    public function test_dismissing_needs_the_article_right_and_the_own_organization(): void {
        $conflict = $this->articleConflict();
        $reader = $this->userWithPermissions(P::ArticleViewAny);

        $this->actingAs($reader)->get(route('inventory.conflicts.index'))->assertOk()
            ->assertSee('Wartung lokal')
            ->assertDontSee(route('inventory.conflicts.dismiss', $conflict), false)
            ->assertDontSee(route('inventory.conflicts.adopt-remote', $conflict), false);
        $this->actingAs($reader)->post(route('inventory.conflicts.dismiss', $conflict))->assertForbidden();
        $this->assertSame(ExternalConflictStatus::Open, $conflict->fresh()->status);

        // Fremde Organisation: weder in der Liste noch adressierbar.
        $foreign = $this->articleConflict(['organization_id' => Organization::factory()->create()->id, 'local_snapshot' => ['name' => 'Fremder Artikel']]);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('inventory.conflicts.index', ['status' => 'all']))->assertOk()
            ->assertSee('Wartung lokal')
            ->assertDontSee('Fremder Artikel');
        $this->actingAs($admin)->post(route('inventory.conflicts.dismiss', $foreign))->assertNotFound();
        $this->assertSame(ExternalConflictStatus::Open, PendingExternalConflict::query()->withoutGlobalScopes()->findOrFail($foreign->id)->status);
        $this->assertSame(0, AuditLog::query()->where('event', 'integration.conflict_resolved')->count());
    }

    /** Jede Art hat ihre Wege: verwerfen und Fremdstand übernehmen nur Artikel, ausgleichen nur Lagerbewegungen; belassen können beide. */
    public function test_resolutions_stay_with_their_conflict_type(): void {
        $admin = $this->admin();
        $stock = $this->conflictFor($this->bookedMovement());
        $article = $this->articleConflict();

        $this->actingAs($admin)->post(route('inventory.conflicts.dismiss', $stock))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('inventory.conflicts.adopt-remote', $stock))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('inventory.conflicts.compensate', $article))->assertSessionHas('error');

        $this->assertSame(ExternalConflictStatus::Open, $stock->fresh()->status);
        $this->assertSame(ExternalConflictStatus::Open, $article->fresh()->status);
        $this->assertSame('50.0000', $this->physical());
    }

    public function test_stock_resolutions_are_audited_like_the_dismissal(): void {
        $admin = $this->admin();
        $kept = $this->conflictFor($this->bookedMovement());
        $compensated = $this->conflictFor($this->bookedMovement());

        $this->actingAs($admin)->post(route('inventory.conflicts.keep-local', $kept))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('inventory.conflicts.compensate', $compensated))->assertSessionHasNoErrors();

        $statuses = AuditLog::query()->where('event', 'integration.conflict_resolved')->orderBy('id')->get()
            ->mapWithKeys(fn (AuditLog $log): array => [(int) $log->auditable_id => $log->changes['status']])->all();
        $this->assertSame([$kept->id => 'resolved_local', $compensated->id => 'compensated'], $statuses);
    }

    // ── Reiter, Rechte je Art und Artikelwege (Entscheidung 2026-10-06) ──

    private function userWithPermissions(P ...$permissions): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        foreach ($permissions as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
            $user->givePermissionTo($permission->value);
        }

        return $user;
    }

    private function contributeFakeHandler(string $pluginId = 'acme'): void {
        FakeArticleConflictHandler::$pluginId = $pluginId;
        app(ModuleRegistry::class)->contribute(ArticleConflictHandler::class, FakeArticleConflictHandler::class);
    }

    /** Der Reiter zählt nur offene Konflikte; ohne offene gibt es keinen Zähler. */
    public function test_inventory_tabs_show_the_conflicts_tab_with_the_open_count(): void {
        $admin = $this->admin();
        $this->conflictFor($this->bookedMovement());
        $this->articleConflict(['status' => ExternalConflictStatus::Dismissed]);

        $html = (string) $this->actingAs($admin)->get(route('inventory.stock'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('inventory.conflicts.index'), '#') . '"[^>]*>\s*<span[^>]*>difference</span>\s*Konflikte\s*<span class="badge badge-sm ml-2">1</span>#', $html);

        $html = (string) $this->actingAs($admin)->get(route('inventory.conflicts.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('inventory.conflicts.index'), '#') . '"\s+class="tab gap-1 tab-active"#', $html);

        PendingExternalConflict::query()->update(['status' => ExternalConflictStatus::Dismissed->value]);
        $html = (string) $this->actingAs($admin)->get(route('inventory.stock'))->assertOk()->getContent();
        $this->assertStringContainsString('Konflikte', $html);
        $this->assertStringNotContainsString('badge badge-sm ml-2', $html);
    }

    /** Ohne Lagerrecht zeigt die Liste nur Artikelkonflikte — und der Reiter zählt nur diese. */
    public function test_user_with_only_the_article_right_sees_only_article_conflicts(): void {
        $stock = $this->conflictFor($this->bookedMovement());
        $article = $this->articleConflict();
        $reader = $this->userWithPermissions(P::ArticleViewAny);

        $response = $this->actingAs($reader)->get(route('inventory.conflicts.index'))->assertOk();
        $this->assertSame([$article->id], $response->viewData('conflicts')->pluck('id')->all());
        $this->assertSame([PendingExternalConflict::TYPE_ARTICLE], $response->viewData('types'));
        $response->assertSee('Wartung lokal')
            ->assertDontSee('#' . $stock->referenceable_id)
            // Die Lager-Reiter führen auf gesperrte Seiten und bleiben weg; der Konflikte-Reiter zählt 1.
            ->assertDontSee('href="' . route('inventory.stock') . '"', false)
            ->assertSee('<span class="badge badge-sm ml-2">1</span>', false);

        // Der Art-Filter kann nicht mehr zeigen als das Recht erlaubt.
        $this->assertSame(
            [$article->id],
            $this->actingAs($reader)->get(route('inventory.conflicts.index', ['type' => PendingExternalConflict::TYPE_INVENTORY_OUTBOX]))->assertOk()->viewData('conflicts')->pluck('id')->all(),
        );

        // Ohne Artikelrecht umgekehrt nur Bestand.
        $stockReader = $this->userWithPermissions(P::InventoryViewAny);
        $response = $this->actingAs($stockReader)->get(route('inventory.conflicts.index'))->assertOk();
        $this->assertSame([$stock->id], $response->viewData('conflicts')->pluck('id')->all());
        $response->assertDontSee('Wartung lokal');
    }

    public function test_user_without_either_right_cannot_open_the_list(): void {
        $this->articleConflict();
        $nobody = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($nobody)->get(route('inventory.conflicts.index'))->assertForbidden();
    }

    /** Artikelwege brauchen article.manage — das Buchungsrecht allein reicht nicht, und umgekehrt. */
    public function test_resolving_needs_the_right_of_the_conflict_type(): void {
        $this->contributeFakeHandler();
        $stock = $this->conflictFor($this->bookedMovement());
        $article = $this->articleConflict(['plugin_id' => 'acme']);

        $poster = $this->userWithPermissions(P::InventoryViewAny, P::InventoryPost, P::ArticleViewAny);
        $this->actingAs($poster)->get(route('inventory.conflicts.index'))->assertOk()
            ->assertSee('action="' . route('inventory.conflicts.compensate', $stock) . '"', false)
            ->assertDontSee(route('inventory.conflicts.adopt-remote', $article), false);
        $this->actingAs($poster)->post(route('inventory.conflicts.dismiss', $article))->assertForbidden();
        $this->actingAs($poster)->post(route('inventory.conflicts.keep-local', $article))->assertForbidden();
        $this->actingAs($poster)->post(route('inventory.conflicts.adopt-remote', $article))->assertForbidden();

        $manager = $this->userWithPermissions(P::InventoryViewAny, P::ArticleViewAny, P::ArticleManage);
        $this->actingAs($manager)->get(route('inventory.conflicts.index'))->assertOk()
            ->assertSee('action="' . route('inventory.conflicts.adopt-remote', $article) . '"', false)
            ->assertDontSee(route('inventory.conflicts.compensate', $stock), false);
        $this->actingAs($manager)->post(route('inventory.conflicts.compensate', $stock))->assertForbidden();
        $this->actingAs($manager)->post(route('inventory.conflicts.keep-local', $stock))->assertForbidden();

        $this->assertSame(ExternalConflictStatus::Open, $stock->fresh()->status);
        $this->assertSame(ExternalConflictStatus::Open, $article->fresh()->status);
        $this->assertSame([], FakeArticleConflictHandler::$adopted);
        $this->assertSame('50.0000', $this->physical());
    }

    public function test_adopting_remote_runs_the_plugin_handler_and_closes_with_audit(): void {
        $this->travelTo('2026-10-06 09:30:00');
        $this->contributeFakeHandler();
        $manager = $this->userWithRole(UserRole::Buchhaltung->value);
        $conflict = $this->articleConflict(['plugin_id' => 'acme']);

        $this->actingAs($manager)->post(route('inventory.conflicts.adopt-remote', $conflict))
            ->assertRedirect(route('inventory.conflicts.index'))
            ->assertSessionHas('success', 'Konflikt geschlossen — Stand des Fremdsystems übernommen.');

        $this->assertSame([$conflict->id], FakeArticleConflictHandler::$adopted);
        $conflict->refresh();
        $this->assertSame(ExternalConflictStatus::ResolvedRemote, $conflict->status);
        $this->assertSame($manager->id, $conflict->resolved_by);
        $this->assertTrue(now()->equalTo($conflict->resolved_at));
        $audit = AuditLog::query()->where('event', 'integration.conflict_resolved')->sole();
        $this->assertSame('resolved_remote', $audit->changes['status']);
        $this->assertSame('acme', $audit->changes['plugin_id']);

        // Erledigt: ein zweiter Aufruf erreicht das Plugin nicht mehr.
        $this->actingAs($manager)->post(route('inventory.conflicts.adopt-remote', $conflict))->assertSessionHas('error');
        $this->assertSame([$conflict->id], FakeArticleConflictHandler::$adopted);
    }

    /** Scheitert das Fremdsystem oder fehlt sein Plugin, bleibt der Konflikt offen und nichts wird protokolliert. */
    public function test_adopting_remote_without_a_handler_or_with_a_failing_one_keeps_the_conflict_open(): void {
        $manager = $this->userWithRole(UserRole::Buchhaltung->value);
        $orphan = $this->articleConflict(['plugin_id' => 'jtl']);

        $this->actingAs($manager)->post(route('inventory.conflicts.adopt-remote', $orphan))
            ->assertRedirect(route('inventory.conflicts.index'))
            ->assertSessionHas('error', 'Für Konflikte des Plugins „jtl“ steht kein Abgleich bereit — ist das Plugin aktiviert?');

        // Ein Fehler des Fremdsystems erreicht die Oberfläche als Meldung (ErrorText redigiert fremde Texte).
        $this->contributeFakeHandler();
        FakeArticleConflictHandler::$failure = new RuntimeException('Fremdsystem antwortet nicht.');
        $failing = $this->articleConflict(['plugin_id' => 'acme']);
        $this->actingAs($manager)->post(route('inventory.conflicts.adopt-remote', $failing))
            ->assertRedirect(route('inventory.conflicts.index'))
            ->assertSessionHas('error');

        $this->assertSame(ExternalConflictStatus::Open, $orphan->fresh()->status);
        $this->assertSame(ExternalConflictStatus::Open, $failing->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('event', 'integration.conflict_resolved')->count());
    }

    public function test_keeping_local_for_an_article_closes_without_touching_the_plugin(): void {
        $this->contributeFakeHandler();
        $manager = $this->userWithRole(UserRole::Buchhaltung->value);
        $conflict = $this->articleConflict(['plugin_id' => 'acme']);

        $this->actingAs($manager)->from(route('inventory.conflicts.index'))->post(route('inventory.conflicts.keep-local', $conflict))
            ->assertRedirect(route('inventory.conflicts.index'))
            ->assertSessionHas('success', 'Konflikt geschlossen — lokaler Artikelstand beibehalten; er wird beim nächsten Abgleich übertragen.');

        $conflict->refresh();
        $this->assertSame(ExternalConflictStatus::ResolvedLocal, $conflict->status);
        $this->assertSame($manager->id, $conflict->resolved_by);
        $this->assertSame([], FakeArticleConflictHandler::$adopted);
        $this->assertSame('resolved_local', AuditLog::query()->where('event', 'integration.conflict_resolved')->sole()->changes['status']);
    }
}
