<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBookingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningBookingStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Learning\LearningBooking;
use App\Models\User;
use App\Services\Learning\LearningBookingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Buchungsanfragen zu Kursen (Feature 149, MVP-744).
 *
 * Zweiphasig wie die Terminbuchung (Feature 087): die Anfrage wandert in
 * eine Arbeitsliste, der Zugang entsteht erst mit der Zusage.
 */
class LearningBookingController extends Controller {
    public function __construct(
        private readonly LearningBookingService $bookings,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::LearningManage->value);

        $status = $request->query('status');
        $status = is_string($status) ? LearningBookingStatus::tryFrom($status) : LearningBookingStatus::Requested;

        return view('learning.bookings.index', [
            'bookings' => LearningBooking::query()
                ->with(['course', 'user', 'externalParticipant', 'customer'])
                ->when($status instanceof LearningBookingStatus, fn ($q) => $q->where('status', $status?->value))
                ->orderByDesc('requested_at')
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'openCount' => LearningBooking::query()->where('status', LearningBookingStatus::Requested->value)->count(),
        ]);
    }

    public function confirm(Request $request, LearningBooking $booking): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $note = $request->string('note')->trim()->value();
        $this->bookings->confirm($booking, $this->actor(), $note !== '' ? $note : null);

        return redirect()
            ->route('learning.bookings.index')
            ->with('success', __('learning.flash.booking_confirmed'));
    }

    public function reject(Request $request, LearningBooking $booking): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $this->bookings->reject($booking, $data['reason'], $this->actor());

        return redirect()
            ->route('learning.bookings.index')
            ->with('success', __('learning.flash.booking_rejected'));
    }

    /** Als fakturiert markieren — der Beleg entsteht in der Faktura. */
    public function markBilled(LearningBooking $booking): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $this->bookings->markBilled($booking);

        return redirect()
            ->route('learning.bookings.index')
            ->with('success', __('learning.flash.booking_billed'));
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
