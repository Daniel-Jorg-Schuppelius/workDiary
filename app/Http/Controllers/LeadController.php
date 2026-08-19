<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Enums\User\Permission;
use App\Models\{Customer, Lead, User};
use App\Services\Sales\LeadService;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Lead-Pipeline (Feature 091, MVP-654–656): Interessenten vor dem
 * Kundenstatus — erfassen, qualifizieren, konvertieren.
 *
 * Rechte: bewusst die Kunden-Permissions (customer.*) statt eines eigenen
 * Satzes — wer den Kundenstamm pflegen darf, pflegt auch die Vorstufe; ein
 * eigener Rechtesatz suggerierte eine Delegierbarkeit, die es fachlich nicht
 * gibt. Modul-Gate: module.vertrieb (config/plans.php).
 */
class LeadController extends Controller {
    public function __construct(private readonly LeadService $service) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::CustomerViewAny->value);

        $filters = [
            'status' => (string) $request->query('status', ''),
            'source' => (string) $request->query('source', ''),
            'q' => (string) $request->query('q', ''),
        ];

        $query = Lead::query()->with(['responsible:id,name', 'customer:id,name']);
        if (LeadStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if (LeadSource::tryFrom($filters['source']) !== null) {
            $query->where('source', $filters['source']);
        }
        if (trim($filters['q']) !== '') {
            $term = trim($filters['q']);
            $query->where(function ($q) use ($term): void {
                $q->orWhereLikeEscaped('company', $term)
                    ->orWhereLikeEscaped('contact_name', $term)
                    ->orWhereLikeEscaped('email', $term);
            });
        }

        return view('leads.index', [
            'leads' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'counts' => Lead::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'canManage' => Gate::allows(Permission::CustomerCreate->value),
        ]);
    }

    public function show(Lead $lead): View {
        Gate::authorize(Permission::CustomerView->value);
        $this->guard($lead);

        return view('leads.show', [
            'lead' => $lead->load(['responsible:id,name', 'customer:id,name']),
            'canManage' => Gate::allows(Permission::CustomerUpdate->value),
            // Kandidaten NUR im offenen Zustand - nach der Konvertierung gibt
            // es nichts mehr zu warnen.
            'duplicates' => $lead->status->isFinal() ? collect() : $this->service->duplicateCandidates($lead),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::CustomerCreate->value);

        return view('leads._form_dialog', ['lead' => null, 'users' => $this->assignableUsers()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::CustomerCreate->value);

        $data = $this->validated($request);
        $data['organization_id'] = $this->orgId();
        $data['created_by'] = Auth::id();
        $data['last_contact_at'] = now();

        $lead = Lead::query()->create($data);
        $lead->audit('lead.created', ['source' => $lead->source->value]);

        return redirect()->route('leads.show', $lead)->with('success', __('Lead angelegt.'));
    }

    public function edit(Lead $lead): View {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->guard($lead);

        return view('leads._form_dialog', ['lead' => $lead, 'users' => $this->assignableUsers()]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->guard($lead);
        abort_if($lead->anonymized_at !== null, 422);

        $lead->update($this->validated($request));

        return redirect()->route('leads.show', $lead)->with('success', __('Lead aktualisiert.'));
    }

    /** Statuswechsel entlang der Pipeline (inkl. Verwerfen mit Grund). */
    public function transition(Request $request, Lead $lead): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->guard($lead);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $to = LeadStatus::tryFrom((string) $data['status']);
        abort_if($to === null, 422);

        try {
            $this->service->transition($lead, $to, $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Lead ist jetzt: :status', ['status' => $to->label()]));
    }

    /**
     * Konvertierung: verbindet mit einem Bestandskunden ODER legt neu an.
     * Die Dublettenkandidaten standen vorher auf der Akte — wer hier ankommt,
     * hat sie gesehen.
     */
    public function convert(Request $request, Lead $lead, SqidEncoder $sqids): RedirectResponse {
        Gate::authorize(Permission::CustomerCreate->value);
        $this->guard($lead);

        $existing = null;
        $customerSqid = (string) $request->input('customer', '');
        if ($customerSqid !== '') {
            $customerId = $sqids->decode(Customer::class, $customerSqid);
            abort_if($customerId === null, 422);
            $existing = Customer::query()->findOrFail($customerId);
        }

        try {
            $customer = $this->service->convert($lead, $this->actor(), $existing);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', $existing !== null
                ? __('Lead mit Bestandskunde :name verbunden.', ['name' => $customer->name])
                : __('Lead konvertiert — Kunde :name angelegt.', ['name' => $customer->name]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $data = $request->validate([
            'company' => ['nullable', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:64'],
            'source' => ['required', 'string'],
            'interest' => ['nullable', 'string', 'max:5000'],
            'responsible_user' => ['nullable', 'string'],
        ]);
        abort_if(LeadSource::tryFrom((string) $data['source']) === null, 422);
        // Ohne Firma UND ohne Ansprechpartner gibt es keine Akte.
        abort_if(blank($data['company'] ?? null) && blank($data['contact_name'] ?? null), 422);

        $responsible = null;
        if (filled($data['responsible_user'] ?? null)) {
            $responsible = app(SqidEncoder::class)->decode(User::class, (string) $data['responsible_user']);
            abort_if($responsible === null || ! User::query()->whereKey($responsible)->where('organization_id', $this->orgId())->exists(), 422);
        }

        unset($data['responsible_user']);
        $data['responsible_user_id'] = $responsible;

        return $data;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function assignableUsers(): \Illuminate\Database\Eloquent\Collection {
        return User::query()->where('organization_id', $this->orgId())->orderBy('name')->get(['id', 'name']);
    }

    private function guard(Lead $lead): void {
        abort_unless($lead->organization_id === $this->orgId(), 404);
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
