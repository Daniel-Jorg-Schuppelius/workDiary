<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TerminalAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AttendanceTerminal, Organization, Site, User, UserBadge};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin-Verwaltung der Hardware-Stempelterminals (Feature 061, MVP-130):
 * Terminals registrieren/sperren (Gerätetoken einmalig als Teil der Ingest-URL)
 * und RFID-/NFC-Badges Nutzern zuordnen/sperren. Weder Gerätetoken noch
 * Badge-Kennung erscheinen im Klartext in Views/Audit; `last_seen_at` zeigt den
 * Gesundheitsstatus (Terminalausfall).
 */
class TerminalAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $sqids = app(SqidEncoder::class);

        return view('admin.terminals.index', [
            'terminals' => AttendanceTerminal::query()
                ->where('organization_id', $organization->id)
                ->with('site:id,name')
                ->orderBy('name')
                ->get(),
            'badges' => UserBadge::query()
                ->where('organization_id', $organization->id)
                ->with('user:id,name')
                ->orderByDesc('id')
                ->get(),
            'sites' => Site::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Site $s): array => ['sqid' => $sqids->encode(Site::class, (int) $s->id), 'name' => $s->name]),
            'users' => User::query()->where('organization_id', $organization->id)->whereNull('customer_id')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['sqid' => $sqids->encode(User::class, (int) $u->id), 'name' => $u->name]),
            'issuedUrl' => is_array(session('terminal_issued')) ? (session('terminal_issued')['url'] ?? null) : null,
        ]);
    }

    /** Registriert ein Terminal; die Ingest-URL (mit Token) wird einmalig geflasht. */
    public function storeTerminal(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'site' => ['nullable', 'string'],
        ]);

        $siteId = $this->resolveSiteId($organization, $data['site'] ?? null);
        [$terminal, $plain] = AttendanceTerminal::issue($organization->id, (string) $data['name'], $siteId, (int) $admin->id);
        $terminal->audit('terminal.registered', ['by_user_id' => (int) $admin->id]);

        return back()
            ->with('terminal_issued', ['url' => route('api.terminal.ingest', ['token' => $plain])])
            ->with('success', __('terminal.flash.registered'));
    }

    /** MVP-516: Gerätetoken rotieren — neue Ingest-URL wird einmalig geflasht. */
    public function rotateTerminal(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $terminal = $this->resolveTerminal($organization, (string) $request->input('terminal', ''));
        $plain = $terminal->rotate();
        $terminal->audit('terminal.token_rotated', ['by_user_id' => (int) $admin->id]);

        return back()
            ->with('terminal_issued', ['url' => route('api.terminal.ingest', ['token' => $plain])])
            ->with('success', __('terminal.flash.token_rotated'));
    }

    /** MVP-516: Status-Anzeige (Saldo/Resturlaub) je Terminal umschalten — Standard AUS. */
    public function toggleStatus(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $terminal = $this->resolveTerminal($organization, (string) $request->input('terminal', ''));
        $terminal->forceFill(['show_status' => ! $terminal->show_status])->save();
        $terminal->audit('terminal.status_display_toggled', ['enabled' => (bool) $terminal->show_status, 'by_user_id' => (int) $admin->id]);

        return back()->with('success', __($terminal->show_status ? 'terminal.flash.status_enabled' : 'terminal.flash.status_disabled'));
    }

    /** Sperrt ein Terminal (Ingest wird nicht mehr angenommen). */
    public function disconnectTerminal(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $terminal = $this->resolveTerminal($organization, (string) $request->input('terminal', ''));
        if ($terminal->active) {
            $terminal->forceFill(['active' => false])->save();
            $terminal->audit('terminal.disabled', ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('terminal.flash.terminal_disabled'));
    }

    /** Ordnet einem Nutzer eine Badge-Kennung zu (nur der Hash wird gespeichert). */
    public function storeBadge(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'user' => ['required', 'string'],
            'badge_uid' => ['required', 'string', 'max:190'],
            'label' => ['nullable', 'string', 'max:120'],
            // MVP-516: optionaler Gültigkeitszeitraum (z. B. befristete Kräfte).
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $user = $this->resolveUser($organization, (string) $data['user']);
        $hash = UserBadge::hashBadge((string) $data['badge_uid']);

        $exists = UserBadge::query()
            ->where('organization_id', $organization->id)
            ->where('badge_hash', $hash)
            ->whereNull('revoked_at')
            ->exists();
        if ($exists) {
            return back()->with('error', __('terminal.flash.badge_taken'));
        }

        $badge = UserBadge::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'label' => filled($data['label'] ?? null) ? (string) $data['label'] : null,
            'badge_hash' => $hash,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'created_by' => $admin->id,
        ]);
        $badge->audit('terminal.badge_assigned', ['by_user_id' => (int) $admin->id, 'user_id' => (int) $user->id]);

        return back()->with('success', __('terminal.flash.badge_assigned'));
    }

    /** Sperrt einen Badge (Verlust); die Historie bleibt erhalten. */
    public function revokeBadge(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $decoded = app(SqidEncoder::class)->decode(UserBadge::class, (string) $request->input('badge', ''));
        $badge = $decoded !== null
            ? UserBadge::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($badge instanceof UserBadge, 404);

        if ($badge->revoked_at === null) {
            $badge->forceFill(['revoked_at' => Carbon::now()])->save();
            $badge->audit('terminal.badge_revoked', ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('terminal.flash.badge_revoked'));
    }

    private function resolveTerminal(Organization $organization, string $sqid): AttendanceTerminal {
        $decoded = app(SqidEncoder::class)->decode(AttendanceTerminal::class, $sqid);
        $terminal = $decoded !== null
            ? AttendanceTerminal::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($terminal instanceof AttendanceTerminal, 404);

        return $terminal;
    }

    private function resolveUser(Organization $organization, string $sqid): User {
        $decoded = app(SqidEncoder::class)->decode(User::class, $sqid);
        $user = $decoded !== null
            ? User::query()->whereKey($decoded)->where('organization_id', $organization->id)->whereNull('customer_id')->first()
            : null;
        abort_unless($user instanceof User, 404);

        return $user;
    }

    private function resolveSiteId(Organization $organization, ?string $sqid): ?int {
        if (! filled($sqid)) {
            return null;
        }
        $decoded = app(SqidEncoder::class)->decode(Site::class, (string) $sqid);

        return $decoded !== null && Site::query()->whereKey($decoded)->where('organization_id', $organization->id)->exists()
            ? $decoded
            : null;
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
