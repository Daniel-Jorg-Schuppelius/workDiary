<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDossierController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Organization, Team, User};
use App\Services\Learning\{LearningDossierPdfRenderer, QualificationDossierService};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Nachweismappe (Feature 149, MVP-750): „war diese Person **am 14. März**
 * unterwiesen?" — nicht „ist sie es heute?".
 *
 * **Aggregiert ist die Vorgabe.** Die namentliche Ausprägung ist eine
 * Weitergabe personenbezogener Daten und muss deshalb ausdrücklich
 * angefordert werden; der Anlass wird mitgeschrieben. Wer sie ohne Anlass
 * anfordert, bekommt sie nicht.
 */
class LearningDossierController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly QualificationDossierService $dossier,
        private readonly LearningDossierPdfRenderer $pdf,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::LearningManage->value);

        $asOf = $this->asOf($request);
        $team = $this->team($request);
        $users = $this->users($team);
        $named = $this->namedRequested($request);

        $rows = $named ? $this->dossier->forUsers($users, $asOf) : [];

        if ($named) {
            $this->recordNamedAccess($request, $users->count(), $asOf);
        }

        return view('learning.dossier.index', [
            'asOf' => $asOf,
            'teams' => Team::query()->orderBy('name')->get(),
            'teamSqid' => $team?->sqid,
            'users' => $users,
            'named' => $named,
            'reason' => (string) $request->string('reason'),
            'rows' => $rows,
            'summary' => $this->dossier->coverageSummary($users, $asOf),
        ]);
    }

    /** Die Mappe als PDF — trägt Stichtag, Anlass und den Prüfhash. */
    public function pdf(Request $request): Response {
        Gate::authorize(Permission::LearningManage->value);

        $asOf = $this->asOf($request);
        $team = $this->team($request);
        $users = $this->users($team);
        $named = $this->namedRequested($request);

        if ($named) {
            $this->recordNamedAccess($request, $users->count(), $asOf);
        }

        $content = $this->pdf->output(
            $this->currentOrganization(),
            $users,
            $asOf,
            $named,
            (string) $request->string('reason'),
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="nachweismappe-' . $asOf->toDateString() . '.pdf"',
        ]);
    }

    /** Maschinenlesbar für Auditoren — reproduzierbar über den Hash. */
    public function json(Request $request): StreamedResponse {
        Gate::authorize(Permission::LearningManage->value);

        $asOf = $this->asOf($request);
        $users = $this->users($this->team($request));

        $this->recordNamedAccess($request, $users->count(), $asOf);

        $payload = $this->dossier->exportPayload($this->currentOrganization(), $users, $asOf);

        return response()->streamDownload(
            static function () use ($payload): void {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            },
            'nachweismappe-' . $asOf->toDateString() . '.json',
            ['Content-Type' => 'application/json']
        );
    }

    private function asOf(Request $request): Carbon {
        $value = (string) $request->string('as_of');

        // Ein unlesbarer Stichtag darf nicht stillschweigend zu „heute"
        // werden — dann stünde eine falsche Aussage im Nachweis.
        return $value !== '' ? Carbon::parse($value)->startOfDay() : Carbon::today();
    }

    private function team(Request $request): ?Team {
        $id = Sqid::decode(Team::class, (string) $request->string('team_id'));

        return $id !== null ? Team::query()->find($id) : null;
    }

    /** @return EloquentCollection<int, User> */
    private function users(?Team $team): EloquentCollection {
        // Auf die eigene Organisation gescopt — eine Nachweismappe, die
        // fremde Belegschaft mitzählt, wäre nicht nur falsch, sondern ein
        // Datenabfluss. Deaktivierte Personen gehören nicht in eine
        // Einsatzauskunft.
        $query = User::query()
            ->inCurrentOrganization()
            ->whereNull('deactivated_at')
            ->orderBy('name');

        if ($team !== null) {
            $query->whereHas('teams', fn ($q) => $q->whereKey($team->id));
        }

        return $query->get();
    }

    /** Namentlich nur mit Anlass — ohne Begründung bleibt es aggregiert. */
    private function namedRequested(Request $request): bool {
        return $request->boolean('named') && trim((string) $request->string('reason')) !== '';
    }

    /**
     * Eine namentliche Auskunft ist eine Weitergabe personenbezogener Daten
     * — wer sie wann und **warum** angefordert hat, gehört ins Protokoll.
     */
    private function recordNamedAccess(Request $request, int $people, Carbon $asOf): void {
        $organization = $this->currentOrganization();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => Auth::id(),
            'event' => 'learning.dossierDisclosed',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'reason' => trim((string) $request->string('reason')),
                'people' => $people,
                'as_of' => $asOf->toDateString(),
            ],
        ]);
    }
}
