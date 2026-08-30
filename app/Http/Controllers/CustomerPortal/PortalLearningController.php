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
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningBookingService, LearningEnrollmentService};
use Illuminate\Http\RedirectResponse;
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

    public function index(): View {
        $user = $this->actor();

        return view('customer.learning.index', [
            'courses' => $this->visibleCourses(),
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

    public function show(LearningEnrollment $enrollment): View {
        $this->guardOwn($enrollment);
        $enrollment->load(['course.units', 'progress']);

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
            ->where('status', LearningCourseStatus::Released->value)
            ->orderBy('title')
            ->get()
            ->filter(static fn (LearningCourse $course): bool => $course->servesAudience(LearningAudience::Customer))
            ->values();
    }

    private function guardVisible(LearningCourse $course): void {
        abort_unless(
            $course->status === LearningCourseStatus::Released && $course->servesAudience(LearningAudience::Customer),
            404
        );
    }

    private function guardOwn(LearningEnrollment $enrollment): void {
        abort_unless($enrollment->user_id === $this->actor()->id, 404);
        $this->guardVisible($enrollment->course ?? abort(404));
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        return $user;
    }
}
