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
use App\Mail\ExternalParticipantInvitedMail;
use App\Models\{DiaryEntry, Document, ExternalContact, ExternalParticipant, Protocol, User};
use App\Services\ExternalParticipant\ExternalParticipantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Mail};
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
        // Feature 075 (MVP-290): externe Prüfer/Prüfstellen liefern
        // Nachweise zu einem Prüftermin über den begrenzten Zugang.
        'inspection' => \App\Models\AssetCompliance\AssetInspectionSchedule::class,
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
            // Wiederverwendbare Kontaktprofile (Rang 30) zur Vorauswahl.
            'contacts' => ExternalContact::query()->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function store(Request $request, string $type, string $id): RedirectResponse {
        $subject = $this->resolveSubject($type, $id);
        Gate::authorize('manageForSubject', [ExternalParticipant::class, $subject]);

        $data = $request->validate([
            // Kontaktfelder sind bei gewähltem Profil optional (werden daraus gefüllt).
            'external_contact' => ['nullable', 'string'],
            'name' => ['nullable', 'required_without:external_contact', 'string', 'min:2', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'role' => ['nullable', 'string', 'max:120'],
            'party' => ['nullable', 'required_without:external_contact', 'string', 'in:' . implode(',', array_map(fn(ExternalParty $p) => $p->value, ExternalParty::cases()))],
            'save_contact' => ['nullable', 'boolean'],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:' . implode(',', array_map(fn(ExternalAbility $a) => $a->value, ExternalAbility::selectable()))],
            'ttl_days' => ['required', 'integer', 'min:' . ExternalParticipantService::MIN_TTL_DAYS, 'max:' . ExternalParticipantService::MAX_TTL_DAYS],
        ]);

        // Gewähltes Profil org-gescopt auflösen (HasSqid + BelongsToOrganization).
        $contact = null;
        if (($data['external_contact'] ?? '') !== '') {
            $contact = (new ExternalContact)->resolveRouteBinding((string) $data['external_contact']);
            abort_if(! $contact instanceof ExternalContact, 404);
        }

        // Effektive Werte: Profil füllt leere Felder, Ad-hoc-Eingabe gewinnt.
        $name = $data['name'] ?? null ?: $contact?->name;
        $email = ($data['email'] ?? null) ?: $contact?->email;
        $role = ($data['role'] ?? null) ?: $contact?->role;
        $party = $data['party'] ?? null ?: ($contact?->party->value ?? ExternalParty::Other->value);

        // Ad-hoc-Kontakt auf Wunsch als wiederverwendbares Profil speichern.
        if ($contact === null && $request->boolean('save_contact')) {
            $contact = ExternalContact::query()->create([
                'organization_id' => $subject->getAttribute('organization_id'),
                'name' => (string) $name,
                'email' => $email,
                'role' => $role,
                'party' => $party,
            ]);
        }

        /** @var User $actor */
        $actor = Auth::user();
        $issued = $this->service->invite($subject, $actor, [
            'name' => (string) $name,
            'email' => $email,
            'role' => $role,
            'party' => $party,
            'abilities' => $data['abilities'] ?? [],
            'ttl_days' => (int) $data['ttl_days'],
            'external_contact_id' => $contact?->id,
        ]);

        $participant = $issued['model'];
        $accessUrl = route('external.show', ['token' => $issued['token']]);

        // Einmal-Link zusätzlich per E-Mail zustellen, sofern eine Adresse
        // hinterlegt wurde (Rang 29) — und als externen Nachweis protokollieren.
        $emailed = false;
        if ($participant->email !== null && $participant->email !== '') {
            Mail::to($participant->email)->queue(new ExternalParticipantInvitedMail($participant, $accessUrl));
            $this->service->log($participant, 'invite_emailed', ['email' => $participant->email]);
            $emailed = true;
        }

        return back()
            ->with('success', __($emailed ? 'external.flash.invited_emailed' : 'external.flash.invited', ['name' => $participant->name]))
            ->with('external_participant_link', $accessUrl);
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
