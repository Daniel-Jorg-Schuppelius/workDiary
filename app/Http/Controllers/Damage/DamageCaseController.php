<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCaseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Damage;

use App\Enums\Damage\{DamageCaseStatus, DamageKind};
use App\Http\Controllers\Controller;
use App\Models\Contracts\DamageCaseSubject;
use App\Models\Damage\DamageCase;
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Damage\DamageCaseService;
use App\Support\{ErrorText, MorphMap, Sqid, Tz};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Schadensfälle (MVP-919): Liste, Akte, Anlage am Träger, Pflege und
 * Statuswechsel. Angelegt wird immer an einem Träger, den die Person sehen darf.
 */
class DamageCaseController extends Controller {
    public function __construct(private readonly DamageCaseService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', DamageCase::class);

        $status = DamageCaseStatus::tryFrom($request->string('status')->toString());
        $kind = DamageKind::tryFrom($request->string('kind')->toString());
        $subjectTypes = array_map(static fn (string $class): string => MorphMap::alias($class), DamageCaseService::SUBJECTS);
        $subjectType = in_array($request->string('subject_type')->toString(), $subjectTypes, true) ? $request->string('subject_type')->toString() : null;
        $open = [DamageCaseStatus::Reported->value, DamageCaseStatus::Submitted->value, DamageCaseStatus::InReview->value];

        return view('damage.index', [
            'cases' => DamageCase::query()
                ->with('subject')
                ->when($status, fn ($q, DamageCaseStatus $s) => $q->where('status', $s->value))
                ->when($kind, fn ($q, DamageKind $k) => $q->where('kind', $k->value))
                ->when($subjectType !== null, fn ($q) => $q->where('subject_type', $subjectType))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'subjectTypes' => $subjectTypes,
            'openCount' => DamageCase::query()->whereIn('status', $open)->count(),
            'openEstimate' => (string) DamageCase::query()->whereIn('status', $open)->sum('estimated_amount'),
            'settledTotal' => (string) DamageCase::query()->whereIn('status', [DamageCaseStatus::Settled->value, DamageCaseStatus::Closed->value])->sum('settled_amount'),
        ]);
    }

    public function show(DamageCase $damageCase): View {
        Gate::authorize('view', $damageCase);

        $damageCase->load(['subject', 'responsible', 'journal.actor', 'attachments']);

        return view('damage.show', ['case' => $damageCase]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', DamageCase::class);
        $subject = $this->subject($request->string('subject_type')->toString(), $request->string('subject')->toString());

        return view('damage._form_dialog', [
            'case' => null,
            'subject' => $subject,
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', DamageCase::class);
        $subject = $this->subject($request->string('subject_type')->toString(), $request->string('subject')->toString());

        $case = $this->service->open($subject, $this->validated($request), $request->user() ?? abort(401));

        return redirect()->route('damage-cases.show', $case)->with('success', __('damage.flash.opened', ['number' => (string) $case->number]));
    }

    public function edit(DamageCase $damageCase): View {
        Gate::authorize('update', $damageCase);

        return view('damage._form_dialog', [
            'case' => $damageCase,
            'subject' => $damageCase->subject,
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, DamageCase $damageCase): RedirectResponse {
        Gate::authorize('update', $damageCase);

        $this->service->update($damageCase, $this->validated($request), $request->user() ?? abort(401));

        return redirect()->route('damage-cases.show', $damageCase)->with('success', __('damage.flash.saved'));
    }

    public function transition(Request $request, DamageCase $damageCase): RedirectResponse {
        Gate::authorize('update', $damageCase);

        $data = $request->validate([
            'status' => ['required', Rule::enum(DamageCaseStatus::class)],
            'settled_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'claim_number' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->transition($damageCase, DamageCaseStatus::from((string) $data['status']), $request->user() ?? abort(401), [
                'settled_amount' => isset($data['settled_amount']) ? (string) $data['settled_amount'] : null,
                'claim_number' => $data['claim_number'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('damage.flash.status', ['status' => DamageCaseStatus::from((string) $data['status'])->label()]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        if ($request->filled('responsible_user_id')) {
            $request->merge(['responsible_user_id' => Sqid::decodeOrNumeric(User::class, $request->string('responsible_user_id')->toString())]);
        }

        $data = $request->validate([
            'kind' => ['required', Rule::enum(DamageKind::class)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'insurer_name' => ['nullable', 'string', 'max:160'],
            'policy_number' => ['nullable', 'string', 'max:80'],
            'claim_number' => ['nullable', 'string', 'max:80'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'deductible_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', Rule::enum(CurrencyCode::class)],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
        ]);
        // datetime-local ist Ortszeit der Organisation; gespeichert wird UTC.
        if (! empty($data['occurred_at'])) {
            $data['occurred_at'] = Tz::parse((string) $data['occurred_at'])->utc();
        }

        return $data;
    }

    /** Träger aus Morph-Alias und Sqid; nur Träger mit Schadensfällen, die die Person sehen darf. */
    private function subject(string $type, string $sqid): Model&DamageCaseSubject {
        $class = MorphMap::classFor($type);
        abort_unless(is_string($class) && is_subclass_of($class, DamageCaseSubject::class) && is_subclass_of($class, Model::class), 404);
        $id = Sqid::decode($class, $sqid);
        abort_if($id === null, 404);
        /** @var (Model&DamageCaseSubject)|null $subject */
        $subject = $class::query()->find($id);
        abort_unless($subject instanceof DamageCaseSubject, 404);
        Gate::authorize('view', $subject);

        return $subject;
    }
}
