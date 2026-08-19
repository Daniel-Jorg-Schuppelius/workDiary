<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{AppointmentRequest, BookableService, Site, User};
use App\Services\Appointments\AppointmentRequestService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Dispositions-Inbox der Terminanfragen (Feature 087, MVP-667) + Pflege der
 * buchbaren Leistungsarten. Rechte: dispatch.* (wer den Dienstplan führt,
 * entscheidet über Termine), Modul module.planung.
 */
class AppointmentInboxController extends Controller {
    public function index(): View {
        Gate::authorize(Permission::DispatchViewAny->value);

        return view('appointments.index', [
            'requests' => AppointmentRequest::query()
                ->where('status', AppointmentRequest::STATUS_REQUESTED)
                ->with(['customer:id,name', 'bookableService:id,title'])
                ->orderBy('start_at')
                ->get(),
            'decided' => AppointmentRequest::query()
                ->whereIn('status', [AppointmentRequest::STATUS_CONFIRMED, AppointmentRequest::STATUS_DECLINED, AppointmentRequest::STATUS_CANCELED])
                ->with(['customer:id,name', 'bookableService:id,title'])
                ->orderByDesc('decided_at')
                ->limit(20)
                ->get(),
            'services' => BookableService::query()->orderBy('title')->get(),
            'canManage' => Gate::allows(Permission::DispatchManage->value),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'qualifications' => \App\Models\Qualification::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function confirm(AppointmentRequest $appointmentRequest, AppointmentRequestService $service): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->guard($appointmentRequest);

        try {
            $entry = $service->confirm($appointmentRequest, $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Termin bestätigt — Eintrag am :date angelegt.', [
            'date' => $entry->start_at?->format('d.m.Y H:i') ?? '—',
        ]));
    }

    public function decline(Request $request, AppointmentRequest $appointmentRequest, AppointmentRequestService $service): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        $this->guard($appointmentRequest);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $service->decline($appointmentRequest, $this->actor(), (string) $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Anfrage abgelehnt.'));
    }

    /** Buchbare Leistungsart anlegen (kuratiert — nichts ist automatisch buchbar). */
    public function storeService(Request $request, SqidEncoder $sqids): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'lead_time_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'cancel_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'site' => ['nullable', 'string'],
            'qualification' => ['nullable', 'string'],
        ]);

        $qualificationId = null;
        if (filled($data['qualification'] ?? null)) {
            $qualificationId = $sqids->decode(\App\Models\Qualification::class, (string) $data['qualification']);
            abort_if($qualificationId === null || ! \App\Models\Qualification::query()->whereKey($qualificationId)->exists(), 422);
        }

        $siteId = null;
        if (filled($data['site'] ?? null)) {
            $siteId = $sqids->decode(Site::class, (string) $data['site']);
            abort_if($siteId === null || ! Site::query()->whereKey($siteId)->exists(), 422);
        }

        $service = BookableService::query()->create([
            'organization_id' => $this->orgId(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => (int) $data['duration_minutes'],
            'lead_time_hours' => (int) $data['lead_time_hours'],
            'cancel_hours' => (int) $data['cancel_hours'],
            'buffer_minutes' => (int) $data['buffer_minutes'],
            'site_id' => $siteId,
            'required_qualification_id' => $qualificationId,
            'active' => true,
            'created_by' => Auth::id(),
        ]);
        $service->audit('appointment.service_created', []);

        return back()->with('success', __('Leistungsart „:title" ist jetzt buchbar.', ['title' => $service->title]));
    }

    public function toggleService(BookableService $bookableService): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        abort_unless($bookableService->organization_id === $this->orgId(), 404);

        $bookableService->forceFill(['active' => ! $bookableService->active])->save();

        return back()->with('success', $bookableService->active ? __('Leistungsart aktiviert.') : __('Leistungsart deaktiviert.'));
    }

    private function guard(AppointmentRequest $request): void {
        abort_unless($request->organization_id === $this->orgId(), 404);
    }

    private function orgId(): int {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        /** @var User $user */
        $user = Auth::user();

        return (int) ($org->id ?? $user->organization_id);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
