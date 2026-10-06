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
use App\Models\Customer\Customer;
use App\Models\Learning\{LearningBooking, LearningCourse, LearningEnrollment};
use App\Models\Platform\{Organization, User};
use App\Services\Learning\LearningCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
