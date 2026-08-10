<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QueryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Customer\CustomerQueryStatus;
use App\Http\Controllers\Controller;
use App\Models\{CustomerQuery, User};
use App\Services\Customer\CustomerQueryService;
use App\Services\CustomerPortal\PortalQuerySubjects;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Rückfragen/Kommentare im Kundenportal (MVP-512): read-only bleibt das
 * Portal — hier entsteht ausschließlich ein nachvollziehbarer
 * Frage-Antwort-Vorgang je ausdrücklich sichtbarem Subject
 * ({@see PortalQuerySubjects}). Nach dem Absenden ist der Text aus
 * Nachweisgründen nicht editierbar; eine Rücknahme ist eine Statusänderung.
 */
class QueryController extends Controller {
    public function __construct(
        private readonly PortalQuerySubjects $subjects,
        private readonly CustomerQueryService $service,
    ) {}

    /** Eigene Rückfragen des Kunden mit Antwort, Status und Subject-Kontext. */
    public function index(): View {
        $user = $this->portalUser();

        $queries = CustomerQuery::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('customer_id', $user->customer_id)
            ->with(['subject', 'answeredBy:id,name'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('customer.queries.index', [
            'queries' => $queries,
            'subjects' => $this->subjects,
        ]);
    }

    /** Formular (Subject über Query-Parameter vorbelegt). */
    public function create(Request $request): View {
        $user = $this->portalUser();

        $subject = $this->subjects->resolve(
            $user,
            (string) $request->query('subject_type', ''),
            (string) $request->query('subject', ''),
        );
        abort_if($subject === null, 404);

        return view('customer.queries.create', [
            'subject' => $subject,
            'subjectLabel' => $this->subjects->label($subject),
            'subjectType' => (string) $request->query('subject_type'),
            'subjectSqid' => (string) $request->query('subject'),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $user = $this->portalUser();

        $data = $request->validate([
            'subject_type' => ['required', 'string', 'max:32'],
            'subject' => ['required', 'string', 'max:64'],
            // Kein HTML: Ausgabe wird escaped, Eingabe hart begrenzt.
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $subject = $this->subjects->resolve($user, (string) $data['subject_type'], (string) $data['subject']);
        abort_if($subject === null, 404);

        if (trim((string) $data['question']) === '') {
            return back()->withErrors(['question' => (string) __('Die Rückfrage darf nicht leer sein.')]);
        }

        $this->service->raise($subject, [
            'organization_id' => (int) $user->organization_id,
            'customer_id' => (int) $user->customer_id,
            'asker_name' => $user->name,
            'asker_email' => $user->email,
            'question' => (string) $data['question'],
        ]);

        return redirect()->route('customer.queries.index')
            ->with('status', __('Ihre Rückfrage wurde übermittelt. Sie werden benachrichtigt, sobald eine Antwort vorliegt.'));
    }

    /** Rücknahme = protokollierte Statusänderung, kein spurloses Löschen. */
    public function withdraw(CustomerQuery $query): RedirectResponse {
        $user = $this->portalUser();

        abort_unless(
            (int) $query->organization_id === (int) $user->organization_id
            && (int) $query->customer_id === (int) $user->customer_id,
            404,
        );

        if ($query->status === CustomerQueryStatus::Open) {
            $this->service->close($query);
            $query->audit('portal.query.withdrawn', ['by_portal_user_id' => (int) $user->id]);
        }

        return redirect()->route('customer.queries.index')
            ->with('status', __('Die Rückfrage wurde zurückgezogen.'));
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 404);

        return $user;
    }
}
