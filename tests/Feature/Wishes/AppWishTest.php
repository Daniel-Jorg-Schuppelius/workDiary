<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Wishes;

use App\Enums\User\UserRole;
use App\Enums\Wishes\{AppWishKind, AppWishStatus};
use App\Mail\AppWishVendorMail;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\Wishes\{AppWish, AppWishVote};
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Services\Wishes\{AppWishService, VendorChannel};
use App\Services\Wishes\Demo\WishesDemoBlock;
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wünsche an die App (Feature 206, MVP-1658/1659/1677) — Akzeptanzkriterien:
 * Seite wird mitgenommen und der Wunsch bleibt in der eigenen Organisation,
 * eine Stimme je Person, Weitergabe ohne Namen, Antwort des Betriebs im
 * Wunsch der Organisation, ohne Opt-in verlässt kein Wunsch die Installation.
 */
final class AppWishTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        Mail::fake();
        $this->setUpOrganization(['name' => 'Kanzlei Beispiel']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function worker(array $attributes = []): User {
        return $this->userWithRole(UserRole::User->value, attributes: $attributes);
    }

    private function lead(): User {
        return $this->userWithRole(UserRole::Geschaeftsfuehrung->value, attributes: ['name' => 'Gerda Leitung']);
    }

    private function platformAdmin(): User {
        $platform = Organization::factory()->create(['name' => 'Betrieb']);

        return User::factory()->platformAdmin()->create(['organization_id' => $platform->id, 'name' => 'Paul Plattform', 'email' => 'plattform@example.test']);
    }

    /** Wunsch über die Oberfläche, wie aus der Kopfleiste. */
    private function wish(User $author, string $body = 'Bitte einen Filter nach Kunde im Arbeitsvorrat.', string $route = 'work-queue.index'): AppWish {
        $this->actingAs($author)->post(route('wishes.store'), ['kind' => 'wish', 'body' => $body, 'route_name' => $route])->assertSessionHasNoErrors();

        return AppWish::query()->withoutGlobalScope(OrganizationScope::class)->latest('id')->firstOrFail();
    }

    public function test_a_wish_takes_the_current_page_and_stays_in_its_organization(): void {
        $author = $this->worker(['name' => 'Anna Autorin']);
        $this->actingAs($author)->get(route('work-queue.index'))
            ->assertOk()
            ->assertSee(route('wishes.create', ['route' => 'work-queue.index']), false);
        $this->actingAs($author)->get(route('wishes.create', ['route' => 'work-queue.index', 'dialog' => 1]))
            ->assertOk()
            ->assertSee('name="route_name" value="work-queue.index"', false);

        $wish = $this->wish($author);
        $this->assertSame('work-queue.index', $wish->route_name);
        $this->assertSame(AppWishStatus::New, $wish->status);
        $this->assertSame((int) $this->organization->id, (int) $wish->organization_id);

        $colleague = $this->worker();
        $this->actingAs($colleague)->get(route('wishes.index'))->assertOk()->assertSee('Bitte einen Filter nach Kunde im Arbeitsvorrat.')->assertSee('Anna Autorin');

        $foreign = Organization::factory()->create();
        $stranger = User::factory()->user()->create(['organization_id' => $foreign->id]);
        app()->instance('currentOrganization', $foreign);
        $this->actingAs($stranger)->get(route('wishes.index'))->assertOk()->assertDontSee('Bitte einen Filter nach Kunde im Arbeitsvorrat.');
        $this->actingAs($stranger)->post(route('wishes.vote', $wish))->assertNotFound();
        app()->instance('currentOrganization', $this->organization);

        $this->actingAs($author)->post(route('wishes.store'), ['kind' => 'bug', 'body' => 'Unbekannte Seite', 'route_name' => 'gibt.es.nicht'])->assertSessionHasNoErrors();
        $this->assertNull(AppWish::query()->where('body', 'Unbekannte Seite')->value('route_name'), 'Nur Routennamen der App.');
    }

    public function test_one_vote_per_person_and_never_for_the_own_wish(): void {
        $author = $this->worker();
        $voter = $this->worker();
        $wish = $this->wish($author);

        $this->actingAs($voter)->post(route('wishes.vote', $wish))->assertRedirect();
        $this->actingAs($voter)->post(route('wishes.vote', $wish))->assertRedirect();
        $this->assertSame(1, AppWishVote::query()->where('app_wish_id', $wish->id)->count(), 'Die zweite Stimme zählt nicht.');

        $this->actingAs($author)->post(route('wishes.vote', $wish))->assertForbidden();
        $this->actingAs($voter)->get(route('wishes.index', ['sort' => 'votes', 'dir' => 'desc']))->assertOk()->assertSee(__('wishes.action.unvote'));

        $this->actingAs($voter)->delete(route('wishes.unvote', $wish))->assertRedirect();
        $this->assertSame(0, AppWishVote::query()->where('app_wish_id', $wish->id)->count());
    }

    public function test_the_author_edits_while_new_and_managers_set_status_and_answer(): void {
        $author = $this->worker(['name' => 'Anna Autorin']);
        $voter = $this->worker();
        $other = $this->worker();
        $lead = $this->lead();
        $wish = $this->wish($author);
        $this->assertGreaterThan(0, $lead->fresh()?->notifications()->count(), 'Hinweis an die Bearbeitenden bei neuem Wunsch.');
        $this->assertSame(0, $author->notifications()->count());
        app(AppWishService::class)->vote($wish, $voter);

        $this->actingAs($other)->put(route('wishes.update', $wish), ['kind' => 'wish', 'body' => 'Fremd geändert'])->assertForbidden();
        $this->actingAs($author)->put(route('wishes.update', $wish), ['kind' => 'question', 'body' => 'Geht ein Filter nach Kunde?'])->assertSessionHasNoErrors();
        $this->assertSame(AppWishKind::Question, $wish->fresh()?->kind);

        $this->actingAs($author)->put(route('wishes.answer.update', $wish), ['status' => 'planned', 'answer' => 'x'])->assertForbidden();
        $this->actingAs($lead)->get(route('wishes.answer.edit', ['appWish' => $wish, 'dialog' => 1]))->assertOk();
        $this->actingAs($lead)->put(route('wishes.answer.update', $wish), ['status' => 'planned', 'answer' => 'Kommt mit dem nächsten Update.'])->assertSessionHasNoErrors();

        $wish->refresh();
        $this->assertSame(AppWishStatus::Planned, $wish->status);
        $this->assertSame('Kommt mit dem nächsten Update.', $wish->answer);
        $this->assertSame(1, $author->notifications()->count(), 'Hinweis an die verfassende Person.');
        $this->assertSame(1, $voter->notifications()->count(), 'Hinweis an Abstimmende.');
        $this->actingAs($author)->put(route('wishes.update', $wish), ['kind' => 'wish', 'body' => 'Zu spät'])->assertForbidden();
        $this->actingAs($lead)->put(route('wishes.answer.update', $wish), ['status' => 'new', 'answer' => ''])->assertSessionHasErrors('status');
    }

    public function test_at_most_thirty_new_wishes_per_person_and_day(): void {
        $author = $this->worker();
        $service = app(AppWishService::class);
        for ($i = 0; $i < AppWishService::DAILY_LIMIT; $i++) {
            $service->create($author, $this->organization, AppWishKind::Wish, 'Wunsch ' . $i, null);
        }

        $this->actingAs($author)->post(route('wishes.store'), ['kind' => 'wish', 'body' => 'Einer zu viel'])->assertSessionHasErrors('body');
        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->actingAs($author)->post(route('wishes.store'), ['kind' => 'wish', 'body' => 'Am nächsten Tag'])->assertSessionHasNoErrors();
    }

    public function test_forwarding_reaches_the_platform_view_without_names_and_the_answer_returns(): void {
        $author = $this->worker(['name' => 'Anna Autorin']);
        $voter = $this->worker(['name' => 'Viktor Votant']);
        $lead = $this->lead();
        $wish = $this->wish($author, 'Für Kunde Müller AG fehlt ein Export.');
        app(AppWishService::class)->vote($wish, $voter);

        $this->actingAs($author)->post(route('wishes.forward.store', $wish), ['forwarded_body' => 'Export fehlt', 'confirm_clean' => '1'])->assertForbidden();
        $this->actingAs($lead)->post(route('wishes.forward.store', $wish), ['forwarded_body' => 'Für einen Kunden fehlt ein Export.'])->assertSessionHasErrors('confirm_clean');
        $this->actingAs($lead)->post(route('wishes.forward.store', $wish), ['forwarded_body' => 'Für einen Kunden fehlt ein Export.', 'confirm_clean' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($wish->fresh()?->isForwarded());

        $admin = $this->platformAdmin();
        $this->actingAs($lead)->get(route('admin.wishes.index'))->assertForbidden();
        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->organization_id);
        $index = $this->actingAs($admin)->get(route('admin.wishes.index'));
        $index->assertOk()->assertSee('Für einen Kunden fehlt ein Export.')->assertSee('Kanzlei Beispiel');
        foreach ([$index, $this->actingAs($admin)->get(route('admin.wishes.show', $wish))] as $response) {
            $response->assertOk()
                ->assertDontSee('Anna Autorin')
                ->assertDontSee('Viktor Votant')
                ->assertDontSee('Gerda Leitung')
                ->assertDontSee('Müller AG');
        }

        $this->actingAs($admin)->put(route('admin.wishes.update', $wish), ['platform_status' => 'planned', 'platform_answer' => 'Ist eingeplant.'])->assertSessionHasNoErrors();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->actingAs($author)->get(route('wishes.index'))
            ->assertOk()
            ->assertSee(__('wishes.answer.platform'))
            ->assertSee('Ist eingeplant.');
        $this->assertSame(1, $author->notifications()->count());
        $this->assertSame(1, $voter->notifications()->count());
    }

    public function test_bundling_shares_status_and_answer_of_the_lead(): void {
        $first = $this->wish($this->worker(), 'Kalender als Abo');
        $foreignOrg = Organization::factory()->create(['name' => 'Pflege Nord']);
        $second = AppWish::factory()->forwarded('Kalender abonnieren')->create(['organization_id' => $foreignOrg->id]);
        app(AppWishService::class)->forward($first, $this->lead(), 'Kalender als Abo');
        $admin = $this->platformAdmin();
        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->organization_id);

        $this->actingAs($admin)->put(route('admin.wishes.bundle', $second), ['lead' => $first->sqid])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.wishes.update', $first), ['platform_status' => 'in_review', 'platform_answer' => 'Wir prüfen das.'])->assertSessionHasNoErrors();

        $second->refresh();
        $this->assertSame('Wir prüfen das.', $second->platform_answer);
        $this->assertSame(AppWishStatus::InReview, $second->platform_status);
        $this->actingAs($admin)->put(route('admin.wishes.update', $second), ['platform_status' => 'done'])->assertSessionHasErrors('platform_status');
        $this->actingAs($admin)->get(route('admin.wishes.index'))->assertOk()->assertSee(trans_choice('wishes.platform.bundled_count', 1, ['count' => 1]));
    }

    public function test_without_the_vendor_opt_in_no_wish_leaves_the_installation(): void {
        $wish = $this->wish($this->worker());
        app(AppWishService::class)->forward($wish, $this->lead(), 'Ein Export fehlt.');
        $admin = $this->platformAdmin();
        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->organization_id);

        $this->assertFalse(app(VendorChannel::class)->enabled(), 'Vorgabe aus.');
        $this->actingAs($admin)->get(route('admin.wishes.show', $wish))->assertOk()->assertSee(__('wishes.vendor.disabled_hint'))->assertDontSee('name="preview_hash"', false);
        $this->actingAs($admin)->post(route('admin.wishes.vendor.send', $wish), ['preview_hash' => 'egal'])->assertSessionHasErrors('vendor');

        Mail::assertNothingSent();
        $this->assertNull($wish->fresh()?->vendor_sent_at);
    }

    public function test_with_the_opt_in_the_admin_sends_exactly_the_previewed_mail_and_records_the_answer(): void {
        $author = $this->worker(['name' => 'Anna Autorin']);
        $wish = $this->wish($author, 'Original mit Namen');
        app(AppWishService::class)->vote($wish, $this->worker());
        app(AppWishService::class)->forward($wish, $this->lead(), 'Ein Export fehlt.');
        Setting::set(VendorChannel::SETTING_ENABLED, true, SettingScope::System);
        Setting::set(VendorChannel::SETTING_ADDRESS, 'hersteller@example.test', SettingScope::System);
        $admin = $this->platformAdmin();
        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->organization_id);

        $this->actingAs($admin)->put(route('admin.wishes.vendor-text', $wish), ['vendor_body' => 'Ein CSV-Export fehlt.'])->assertSessionHasNoErrors();
        $preview = app(VendorChannel::class)->preview($wish->fresh() ?? $wish, $admin);
        $this->assertSame(app(VendorChannel::class)->mail($wish->fresh() ?? $wish, $admin)->messageText, trim($preview['body']), 'Die Vorschau ist der gesendete Text, ohne HTML-Entitäten.');
        $this->assertStringContainsString('<plattform@example.test>', $preview['body']);
        $this->assertSame('hersteller@example.test', $preview['to']);
        foreach (['W-' . $wish->sqid, AppWishKind::Wish->label(), 'Ein CSV-Export fehlt.', 'work-queue.index', 'plattform@example.test'] as $expected) {
            $this->assertStringContainsString($expected, $preview['body']);
        }
        $this->assertStringContainsString(__('wishes.vendor.mail.votes') . ': 1', $preview['body']);
        foreach (['Kanzlei Beispiel', 'Anna Autorin', 'Original mit Namen', 'Gerda Leitung'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $preview['body'] . $preview['subject']);
        }
        $this->actingAs($admin)->get(route('admin.wishes.show', $wish))->assertOk()->assertSee('name="preview_hash" value="' . $preview['hash'] . '"', false);

        $this->actingAs($admin)->post(route('admin.wishes.vendor.send', $wish), ['preview_hash' => 'veraltet'])->assertSessionHasErrors('vendor');
        Mail::assertNothingSent();
        $this->actingAs($admin)->post(route('admin.wishes.vendor.send', $wish), ['preview_hash' => $preview['hash']])->assertSessionHasNoErrors();

        Mail::assertSent(AppWishVendorMail::class, static fn (AppWishVendorMail $mail): bool => $mail->hasTo('hersteller@example.test')
            && $mail->hasReplyTo('plattform@example.test')
            && $mail->messageText === app(VendorChannel::class)->mail(AppWish::query()->withoutGlobalScope(OrganizationScope::class)->firstOrFail(), User::query()->where('email', 'plattform@example.test')->firstOrFail())->messageText);
        $this->assertSame('Ein CSV-Export fehlt.', $wish->fresh()?->vendor_body);
        $this->actingAs($admin)->post(route('admin.wishes.vendor.send', $wish), ['preview_hash' => $preview['hash']])->assertSessionHasErrors('vendor');
        Mail::assertSentCount(1);

        $this->actingAs($admin)->put(route('admin.wishes.vendor-answer', $wish), ['vendor_answer' => 'Kommt in Version 2.'])->assertSessionHasNoErrors();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->actingAs($author)->get(route('wishes.index'))->assertOk()->assertSee(__('wishes.answer.vendor'))->assertSee('Kommt in Version 2.');
    }

    public function test_the_demo_block_seeds_invented_wishes(): void {
        $admin = $this->orgAdmin();
        $users = collect([$admin, $this->worker(), $this->worker()]);
        $context = new DemoSeedContext($this->organization, $admin, $users, [], null, null, null);

        $counts = app(WishesDemoBlock::class)->seed($context);
        $this->assertSame(3, $counts['app_wishes']);
        $this->assertSame(6, $counts['app_wish_votes']);
        $this->assertSame(1, AppWish::query()->whereNotNull('forwarded_at')->count());
        Mail::assertNothingSent();

        app(WishesDemoBlock::class)->purge($this->organization);
        $this->assertSame(0, AppWish::query()->count());
        $this->assertSame(0, AppWishVote::query()->count());
    }
}
