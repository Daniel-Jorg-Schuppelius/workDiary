<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MedicalCheckupController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Safety;

use App\Enums\Safety\MedicalCheckupKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Safety\{MedicalCheckup, MedicalCheckupOccasion};
use App\Rules\ExistsInCurrentOrganization;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Vorsorge-Register (ArbMedVV, Feature 132): Liste mit Fälligkeitsspalte
 * und Modal-CRUD. Bewusst ohne Service — reine Stammdatenpflege ohne
 * Statusmaschine; das Register führt KEINE Gesundheitsdaten.
 */
class MedicalCheckupController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request): View {
        Gate::authorize('viewAny', MedicalCheckup::class);

        $kind = (string) $request->query('kind', '');
        $onlyDue = $request->query('due') === '1';

        $query = MedicalCheckup::query()
            ->with('user:id,name')
            ->orderByRaw('next_due_on is null')
            ->orderBy('next_due_on')
            ->orderByDesc('performed_on');

        if (MedicalCheckupKind::tryFrom($kind) instanceof MedicalCheckupKind) {
            $query->where('kind', $kind);
        }
        if ($onlyDue) {
            $query->due();
        }

        return view('safety.checkups.index', [
            'checkups' => $query->paginate(30)->withQueryString(),
            'kind' => $kind,
            'onlyDue' => $onlyDue,
            'dueCount' => MedicalCheckup::query()->due()->count(),
            'canManage' => Gate::allows('create', MedicalCheckup::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', MedicalCheckup::class);

        return view('safety.checkups._form_dialog', ['checkup' => null, 'users' => $this->userOptions(), 'occasions' => $this->occasionOptions(null)]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', MedicalCheckup::class);

        /** @var User $actor */
        $actor = Auth::user();

        MedicalCheckup::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'created_by_user_id' => $actor->id,
        ] + $this->validateCheckup($request));

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.checkup_created'));
    }

    public function edit(MedicalCheckup $checkup): View {
        Gate::authorize('update', $checkup);

        return view('safety.checkups._form_dialog', ['checkup' => $checkup, 'users' => $this->userOptions(), 'occasions' => $this->occasionOptions($checkup)]);
    }

    public function update(Request $request, MedicalCheckup $checkup): RedirectResponse {
        Gate::authorize('update', $checkup);

        $checkup->update($this->validateCheckup($request));

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.checkup_updated'));
    }

    public function destroy(MedicalCheckup $checkup): RedirectResponse {
        Gate::authorize('delete', $checkup);

        $checkup->delete();

        return redirect()
            ->route('safety.checkups.index')
            ->with('success', __('safety.register.flash.checkup_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCheckup(Request $request): array {
        if ($request->filled('user_id')) {
            $request->merge(['user_id' => Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);
        }
        if ($request->filled('medical_checkup_occasion_id')) {
            $request->merge(['medical_checkup_occasion_id' => Sqid::decodeOrNumeric(MedicalCheckupOccasion::class, $request->input('medical_checkup_occasion_id'))]);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', new ExistsInCurrentOrganization('users')],
            'medical_checkup_occasion_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('medical_checkup_occasions')],
            'kind' => ['required_without:medical_checkup_occasion_id', 'nullable', 'string', Rule::enum(MedicalCheckupKind::class)],
            'occasion' => ['nullable', 'string', 'max:180'],
            'performed_on' => ['required', 'date'],
            'next_due_on' => ['nullable', 'date', 'after:performed_on'],
            'certificate_on_file' => ['nullable', 'boolean'],
        ]);
        $data['certificate_on_file'] = (bool) ($data['certificate_on_file'] ?? false);

        // Anlass aus dem Katalog (MVP-986) bestimmt die Art; Anlasstext und Fälligkeit nur, wo nichts eingetragen ist.
        $template = isset($data['medical_checkup_occasion_id']) ? MedicalCheckupOccasion::query()->find($data['medical_checkup_occasion_id']) : null;
        if ($template instanceof MedicalCheckupOccasion) {
            $data['kind'] = $template->kind->value;
            $data['occasion'] = ($data['occasion'] ?? null) ?: $template->label;
            $data['next_due_on'] = ($data['next_due_on'] ?? null) ?: $template->nextDueFrom(CarbonImmutable::parse((string) $data['performed_on']))?->toDateString();
        }

        return $data;
    }

    /**
     * Aktive Anlässe, dazu der bereits gewählte, auch wenn er inzwischen deaktiviert ist.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MedicalCheckupOccasion>
     */
    private function occasionOptions(?MedicalCheckup $checkup): \Illuminate\Database\Eloquent\Collection {
        return MedicalCheckupOccasion::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $checkup?->medical_checkup_occasion_id))
            ->orderBy('label')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function userOptions() {
        return User::query()->inCurrentOrganization()->orderBy('name')->get(['id', 'name']);
    }
}
