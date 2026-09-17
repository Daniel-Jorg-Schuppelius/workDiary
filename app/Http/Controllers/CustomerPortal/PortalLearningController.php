<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalLearningController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Learning\{LearningAudience, LearningCourseStatus, LearningProgressStatus};
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCourse, LearningCourseCategory, LearningEnrollment, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningBookingService, LearningEnrollmentService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Kundenschulungen im Portal (Feature 149, MVP-742).
 *
 * **Default-Deny:** sichtbar sind ausschließlich freigegebene Kurse, die
 * die Zielgruppe `customer` ausdrücklich führen. Ein Kurs wird nie durch
 * bloßes Anlegen extern sichtbar.
 *
 * Der Guard ist `customer`; interne Routen sind durch die Provider-Trennung
 * technisch nicht erreichbar.
 */
class PortalLearningController extends Controller {
    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
        private readonly LearningBookingService $bookings,
    ) {}

    public function index(Request $request): View {
        $user = $this->actor();
        // Kategoriefilter (MVP-788) — Sqid aus der Auswahl.
        $categoryId = $request->filled('category') ? Sqid::decodeOrNumeric(LearningCourseCategory::class, (string) $request->query('category')) : null;
        $courses = $this->visibleCourses();

        $visible = $categoryId !== null ? $courses->where('category_id', $categoryId)->values() : $courses;

        return view('customer.learning.index', [
            'courses' => $visible,
            // Sternewert (MVP-794): erst ab fünf Antworten.
            'ratings' => app(\App\Services\Learning\LearningCourseRatingService::class)->ratingsFor(array_values(array_map('intval', $visible->pluck('id')->all()))),
            'categoryId' => $categoryId,
            'categories' => LearningCourseCategory::query()
                ->whereIn('id', $courses->pluck('category_id')->filter()->unique()->all())
                ->orderBy('position')->orderBy('name')->get(),
            'enrollments' => LearningEnrollment::query()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('learning_course_id'),
        ]);
    }

    /** Selbsteinschreibung in einen für Kunden freigegebenen Kurs. */
    public function enroll(LearningCourse $course): RedirectResponse {
        $this->guardVisible($course);

        $enrollment = $this->enrollments->enroll($course, $this->actor(), ['source' => 'self']);

        return redirect()
            ->route('customer.learning.show', $enrollment)
            ->with('success', __('learning.flash.created'));
    }

    /**
     * Buchbare Kurse werden **angefragt**, nicht selbst eingeschrieben —
     * zweiphasig wie die Terminbuchung (Feature 087).
     */
    public function requestBooking(LearningCourse $course): RedirectResponse {
        $this->guardVisible($course);

        $user = $this->actor();
        $this->bookings->request($course, $user, $user->customer);

        return redirect()
            ->route('customer.learning.index')
            ->with('success', __('learning.flash.booking_requested'));
    }

    /**
     * Vorschau ohne Einschreibung (MVP-788): nur Einheiten mit
     * Vorschau-Kennzeichen und darin nur Textblöcke — Medien und Prüfungen
     * hängen an einer Einschreibung.
     */
    public function preview(LearningCourse $course): View {
        $this->guardVisible($course);

        return view('customer.learning.preview', [
            'course' => $course,
            'units' => $course->units()->where('is_preview', true)->orderBy('position')->get(),
            'enrollment' => LearningEnrollment::query()
                ->where('user_id', $this->actor()->id)
                ->where('learning_course_id', $course->id)
                ->first(),
        ]);
    }

    public function show(LearningEnrollment $enrollment): View {
        $this->guardOwn($enrollment);
        $enrollment->load(['course.units']);
        // Mindestverweildauer (MVP-788): das erste Öffnen zählt ab jetzt.
        $this->enrollments->markSeen($enrollment, $enrollment->course->units ?? []);
        $enrollment->load('progress');

        return view('customer.learning.show', [
            'enrollment' => $enrollment,
            'course' => $enrollment->course,
            'completedUnitIds' => $enrollment->progress
                ->where('status', LearningProgressStatus::Completed)
                ->pluck('learning_unit_id')
                ->all(),
        ]);
    }

    public function completeUnit(LearningEnrollment $enrollment, LearningUnit $unit): RedirectResponse {
        $this->guardOwn($enrollment);
        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);

        $this->enrollments->completeUnit($enrollment, $unit);

        return redirect()
            ->route('customer.learning.show', $enrollment)
            ->with('success', __('learning.flash.unit_completed'));
    }

    /**
     * Freigegebene Kurse mit ausdrücklicher Kunden-Zielgruppe.
     *
     * @return \Illuminate\Support\Collection<int, LearningCourse>
     */
    private function visibleCourses() {
        return LearningCourse::query()
            // Mandantengrenze ausdruecklich, nicht nur ueber die Reihenfolge der
            // Middleware (Audit 2026-09, authflow-1): der Portal-Stack setzt den
            // Guard `customer` vor `SetOrganizationContext`, deshalb bindet die
            // Abfrage die Organisation des Portalnutzers selbst.
            ->where('organization_id', $this->actor()->organization_id)
            ->with(['category', 'article'])
            ->withCount(['units as preview_units_count' => fn ($q) => $q->where('is_preview', true)])
            ->where('status', LearningCourseStatus::Released->value)
            // Verfügbarkeitsfenster (MVP-788): außerhalb ist der Kurs im Katalog unsichtbar.
            ->available()
            ->orderBy('title')
            ->get()
            ->filter(static fn (LearningCourse $course): bool => $course->servesAudience(LearningAudience::Customer))
            ->values();
    }

    private function guardVisible(LearningCourse $course, bool $catalog = true): void {
        // Erst die Mandantengrenze, dann die Fachpruefung: ein Kurs einer
        // fremden Organisation ist fuer diesen Portalnutzer nicht vorhanden.
        abort_unless((int) $course->organization_id === (int) $this->actor()->organization_id, 404);
        abort_unless(
            $course->status === LearningCourseStatus::Released && $course->servesAudience(LearningAudience::Customer),
            404
        );
        // Eine bestehende Einschreibung überlebt das Fenster — der Katalog nicht.
        abort_unless(! $catalog || $course->isAvailableOn(), 404);
    }

    private function guardOwn(LearningEnrollment $enrollment): void {
        abort_unless($enrollment->user_id === $this->actor()->id, 404);
        $this->guardVisible($enrollment->course ?? abort(404), catalog: false);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        return $user;
    }
}
