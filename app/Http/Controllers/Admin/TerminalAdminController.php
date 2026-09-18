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

use App\Enums\Attendance\CheckpointKind;
use App\Http\Controllers\Controller;
use App\Models\{AttendanceCheckpoint, AttendanceTerminal, Organization, Site, User, UserBadge, UserTerminalPin, Vehicle};
use App\Services\Attendance\TerminalPinService;
use App\Services\SqidEncoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use CommonToolkit\Helper\Data\DataUrlHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\{Rule, ValidationException};
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
            'issuedUrl' => is_array(session('terminal_issued')) ? (session('terminal_issued')['url'] ?? null) : null,
            'issuedKioskUrl' => is_array(session('terminal_issued')) ? (session('terminal_issued')['kiosk_url'] ?? null) : null,
            // Terminal-PINs (MVP-803): wer eine hat, und ob sie gesperrt ist.
            'pins' => UserTerminalPin::query()
                ->where('organization_id', $organization->id)
                ->with('user:id,name,personnel_number')
                ->get()
                ->sortBy(fn (UserTerminalPin $pin): string => (string) $pin->user?->name)
                ->values(),
            'checkpoints' => AttendanceCheckpoint::query()
                ->where('organization_id', $organization->id)
                ->with(['site:id,name', 'vehicle:id,label,license_plate'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** Dialog-Fragment: Terminal registrieren (data-entry-modal-trigger). */
    public function createTerminal(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $sqids = app(SqidEncoder::class);

        return view('admin.terminals._terminal_form_dialog', [
            'sites' => Site::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Site $s): array => ['sqid' => $sqids->encode(Site::class, (int) $s->id), 'name' => $s->name]),
        ]);
    }

    /** Dialog-Fragment: Badge zuordnen (data-entry-modal-trigger). */
    public function createBadge(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        $sqids = app(SqidEncoder::class);

        return view('admin.terminals._badge_form_dialog', [
            'users' => User::query()->where('organization_id', $organization->id)->whereNull('customer_id')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['sqid' => $sqids->encode(User::class, (int) $u->id), 'name' => $u->name]),
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
            ->with('terminal_issued', ['url' => route('api.terminal.ingest', ['token' => $plain]), 'kiosk_url' => route('kiosk.show', ['token' => $plain])])
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
            ->with('terminal_issued', ['url' => route('api.terminal.ingest', ['token' => $plain]), 'kiosk_url' => route('kiosk.show', ['token' => $plain])])
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

    /** Dialog-Fragment: Terminal-PIN setzen (MVP-803). */
    public function createPin(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.terminals._pin_form_dialog', [
            'users' => User::query()->where('organization_id', $organization->id)->whereNull('customer_id')
                ->whereNull('deactivated_at')->whereNotNull('personnel_number')->orderBy('name')->get(['id', 'name', 'personnel_number']),
        ]);
    }

    /** Setzt oder ersetzt die Terminal-PIN einer Person; gespeichert wird nur der Hash. */
    public function storePin(Request $request, TerminalPinService $pins): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'user' => ['required', 'string'],
            'pin' => ['required', 'string', 'confirmed'],
        ]);
        $pins->set($this->resolveUser($organization, (string) $data['user']), (string) $data['pin'], $admin);

        return back()->with('success', __('terminal.pin.flash.set'));
    }

    public function unlockPin(Request $request, TerminalPinService $pins): RedirectResponse {
        $admin = $this->admin();
        $pins->unlock($this->resolvePin($this->organization($admin), (string) $request->input('pin', '')), $admin);

        return back()->with('success', __('terminal.pin.flash.unlocked'));
    }

    public function removePin(Request $request, TerminalPinService $pins): RedirectResponse {
        $admin = $this->admin();
        $pins->remove($this->resolvePin($this->organization($admin), (string) $request->input('pin', '')), $admin);

        return back()->with('success', __('terminal.pin.flash.removed'));
    }

    private function resolvePin(Organization $organization, string $sqid): UserTerminalPin {
        $decoded = app(SqidEncoder::class)->decode(UserTerminalPin::class, $sqid);
        $pin = $decoded !== null
            ? UserTerminalPin::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($pin instanceof UserTerminalPin, 404);

        return $pin;
    }

    /** Dialog-Fragment: Check-in-Punkt anlegen (MVP-800). */
    public function createCheckpoint(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.terminals._checkpoint_form_dialog', [
            'sites' => Site::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->where('organization_id', $organization->id)->orderBy('license_plate')->get(['id', 'label', 'license_plate']),
        ]);
    }

    /** Legt einen Check-in-Punkt an; QR-Code und NFC-Adresse zeigt die Druckansicht. */
    public function storeCheckpoint(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(CheckpointKind::class)],
            'site' => ['nullable', 'string'],
            'vehicle' => ['nullable', 'required_if:kind,vehicle', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius_m' => ['nullable', 'integer', 'between:10,5000'],
        ]);

        $kind = CheckpointKind::from((string) $data['kind']);
        $siteId = $kind === CheckpointKind::Site ? $this->resolveSiteId($organization, $data['site'] ?? null) : null;
        $vehicleId = $kind === CheckpointKind::Vehicle ? $this->resolveVehicleId($organization, (string) ($data['vehicle'] ?? '')) : null;

        $checkpoint = new AttendanceCheckpoint([
            'organization_id' => $organization->id,
            'name' => (string) $data['name'],
            'kind' => $kind->value,
            'site_id' => $siteId,
            'vehicle_id' => $vehicleId,
            'token' => AttendanceCheckpoint::newToken(),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'radius_m' => $data['radius_m'] ?? null,
            'active' => true,
            'created_by' => $admin->id,
        ]);
        // Ein Radius ohne Mittelpunkt würde jeden Check-in abweisen.
        if ($checkpoint->requiresLocation() && $checkpoint->center() === null) {
            throw ValidationException::withMessages(['radius_m' => (string) __('terminal.checkpoint.error.radius_without_center')]);
        }
        $checkpoint->save();
        $checkpoint->audit('terminal.checkpoint_created', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('terminal.checkpoint.flash.created'));
    }

    /** Sperrt einen Check-in-Punkt oder gibt ihn wieder frei (verlorener Aufkleber). */
    public function toggleCheckpoint(Request $request): RedirectResponse {
        $admin = $this->admin();
        $checkpoint = $this->resolveCheckpoint($this->organization($admin), (string) $request->input('checkpoint', ''));

        $checkpoint->forceFill(['active' => ! $checkpoint->active])->save();
        $checkpoint->audit($checkpoint->active ? 'terminal.checkpoint_enabled' : 'terminal.checkpoint_disabled', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __($checkpoint->active ? 'terminal.checkpoint.flash.enabled' : 'terminal.checkpoint.flash.disabled'));
    }

    /** Druckansicht mit QR-Code; dieselbe Adresse lässt sich auf einen NFC-Aufkleber schreiben. */
    public function checkpointQr(string $checkpoint): View {
        $admin = $this->admin();
        $model = $this->resolveCheckpoint($this->organization($admin), $checkpoint);
        $url = route('checkin.show', ['token' => $model->token]);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd())))->writeString($url);

        return view('admin.terminals.checkpoint_qr', [
            'checkpoint' => $model,
            'url' => $url,
            'qrDataUri' => (string) DataUrlHelper::encode($svg, 'image/svg+xml'),
            'backUrl' => route('admin.terminals.index'),
        ]);
    }

    private function resolveCheckpoint(Organization $organization, string $sqid): AttendanceCheckpoint {
        $decoded = app(SqidEncoder::class)->decode(AttendanceCheckpoint::class, $sqid);
        $checkpoint = $decoded !== null
            ? AttendanceCheckpoint::query()->whereKey($decoded)->where('organization_id', $organization->id)->with(['site', 'vehicle'])->first()
            : null;
        abort_unless($checkpoint instanceof AttendanceCheckpoint, 404);

        return $checkpoint;
    }

    private function resolveVehicleId(Organization $organization, string $sqid): int {
        $decoded = app(SqidEncoder::class)->decode(Vehicle::class, $sqid);
        if ($decoded === null || ! Vehicle::query()->whereKey($decoded)->where('organization_id', $organization->id)->exists()) {
            throw ValidationException::withMessages(['vehicle' => (string) __('terminal.checkpoint.error.vehicle')]);
        }

        return $decoded;
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
