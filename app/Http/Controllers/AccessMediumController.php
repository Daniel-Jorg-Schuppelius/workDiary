<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediumController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Access\{AccessMediumStatus, AccessMediumType};
use App\Enums\User\Permission;
use App\Models\{AccessMedium, Site, User};
use App\Services\Access\AccessMediumService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Zutritts- und Transponderverwaltung, Stufe 1 (Feature 092, MVP-657–659).
 *
 * Rechte über asset.* — Zutrittsmedien sind Dienstmittel wie Schlüssel; ein
 * eigener Rechtesatz spaltete dieselbe Verantwortung künstlich auf. Modul:
 * module.fuhrpark (wie assets.*).
 */
class AccessMediumController extends Controller {
    public function __construct(private readonly AccessMediumService $service) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::AssetView->value);

        $filters = [
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'q' => (string) $request->query('q', ''),
        ];

        $query = AccessMedium::query()->with(['site:id,name', 'holderUser:id,name', 'blockTask:id,status,due_date']);
        if (AccessMediumStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if (AccessMediumType::tryFrom($filters['type']) !== null) {
            $query->where('type', $filters['type']);
        }
        if (trim($filters['q']) !== '') {
            $term = trim($filters['q']);
            $query->where(function ($q) use ($term): void {
                $q->orWhereLikeEscaped('label', $term)
                    ->orWhereLikeEscaped('holder_name', $term)
                    ->orWhereLikeEscaped('system_name', $term)
                    ->orWhereLikeEscaped('number_suffix', $term);
            });
        }

        return view('access-media.index', [
            'media' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'counts' => AccessMedium::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'canManage' => Gate::allows(Permission::AssetUpdate->value),
        ]);
    }

    public function show(AccessMedium $accessMedium): View {
        Gate::authorize(Permission::AssetView->value);
        $this->guard($accessMedium);

        return view('access-media.show', [
            'medium' => $accessMedium->load(['site:id,name', 'holderUser:id,name', 'blockTask']),
            'handovers' => $accessMedium->handovers()->with('holderUser:id,name')->orderByDesc('occurred_at')->get(),
            'canManage' => Gate::allows(Permission::AssetUpdate->value),
            'users' => User::query()->where('organization_id', $this->orgId())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::AssetCreate->value);

        return view('access-media._form_dialog', [
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::AssetCreate->value);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'number' => ['required', 'string', 'min:4', 'max:64'],
            'label' => ['nullable', 'string', 'max:120'],
            'site' => ['nullable', 'string'],
            'system_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_if(AccessMediumType::tryFrom((string) $data['type']) === null, 422);

        $siteId = null;
        if (filled($data['site'] ?? null)) {
            $siteId = app(SqidEncoder::class)->decode(Site::class, (string) $data['site']);
            abort_if($siteId === null || ! Site::query()->whereKey($siteId)->exists(), 422);
        }

        $number = trim((string) $data['number']);
        $hash = AccessMedium::hashNumber($number);
        // Dieselbe Mediennummer darf je Organisation nur einmal existieren.
        abort_if(AccessMedium::query()->where('number_hash', $hash)->exists(), 422);

        $medium = AccessMedium::query()->create([
            'organization_id' => $this->orgId(),
            'type' => $data['type'],
            'number_hash' => $hash,
            'number_suffix' => mb_substr($number, -4),
            'label' => $data['label'] ?? null,
            'site_id' => $siteId,
            'system_name' => $data['system_name'] ?? null,
            'status' => AccessMediumStatus::InStock->value,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);
        $medium->audit('access_medium.created', ['type' => $medium->type->value]);

        // Die Nummer ist ab jetzt nur noch als Hash vorhanden - der Hinweis
        // sagt das ausdrücklich (Klartext nur im Ausgabemoment sichtbar).
        return redirect()->route('access-media.show', $medium)
            ->with('success', __('Medium angelegt. Die vollständige Nummer ist ab jetzt nur noch gehasht gespeichert (…:suffix).', ['suffix' => $medium->number_suffix]));
    }

    public function issue(Request $request, AccessMedium $accessMedium, SqidEncoder $sqids): RedirectResponse {
        Gate::authorize(Permission::AssetUpdate->value);
        $this->guard($accessMedium);

        $data = $request->validate([
            'holder_user' => ['nullable', 'string'],
            'holder_name' => ['nullable', 'string', 'max:160'],
            'holder_company' => ['nullable', 'string', 'max:160'],
            'expected_return_at' => ['nullable', 'date'],
            'signature_token' => ['nullable', 'string', 'max:64'],
        ]);

        $holderUserId = null;
        if (filled($data['holder_user'] ?? null)) {
            $holderUserId = $sqids->decode(User::class, (string) $data['holder_user']);
            abort_if($holderUserId === null || ! User::query()->whereKey($holderUserId)->where('organization_id', $this->orgId())->exists(), 422);
        }

        try {
            $this->service->issue($accessMedium, [
                'holder_user_id' => $holderUserId,
                'holder_name' => $data['holder_name'] ?? null,
                'holder_company' => $data['holder_company'] ?? null,
                'expected_return_at' => $data['expected_return_at'] ?? null,
                'signature_token' => $data['signature_token'] ?? null,
            ], $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Medium ausgegeben an :holder.', ['holder' => (string) $accessMedium->fresh()?->holderDisplay()]));
    }

    public function takeBack(Request $request, AccessMedium $accessMedium): RedirectResponse {
        Gate::authorize(Permission::AssetUpdate->value);
        $this->guard($accessMedium);

        try {
            $this->service->takeBack($accessMedium, $this->actor(), (string) $request->input('condition', '') ?: null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Medium zurückgenommen — es liegt wieder im Lager.'));
    }

    public function reportLost(Request $request, AccessMedium $accessMedium): RedirectResponse {
        Gate::authorize(Permission::AssetUpdate->value);
        $this->guard($accessMedium);

        try {
            $task = $this->service->reportLost($accessMedium, $this->actor(), (string) $request->input('note', '') ?: null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Verlust gemeldet. Sperr-Aufgabe angelegt: „:title" (fällig :due).', [
            'title' => $task->title,
            'due' => optional($task->due_date)->format('d.m.Y'),
        ]));
    }

    public function confirmBlocked(AccessMedium $accessMedium): RedirectResponse {
        Gate::authorize(Permission::AssetUpdate->value);
        $this->guard($accessMedium);

        try {
            $this->service->confirmBlocked($accessMedium, $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Sperrung in der Anlage bestätigt — der Nachweis hängt an der Sperr-Aufgabe.'));
    }

    public function retire(AccessMedium $accessMedium): RedirectResponse {
        Gate::authorize(Permission::AssetUpdate->value);
        $this->guard($accessMedium);

        try {
            $this->service->retire($accessMedium, $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Medium ausgemustert.'));
    }

    private function guard(AccessMedium $medium): void {
        abort_unless($medium->organization_id === $this->orgId(), 404);
    }

    private function orgId(): int {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        return (int) ($org->id ?? $this->actor()->organization_id);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
