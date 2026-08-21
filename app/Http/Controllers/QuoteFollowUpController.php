<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteFollowUpController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Quote, User};
use App\Services\Invoicing\QuoteFollowUpService;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Nachfass-Arbeitsliste für Angebote (Feature 112, MVP-601).
 *
 * **Warum eine eigene Seite statt eines Filters in der Belegliste:** Der
 * Beleg-Feed ist auf den globalen Zeitraum begrenzt (`created_at` zwischen
 * von/bis). Ein Angebot, dessen Nachfasstermin heute fällig ist, kann drei
 * Monate alt sein — es fiele aus dem Zeitraum und damit aus der Liste, in der
 * es am dringendsten gebraucht wird. Eine Arbeitsliste, die den wichtigsten
 * Fall verschweigt, ist schlechter als keine.
 */
class QuoteFollowUpController extends Controller {
    public function __construct(private readonly QuoteFollowUpService $followUps) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Quote::class);
        $user = $request->user() ?? abort(401);

        $today = CarbonImmutable::today();
        $horizon = (int) Setting::get('invoicing.quote_follow_up_days', config('invoicing.quote_follow_up_days', 7));
        // Gleicher Vorlauf wie der Fristen-Scanner (--expiring-days=30),
        // damit Liste und Benachrichtigung denselben Ausschnitt zeigen.
        $expiringDays = 30;

        $base = fn () => Quote::query()
            ->with(['customer:id,name,company', 'followUpUser:id,name'])
            ->whereIn('status', ['approved', 'sent']);

        // Nur die eigenen? Bewusst als Filter, nicht als Zwang: Wer vertritt,
        // muss die Angebote der Kollegin sehen können.
        $mine = $request->boolean('mine');
        $scope = static function ($query) use ($mine, $user) {
            if ($mine) {
                $query->where('follow_up_user_id', $user->id);
            }

            return $query;
        };

        $due = $scope($base()->whereNotNull('follow_up_at')->whereNull('followed_up_at')
            ->whereDate('follow_up_at', '<=', $today->toDateString()))
            ->orderBy('follow_up_at')
            ->get();

        $upcoming = $scope($base()->whereNotNull('follow_up_at')->whereNull('followed_up_at')
            ->whereDate('follow_up_at', '>', $today->toDateString())
            ->whereDate('follow_up_at', '<=', $today->addDays(max($horizon, 7))->toDateString()))
            ->orderBy('follow_up_at')
            ->get();

        // Läuft ab ohne Reaktion — der teure Fall: Ein ausgelaufenes Angebot
        // muss neu erstellt oder verlängert werden.
        $expiring = $scope($base()->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', $today->toDateString())
            ->whereDate('valid_until', '<=', $today->addDays($expiringDays)->toDateString()))
            ->orderBy('valid_until')
            ->get();

        // Ohne Termin: die stille Lücke — versandt, aber niemand hat einen
        // Nachfasstermin gesetzt.
        $untracked = $scope($base()->where('status', 'sent')->whereNull('follow_up_at'))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('quotes.follow-ups', [
            'due' => $due,
            'upcoming' => $upcoming,
            'expiring' => $expiring,
            'untracked' => $untracked,
            'mine' => $mine,
            'expiringDays' => $expiringDays,
        ]);
    }

    /** Dialog-Fragment „Nachfassen protokollieren". */
    public function dialog(Quote $quote): View {
        Gate::authorize('followUp', $quote);

        return view('quotes._follow_up_dialog', ['quote' => $quote]);
    }

    public function store(Request $request, Quote $quote): RedirectResponse {
        Gate::authorize('followUp', $quote);
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'result' => ['required', 'string', 'min:3', 'max:4000'],
            'next_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            $this->followUps->record($quote, $user, (string) $data['result'], $data['next_at'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('quotes.follow_up.recorded'));
    }

    /** Nachfasstermin setzen/verschieben, ohne ein Ergebnis zu protokollieren. */
    public function schedule(Request $request, Quote $quote): RedirectResponse {
        Gate::authorize('followUp', $quote);
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'follow_up_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $quote->forceFill([
            'follow_up_at' => $data['follow_up_at'],
            'followed_up_at' => null,
            'follow_up_user_id' => $quote->follow_up_user_id ?? $user->id,
        ])->save();

        return back()->with('status', __('quotes.follow_up.scheduled'));
    }
}
