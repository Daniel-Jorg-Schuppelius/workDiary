<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNoticeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Tenders;

use App\Http\Controllers\Controller;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\Tenders\{TenderFilterProfile, TenderNoticeMatch};
use App\Models\User;
use App\Services\Tenders\TenderNoticeConverter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use RuntimeException;

/**
 * Bekanntmachungs-Radar (Feature 108, MVP-630): Treffer-Inbox und
 * Suchprofile.
 *
 * Der Radar entscheidet nichts — er legt vor. Was ein Profil gefunden hat,
 * landet als Vorschlag in der Inbox; ob daraus ein Vergabevorgang wird,
 * bleibt beim Menschen. Verworfene Treffer bleiben als Beleg erhalten und
 * verschwinden nur aus der Liste, damit derselbe Vorschlag nicht täglich
 * wiederkehrt.
 *
 * Rechte laufen über den Ausschreibungsbereich (`tender.*`): Wer die
 * Vergabeakte führen darf, führt auch den Radar.
 */
class TenderNoticeController extends Controller {
    private const STATES = [TenderNoticeMatch::STATE_NEW, TenderNoticeMatch::STATE_MUTED, TenderNoticeMatch::STATE_CONVERTED];

    public function index(Request $request): View {
        Gate::authorize('viewAny', ApplicationOpportunity::class);

        $state = $request->string('state')->toString();
        $state = in_array($state, self::STATES, true) ? $state : TenderNoticeMatch::STATE_NEW;

        $matches = TenderNoticeMatch::query()
            ->where('state', $state)
            ->with(['notice', 'profile', 'opportunity'])
            ->join('tender_notices', 'tender_notices.id', '=', 'tender_notice_matches.tender_notice_id')
            // Nächste Frist zuerst: Was in drei Tagen abzugeben ist, ist
            // dringender als eine ältere Bekanntmachung ohne Frist.
            ->orderByRaw('tender_notices.submission_deadline is null, tender_notices.submission_deadline asc')
            ->select('tender_notice_matches.*')
            ->paginate(25)
            ->withQueryString();

        return view('tenders.radar.index', [
            'matches' => $matches,
            'state' => $state,
            'counts' => $this->counts(),
            'profileCount' => TenderFilterProfile::query()->count(),
            'canManage' => Gate::allows('create', ApplicationOpportunity::class),
        ]);
    }

    /** Gesehen und verworfen: Der Treffer bleibt, taucht aber nicht mehr auf. */
    public function mute(TenderNoticeMatch $match): RedirectResponse {
        Gate::authorize('create', ApplicationOpportunity::class);
        $this->guard($match);

        if ($match->state === TenderNoticeMatch::STATE_NEW) {
            $match->forceFill(['state' => TenderNoticeMatch::STATE_MUTED])->save();
        }

        return back()->with('success', __('Bekanntmachung ausgeblendet.'));
    }

    public function restore(TenderNoticeMatch $match): RedirectResponse {
        Gate::authorize('create', ApplicationOpportunity::class);
        $this->guard($match);

        if ($match->state === TenderNoticeMatch::STATE_MUTED) {
            $match->forceFill(['state' => TenderNoticeMatch::STATE_NEW])->save();
        }

        return back()->with('success', __('Bekanntmachung wieder eingeblendet.'));
    }

    /** Übernahme in einen Vergabevorgang — mit dem, was die Bekanntmachung hergibt. */
    public function convert(TenderNoticeMatch $match, TenderNoticeConverter $converter): RedirectResponse {
        Gate::authorize('create', ApplicationOpportunity::class);
        $this->guard($match);

        try {
            $opportunity = $converter->convert($match, $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tenders.show', $opportunity)
            ->with('success', __('Bekanntmachung als Vergabevorgang übernommen — bitte Verfahrensart und Schwellenwert prüfen.'));
    }

    // ── Suchprofile ──────────────────────────────────────────────────────

    public function profiles(): View {
        Gate::authorize('viewAny', ApplicationOpportunity::class);

        return view('tenders.radar.profiles', [
            'profiles' => TenderFilterProfile::query()->withCount('matches')->orderBy('name')->get(),
            'canManage' => Gate::allows('create', ApplicationOpportunity::class),
        ]);
    }

    public function createProfile(): View {
        Gate::authorize('create', ApplicationOpportunity::class);

        return view('tenders.radar._form_dialog', ['profile' => new TenderFilterProfile(['active' => true])]);
    }

    public function editProfile(TenderFilterProfile $profile): View {
        Gate::authorize('create', ApplicationOpportunity::class);
        $this->guardProfile($profile);

        return view('tenders.radar._form_dialog', ['profile' => $profile]);
    }

    public function storeProfile(Request $request): RedirectResponse {
        Gate::authorize('create', ApplicationOpportunity::class);

        TenderFilterProfile::query()->create($this->validated($request) + [
            'organization_id' => $this->actor()->organization_id,
            'created_by' => $this->actor()->id,
        ]);

        return redirect()->route('tender-radar.profiles')->with('success', __('Suchprofil gespeichert.'));
    }

    public function updateProfile(Request $request, TenderFilterProfile $profile): RedirectResponse {
        Gate::authorize('create', ApplicationOpportunity::class);
        $this->guardProfile($profile);

        $profile->update($this->validated($request));

        return redirect()->route('tender-radar.profiles')->with('success', __('Suchprofil gespeichert.'));
    }

    public function destroyProfile(TenderFilterProfile $profile): RedirectResponse {
        Gate::authorize('create', ApplicationOpportunity::class);
        $this->guardProfile($profile);

        $profile->delete();

        return redirect()->route('tender-radar.profiles')->with('success', __('Suchprofil gelöscht.'));
    }

    /**
     * Codes und Stichwörter kommen als Freitext (eine Zeile oder mit Komma
     * getrennt) — das ist die Schreibweise, in der Vergabestellen ihre
     * CPV-Listen veröffentlichen.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
            'cpv_codes' => ['nullable', 'string', 'max:2000'],
            'nuts_codes' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'excluded_keywords' => ['nullable', 'string', 'max:2000'],
            'min_value' => ['nullable', 'numeric', 'min:0'],
            'max_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'active' => $request->boolean('active'),
            'cpv_codes' => $this->list($data['cpv_codes'] ?? null, upper: true),
            'nuts_codes' => $this->list($data['nuts_codes'] ?? null, upper: true),
            'keywords' => $this->list($data['keywords'] ?? null),
            'excluded_keywords' => $this->list($data['excluded_keywords'] ?? null),
            'min_value' => $data['min_value'] ?? null,
            'max_value' => $data['max_value'] ?? null,
        ];
    }

    /** @return list<string> */
    private function list(?string $raw, bool $upper = false): array {
        $parts = preg_split('/[\s,;]+/', (string) $raw) ?: [];
        $parts = array_filter(array_map(trim(...), $parts), static fn (string $p): bool => $p !== '');

        return array_values(array_unique(array_map(
            static fn (string $p): string => $upper ? strtoupper(str_replace('-', '', $p)) : $p,
            $parts
        )));
    }

    /** @return array<string, int> */
    private function counts(): array {
        /** @var array<string, int> $counts */
        $counts = TenderNoticeMatch::query()
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state')
            ->all();

        return $counts;
    }

    private function guard(TenderNoticeMatch $match): void {
        abort_unless($match->organization_id === $this->actor()->organization_id, 404);
    }

    private function guardProfile(TenderFilterProfile $profile): void {
        abort_unless($profile->organization_id === $this->actor()->organization_id, 404);
    }

    private function actor(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
