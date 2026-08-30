<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBookingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningAccessKind, LearningBookingStatus, LearningEnrollmentSource};
use App\Models\{Article, User};
use App\Models\Learning\{LearningBooking, LearningCourse};
use App\Services\Learning\{LearningBookingService, LearningCourseService};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kursbuchung und Verkauf (Feature 149, MVP-744): zweiphasig, Preis wird
 * bei der Zusage eingefroren, und es entsteht **keine** automatische
 * Rechnung — die Rechnungshoheit kann extern liegen.
 */
class LearningBookingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): LearningBookingService {
        return app(LearningBookingService::class);
    }

    private function course(array $attributes = [], bool $release = true): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, array_merge([
            'title' => 'Anwenderschulung',
            'access_kind' => LearningAccessKind::Bookable->value,
        ], $attributes));
        $courses->addUnit($course, ['title' => 'Grundlagen']);

        if ($release) {
            $courses->release($course->refresh(), null);
        }

        return $course->refresh();
    }

    private function booker(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    public function test_anfrage_schafft_noch_keinen_zugang(): void {
        $course = $this->course();
        $user = $this->booker();

        $booking = $this->service()->request($course, $user);

        $this->assertSame(LearningBookingStatus::Requested, $booking->status);
        $this->assertNull($booking->learning_enrollment_id, 'Der Zugang entsteht erst mit der Zusage.');
    }

    public function test_doppelte_anfrage_bleibt_eine_anfrage(): void {
        $course = $this->course();
        $user = $this->booker();

        $first = $this->service()->request($course, $user);
        $second = $this->service()->request($course, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LearningBooking::query()->count());
    }

    public function test_zusage_schafft_den_zugang_und_friert_den_preis_ein(): void {
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_sale_price' => Money::of('149.00', CurrencyCode::Euro),
        ]);
        $course = $this->course(['article_id' => $article->id]);
        $user = $this->booker();
        $booking = $this->service()->request($course, $user);

        $booking = $this->service()->confirm($booking, $this->booker());

        $this->assertSame(LearningBookingStatus::Confirmed, $booking->status);
        $this->assertNotNull($booking->learning_enrollment_id);
        $this->assertSame('149.00', $booking->unit_price);
        $this->assertSame(CurrencyCode::Euro, $booking->currency);
        $this->assertTrue($booking->is_billable);
        $this->assertNull($booking->billed_at, 'Zugesagt ist nicht fakturiert.');

        // Eine spätere Preisänderung verteuert die Zusage nicht.
        $article->update(['default_sale_price' => Money::of('199.00', CurrencyCode::Euro)]);
        $this->assertSame('149.00', $booking->refresh()->unit_price);
    }

    public function test_zusage_ohne_artikel_bleibt_kostenfrei(): void {
        $course = $this->course();
        $booking = $this->service()->request($course, $this->booker());

        $booking = $this->service()->confirm($booking);

        $this->assertNull($booking->unit_price);
        $this->assertFalse($booking->is_billable);
        $this->assertSame(LearningEnrollmentSource::Booking, $booking->enrollment?->source);
    }

    public function test_nur_buchbare_und_freigegebene_kurse(): void {
        $draft = $this->course(release: false);
        $user = $this->booker();

        try {
            $this->service()->request($draft, $user);
            $this->fail('Ein Entwurf darf nicht buchbar sein.');
        } catch (ValidationException) {
            // erwartet
        }

        $notBookable = $this->course(['access_kind' => LearningAccessKind::Enrolled->value]);
        $this->expectException(ValidationException::class);
        $this->service()->request($notBookable, $user);
    }

    public function test_absage_braucht_eine_begruendung(): void {
        $course = $this->course();
        $booking = $this->service()->request($course, $this->booker());

        $this->expectException(ValidationException::class);
        $this->service()->reject($booking, '   ');
    }

    public function test_entschiedene_buchung_wird_nicht_erneut_entschieden(): void {
        $course = $this->course();
        $booking = $this->service()->request($course, $this->booker());
        $this->service()->confirm($booking);

        $this->expectException(ValidationException::class);
        $this->service()->confirm($booking->refresh());
    }

    public function test_abgerechnete_buchung_wird_nicht_storniert(): void {
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_sale_price' => Money::of('99.00', CurrencyCode::Euro),
        ]);
        $course = $this->course(['article_id' => $article->id]);
        $booking = $this->service()->confirm($this->service()->request($course, $this->booker()));
        $this->service()->markBilled($booking);

        $this->expectException(ValidationException::class);
        $this->service()->cancel($booking->refresh());
    }

    public function test_nur_offene_posten_lassen_sich_abrechnen(): void {
        $course = $this->course();
        $booking = $this->service()->confirm($this->service()->request($course, $this->booker()));

        // Ohne Preis gibt es nichts abzurechnen.
        $this->expectException(ValidationException::class);
        $this->service()->markBilled($booking);
    }
}
