<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryReviewController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Diary;

use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Diary\DiaryEntry;
use App\Services\Diary\OrderService;
use App\Services\Diary\Review\ReviewQueue;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Interne Prüfung der Aufträge (Feature 198): Prüf-Warteschlange (MVP-1636)
 * und Prüfpflicht am einzelnen Auftrag (MVP-1635). Die Übergänge selbst
 * laufen über den Lebenszyklus (`diary.lifecycle`).
 */
class DiaryReviewController extends Controller {
    use ResolvesCurrentOrganization;

    /** Aufträge zur Prüfung, die der Nutzer prüfen darf; Fälligkeit, dann Vorlagedatum. */
    public function queue(ReviewQueue $queue): View {
        $entries = $queue->for($this->authUser(), $this->currentOrganizationId())
            ->with(['customer:id,name', 'entryType:id,label', 'reviewSubmitter:id,name', 'assignedUser:id,name', 'classifications'])
            ->paginate(25);

        return view('diary.review-queue', ['entries' => $entries]);
    }

    /** Prüfpflicht ab- oder einschalten (eigenes Recht, Grund im Verlauf, Änderung im Audit). */
    public function requirement(Request $request, DiaryEntry $diary, OrderService $orders): RedirectResponse {
        $this->assertSameOrganization($diary);
        Gate::authorize('waiveReview', $diary);
        $data = $request->validate([
            'requires_review' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $orders->setReviewRequirement($diary, $this->authUser(), (bool) $data['requires_review'], $data['reason'] ?? null);
        } catch (InvalidOrderTransitionException $e) {
            return back()->withErrors(['lifecycle' => ErrorText::for($e)]);
        }

        return back()->with('success', __((bool) $data['requires_review'] ? 'diary.review.flash.required' : 'diary.review.flash.waived'));
    }
}
