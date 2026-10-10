<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalLearningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\Learning\{LearningAudience, LearningEnrollmentStatus};
use App\Models\Attachments\Attachment;
use App\Models\Customer\Customer;
use App\Models\Learning\{LearningBooking, LearningCourse, LearningEnrollment};
use App\Models\Platform\{Organization, User};
use App\Notifications\GenericEventNotification;
use App\Services\Learning\{LearningCourseService, LearningNotifier};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Notification, Storage};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Kundenschulungen im Portal (Feature 149, MVP-742).
 *
 * Der Kern ist **Default-Deny**: ein Kurs erscheint erst, wenn er
 * freigegeben ist UND die Zielgruppe `customer` ausdrücklich führt.
 */
class PortalLearningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create();
    }

    private function course(array $attributes = [], bool $release = true): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, array_merge([
            'title' => 'Geräteeinweisung für Kunden',
            'audiences' => [LearningAudience::Customer->value],
        ], $attributes));
        $courses->addUnit($course, ['title' => 'Bedienung']);

        if ($release) {
            $courses->release($course->refresh(), null);
        }

        return $course->refresh();
    }

    /**
     * Mandantengrenze ausdruecklich, nicht nur ueber die Middleware-Reihenfolge
     * (Audit 2026-09, `authflow-1`): ein freigegebener Kundenkurs einer fremden
     * Organisation darf weder im Katalog stehen noch einzeln erreichbar sein.
     */
    public function test_fremder_mandant_ist_im_portal_unsichtbar(): void {
        $eigener = $this->course(['title' => 'Eigener Kundenkurs']);

        // Kurs und Einheit tragen einen globalen Mandanten-Scope; ohne
        // Kontextwechsel zaehlt die Freigabepruefung die Einheiten des fremden
        // Kurses gegen die eigene Organisation und schlaegt fehl.
        $fremdeOrg = Organization::factory()->create();
        $courses = app(LearningCourseService::class);
        app()->instance('currentOrganization', $fremdeOrg);
        $fremder = $courses->createCourse($fremdeOrg, null, [
            'title' => 'Fremder Kundenkurs',
            'audiences' => [LearningAudience::Customer->value],
        ]);
        $courses->addUnit($fremder, ['title' => 'Bedienung']);
        $courses->release($fremder->refresh(), null);
        app()->instance('currentOrganization', $this->organization);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.learning.index'))
            ->assertOk()
            ->assertSee($eigener->title)
            ->assertDontSee('Fremder Kundenkurs');

        // Die Detailroute erwartet eine Einschreibung; der Einzelzugriff auf
        // einen fremden Kurs laeuft ueber die Vorschau, die `guardVisible` ruft.
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.learning.preview', $fremder->refresh()))
            ->assertNotFound();
    }

    public function test_katalog_zeigt_nur_kurse_mit_kunden_zielgruppe(): void {
        $visible = $this->course();
        $internal = $this->course(['title' => 'Nur intern', 'audiences' => [LearningAudience::Internal->value]]);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.learning.index'))
            ->assertOk()
            ->assertSee($visible->title)
            ->assertDontSee($internal->title);
    }

    public function test_entwurf_ist_im_portal_unsichtbar(): void {
        $draft = $this->course(['title' => 'Entwurf für Kunden'], release: false);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.learning.index'))
            ->assertOk()
            ->assertDontSee($draft->title);
    }

    public function test_selbsteinschreibung_und_abschluss(): void {
        $course = $this->course(['access_kind' => 'open']);

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.learning.enroll', $course))
            ->assertRedirect();

        $enrollment = LearningEnrollment::query()->where('user_id', $this->portalUser->id)->firstOrFail();
        $unit = $course->units()->firstOrFail();

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.learning.units.complete', [$enrollment, $unit]))
            ->assertRedirect(route('customer.learning.show', $enrollment));

        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_einschreibung_in_einen_internen_kurs_wird_abgewiesen(): void {
        $internal = $this->course(['audiences' => [LearningAudience::Internal->value]]);

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.learning.enroll', $internal))
            ->assertNotFound();
    }

    /** Sicherheitsaudit 2026-10-04, authz-b-5: selbst einschreiben nur in offene Kurse; buchbare laufen über die Buchung. */
    public function test_selbsteinschreibung_nur_in_offene_kurse(): void {
        foreach (['enrolled', 'bookable', 'closed'] as $kind) {
            $course = $this->course(['title' => 'Kurs ' . $kind, 'access_kind' => $kind]);

            $this->actingAs($this->portalUser, 'customer')
                ->post(route('customer.learning.enroll', $course))
                ->assertSessionHasErrors('course');
        }
        $this->assertSame(0, LearningEnrollment::query()->count());

        $bookable = LearningCourse::query()->where('access_kind', 'bookable')->firstOrFail();
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.learning.book', $bookable))
            ->assertRedirect(route('customer.learning.index'));
        $this->assertSame(1, LearningBooking::query()->where('learning_course_id', $bookable->id)->count());
        $this->assertSame(0, LearningEnrollment::query()->count());

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.learning.index'))
            ->assertOk()
            ->assertSee(route('customer.learning.book', $bookable), false)
            ->assertDontSee(route('customer.learning.enroll', $bookable), false);
    }

    /** Phase 137 (MVP-1105): die Vorschau bot „Einschreiben“ auch bei buchbaren und verwalteten Kursen an — der Klick endete im Fehler. */
    public function test_vorschau_bietet_je_zugangsart_den_passenden_weg(): void {
        $bookable = $this->course(['title' => 'Buchbarer Kurs', 'access_kind' => 'bookable']);
        $managed = $this->course(['title' => 'Verwalteter Kurs', 'access_kind' => 'enrolled']);
        $open = $this->course(['title' => 'Offener Kurs', 'access_kind' => 'open']);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.learning.preview', $bookable))
            ->assertOk()
            ->assertSee(route('customer.learning.book', $bookable), false)
            ->assertDontSee(route('customer.learning.enroll', $bookable), false);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.learning.preview', $managed))
            ->assertOk()
            ->assertSee(__('learning.help.enroll_by_operator'))
            ->assertDontSee(route('customer.learning.enroll', $managed), false);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.learning.preview', $open))
            ->assertOk()
            ->assertSee(route('customer.learning.enroll', $open), false);
    }

    /** Phase 137 (MVP-1105): nach dem Einschreiben stand „Kurs angelegt.“ da. */
    public function test_einschreiben_meldet_die_einschreibung(): void {
        $course = $this->course(['access_kind' => 'open']);

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.learning.enroll', $course))
            ->assertSessionHas('success', __('learning.flash.enrolled'));
    }

    /** Phase 137 (MVP-1105): die Kursseite zeigte nur Text und Überschriften; Bilder laufen über eine Portal-Route. */
    public function test_kursseite_zeigt_alle_blockarten_mit_portal_medien(): void {
        Storage::fake('local');
        $course = $this->course(['access_kind' => 'open']);
        $unit = $course->units()->firstOrFail();
        $image = Attachment::factory()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $unit->getMorphClass(),
            'attachable_id' => $unit->id,
            'disk' => 'local',
            'path' => 'learning/test/bedienfeld.png',
            'original_name' => 'bedienfeld.png',
            'mime' => 'image/png',
        ]);
        Storage::disk('local')->put('learning/test/bedienfeld.png', 'PNG');
        $unit->forceFill(['content' => json_encode([
            ['type' => 'callout', 'tone' => 'warning', 'text' => 'Vorsicht, Oberfläche heiß'],
            ['type' => 'image', 'attachment_id' => $image->id, 'alt' => 'Bedienfeld des Geräts'],
            ['type' => 'checklist', 'items' => ['Stecker ziehen']],
        ])])->save();

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.learning.enroll', $course));
        $enrollment = LearningEnrollment::query()->where('user_id', $this->portalUser->id)->firstOrFail();
        $media = route('customer.learning.units.media', [$enrollment, $unit, $image]);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.learning.show', $enrollment))
            ->assertOk()
            ->assertSee('Vorsicht, Oberfläche heiß')
            ->assertSee('Stecker ziehen')
            ->assertSee('src="' . $media . '"', false)
            ->assertDontSee('/meine-schulungen/', false);

        $this->actingAs($this->portalUser, 'customer')->get($media)->assertOk();

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->kunde((int) $otherCustomer->id, (int) $this->organization->id)->create();
        $this->actingAs($other, 'customer')->get($media)->assertNotFound();
    }

    /** Phase 137 (MVP-1105): Benachrichtigungen an Portalkonten verlinken die Portalseite, nicht die interne Lernansicht. */
    public function test_benachrichtigung_an_portalkonto_verlinkt_das_portal(): void {
        Notification::fake();
        $course = $this->course(['access_kind' => 'open']);
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.learning.enroll', $course));
        $enrollment = LearningEnrollment::query()->where('user_id', $this->portalUser->id)->firstOrFail();

        app(LearningNotifier::class)->enrolled($enrollment);

        Notification::assertSentTo($this->portalUser, GenericEventNotification::class, fn (GenericEventNotification $n): bool => ($n->payload['url'] ?? null) === route('customer.learning.show', $enrollment));
    }

    public function test_fremde_einschreibung_ist_nicht_einsehbar(): void {
        $course = $this->course(['access_kind' => 'open']);
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($otherCustomer);
        $other = User::factory()->kunde((int) $otherCustomer->id, (int) $this->organization->id)->create();

        $this->actingAs($other, 'customer')->post(route('customer.learning.enroll', $course));
        $foreign = LearningEnrollment::query()->where('user_id', $other->id)->firstOrFail();

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.learning.show', $foreign))
            ->assertNotFound();
    }

    public function test_ohne_anmeldung_kein_zugriff(): void {
        $this->get(route('customer.learning.index'))->assertRedirect();
    }
}
