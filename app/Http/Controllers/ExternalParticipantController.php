<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipantController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\ExternalParticipant\{ExternalAbility, ExternalParty};
use App\Models\{DiaryEntry, Document, ExternalParticipant, Protocol, User};
use App\Services\ExternalParticipant\ExternalParticipantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Interne Verwaltung externer Beteiligter (Feature 033): Einladen,
 * Link-Anzeige (einmalig), Widerruf. Der öffentliche, login-freie Zugriff
 * läuft separat über den {@see PublicExternalParticipantController}.
 *
 * Das Subject (DiaryEntry|Protocol|Document) wird über type+Sqid aufgelöst —
 * analog AttachmentController::TYPE_MAP.
 */
class ExternalParticipantController extends Controller {
    /** @var array<string, class-string<Model>> */
    private const TYPE_MAP = [
        'diary' => DiaryEntry::class,
        'protocol' => Protocol::class,
        'document' => Document::class,
    ];

    public function __construct(private readonly ExternalParticipantService $service) {}

    /** Einladungs-Dialog (Modal). */
    public function create(string $type, string $id): View {
        $subject = $this->resolveSubject($type, $id);
        Gate::authorize('manageForSubject', [ExternalParticipant::class, $subject]);

        return view('external-participants._invite_dialog', [
            'type' => $type,
            'subjectId' => $id,
            'parties' => ExternalParty::selectable(),
            'abilities' => ExternalAbility::selectable(),
            'defaultTtl' => ExternalParticipantService::DEFAULT_TTL_DAYS,
        ]);
    }

    public function store(Request $request, string $type, string $id): RedirectResponse {
        $subject = $this->resolveSubject($type, $id);
        Gate::authorize('manageForSubject', [ExternalParticipant::class, $subject]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'role' => ['nullable', 'string', 'max:120'],
            'party' => ['required', 'string', 'in:' . implode(',', array_map(fn(ExternalParty $p) => $p->value, ExternalParty::cases()))],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:' . implode(',', array_map(fn(ExternalAbility $a) => $a->value, ExternalAbility::selectable()))],
            'ttl_days' => ['required', 'integer', 'min:' . ExternalParticipantService::MIN_TTL_DAYS, 'max:' . ExternalParticipantService::MAX_TTL_DAYS],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $issued = $this->service->invite($subject, $actor, [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'] ?? null,
            'party' => $data['party'],
            'abilities' => $data['abilities'] ?? [],
            'ttl_days' => (int) $data['ttl_days'],
        ]);

        return back()
            ->with('success', __('external.flash.invited', ['name' => $issued['model']->name]))
            ->with('external_participant_link', route('external.show', ['token' => $issued['token']]));
    }

    public function revoke(ExternalParticipant $participant): RedirectResponse {
        Gate::authorize('revoke', $participant);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->revoke($participant, $actor);

        return back()->with('success', __('external.flash.revoked'));
    }

    /**
     * Löst das Subject org-gescopt über type+Sqid auf. Fremde/unbekannte
     * Subjects ⇒ 404 (die Modelle sind über BelongsToOrganization gescopt).
     */
    private function resolveSubject(string $type, string $id): Model {
        $class = self::TYPE_MAP[$type] ?? abort(404);

        $prototype = new $class();
        $model = $prototype->resolveRouteBinding($id);
        abort_if(! $model instanceof Model, 404);

        return $model;
    }
}
