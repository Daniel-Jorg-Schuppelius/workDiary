<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Survey;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Mail\SurveyInvitationMail;
use App\Models\Customer\Customer;
use App\Models\Survey\{Survey, SurveyAnswer, SurveyQuestion};
use App\Services\Survey\SurveyService;
use App\Support\{ErrorText, SqidEncoder};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Mail};
use Illuminate\View\View;
use RuntimeException;

/**
 * Umfragen und Kundenfeedback (Feature 090, MVP-660–662).
 *
 * Rechte über customer.* (Kundenfeedback ist Kundenpflege), Modul
 * module.vertrieb. Die Ticket-CSAT bleibt unberührt daneben bestehen.
 */
class SurveyController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly SurveyService $service) {}

    public function index(): View {
        Gate::authorize(Permission::CustomerViewAny->value);

        return view('surveys.index', [
            'surveys' => Survey::query()
                ->withCount(['questions', 'invitations', 'responses'])
                ->orderByDesc('id')
                ->paginate(25),
            'canManage' => Gate::allows(Permission::CustomerUpdate->value),
        ]);
    }

    public function show(Survey $survey): View {
        Gate::authorize(Permission::CustomerViewAny->value);
        $this->assertSameOrganization($survey);

        return view('surveys.show', [
            'survey' => $survey->load('questions'),
            'nps' => $this->service->npsScore($survey),
            'npsTrend' => $this->service->npsByMonth($survey),
            'responseCount' => $survey->responses()->count(),
            'invitations' => $survey->invitations()->with('customer:id,name')->orderByDesc('id')->limit(50)->get(),
            'textAnswers' => SurveyAnswer::query()
                ->whereIn('survey_question_id', $survey->questions()->pluck('id'))
                ->whereNotNull('value_text')
                ->orderByDesc('id')->limit(50)->get(),
            'canManage' => Gate::allows(Permission::CustomerUpdate->value),
            'customers' => Customer::query()->where('survey_opt_out', false)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::CustomerUpdate->value);

        return view('surveys._form_dialog', ['survey' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'anonymous' => ['nullable', 'boolean'],
            'trigger_on_ticket_close' => ['nullable', 'boolean'],
            'trigger_on_order_completion' => ['nullable', 'boolean'],
            'trigger_schedule_days' => ['nullable', 'integer', 'min:7', 'max:730'],
        ]);

        $survey = Survey::query()->create([
            'organization_id' => $this->currentOrganizationId(),
            'title' => $data['title'],
            'purpose' => $data['purpose'] ?? null,
            'active' => true,
            'anonymous' => $request->boolean('anonymous'),
            'trigger_on_ticket_close' => $request->boolean('trigger_on_ticket_close'),
            'trigger_on_order_completion' => $request->boolean('trigger_on_order_completion'),
            'trigger_schedule_days' => $data['trigger_schedule_days'] ?? null,
            'created_by' => Auth::id(),
        ]);
        $survey->audit('survey.created', ['anonymous' => $survey->anonymous]);

        return redirect()->route('surveys.show', $survey)->with('success', __('Fragebogen angelegt — jetzt Fragen hinzufügen.'));
    }

    /** Auslöser ändern (MVP-1293): Ticket, Auftrag, Kurs, fester Abstand. */
    public function updateTriggers(Request $request, Survey $survey): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->assertSameOrganization($survey);

        $data = $request->validate([
            'trigger_on_ticket_close' => ['nullable', 'boolean'],
            'trigger_on_order_completion' => ['nullable', 'boolean'],
            'trigger_on_course_completion' => ['nullable', 'boolean'],
            'trigger_schedule_days' => ['nullable', 'integer', 'min:7', 'max:730'],
        ]);
        $survey->update([
            'trigger_on_ticket_close' => $request->boolean('trigger_on_ticket_close'),
            'trigger_on_order_completion' => $request->boolean('trigger_on_order_completion'),
            'trigger_on_course_completion' => $request->boolean('trigger_on_course_completion'),
            'trigger_schedule_days' => $data['trigger_schedule_days'] ?? null,
        ]);

        return back()->with('success', __('survey.triggers.saved'));
    }

    public function addQuestion(Request $request, Survey $survey): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->assertSameOrganization($survey);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', SurveyQuestion::TYPES)],
            'label' => ['required', 'string', 'max:500'],
            'options' => ['nullable', 'string', 'max:1000'],
            'required' => ['nullable', 'boolean'],
        ]);

        $options = null;
        if ($data['type'] === 'choice') {
            $options = array_values(array_filter(array_map(trim(...), explode(',', (string) ($data['options'] ?? '')))));
            abort_if($options === [], 422);
        }

        $survey->questions()->create([
            'organization_id' => $survey->organization_id,
            'type' => $data['type'],
            'label' => $data['label'],
            'options' => $options,
            'required' => $request->boolean('required', true),
            'position' => (int) $survey->questions()->max('position') + 1,
        ]);

        return back()->with('success', __('Frage hinzugefügt.'));
    }

    public function removeQuestion(Survey $survey, SurveyQuestion $question): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->assertSameOrganization($survey);
        abort_unless($question->survey_id === $survey->id, 404);

        // Fragen mit Antworten bleiben - sonst verlören die vorhandenen
        // Antworten ihre Bedeutung.
        abort_if(SurveyAnswer::query()->where('survey_question_id', $question->id)->exists(), 422);
        $question->delete();

        return back()->with('success', __('Frage entfernt.'));
    }

    /** Manuelle Einladung an einen Kunden. */
    public function invite(Request $request, Survey $survey, SqidEncoder $sqids): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->assertSameOrganization($survey);

        $data = $request->validate(['customer' => ['required', 'string']]);
        $customerId = $sqids->decode(Customer::class, (string) $data['customer']);
        abort_if($customerId === null, 422);
        $customer = Customer::query()->findOrFail($customerId);

        $email = trim((string) $customer->email);
        if ($email === '') {
            return back()->with('error', __('Dieser Kunde hat keine E-Mail-Adresse.'));
        }

        try {
            $issued = $this->service->invite($survey, $email, $customer);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        Mail::to($email)->send(new SurveyInvitationMail($survey, $issued['token']));

        return back()->with('success', __('Einladung an :email versendet.', ['email' => $email]));
    }

    public function toggleActive(Survey $survey): RedirectResponse {
        Gate::authorize(Permission::CustomerUpdate->value);
        $this->assertSameOrganization($survey);

        $survey->forceFill(['active' => ! $survey->active])->save();

        return back()->with('success', $survey->active ? __('Fragebogen aktiviert.') : __('Fragebogen deaktiviert.'));
    }
}
