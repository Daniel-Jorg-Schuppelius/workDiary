<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicExternalParticipantController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\ExternalParticipant\ExternalAbility;
use App\Models\{ExternalParticipant, Organization};
use App\Services\ExternalParticipant\ExternalParticipantService;
use App\Support\CarbonFmt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffentlicher, login-freier Zugriff externer Beteiligter (Feature 033).
 * Token-basiert OHNE Login und ohne Org-Session (Muster
 * {@see PublicProtocolSignatureController} / {@see PublicAuditPackageController}).
 *
 * Der Token wird über seinen SHA-256-Hash aufgelöst; widerrufene, abgelaufene
 * und unbekannte Tokens antworten einheitlich 404 (keine Detail-Preisgabe).
 * Jeder Zugriff setzt last_access_at und wird protokolliert. Die abilities
 * werden bei JEDER mutierenden Aktion serverseitig STRIKT geprüft (403 sonst).
 * Brute-Force-Schutz über throttle (Route).
 */
class PublicExternalParticipantController extends Controller {
    public function __construct(private readonly ExternalParticipantService $service) {}

    public function show(string $token): View|Response {
        $participant = $this->service->resolveUsable($token);
        abort_if($participant === null, 404);

        $subject = $this->bindSubject($participant);

        $this->service->registerAccess($participant);

        return view('public.external-participant', [
            'token' => $token,
            'participant' => $participant,
            'subject' => $subject,
            'context' => $this->buildContext($subject),
        ]);
    }

    public function comment(Request $request, string $token): RedirectResponse|Response {
        $participant = $this->service->resolveUsable($token);
        abort_if($participant === null, 404);
        abort_unless($participant->can(ExternalAbility::Comment), 403);

        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);

        $subject = $this->bindSubject($participant);
        $this->service->addComment($participant, $subject, $data['body']);

        return redirect()
            ->route('external.show', ['token' => $token])
            ->with('success', __('external.public.comment_saved'));
    }

    public function upload(Request $request, string $token): RedirectResponse|Response {
        $participant = $this->service->resolveUsable($token);
        abort_if($participant === null, 404);
        abort_unless($participant->can(ExternalAbility::Upload), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:' . (ExternalParticipantService::MAX_UPLOAD_BYTES / 1024)],
        ]);

        $subject = $this->bindSubject($participant);
        $file = $request->file('file');
        abort_if($file === null || is_array($file), 422);

        $result = $this->service->addUpload($participant, $subject, $file);
        if ($result['ok'] !== true) {
            return back()->withErrors(['file' => __('external.public.upload_rejected')]);
        }

        return redirect()
            ->route('external.show', ['token' => $token])
            ->with('success', __('external.public.upload_saved'));
    }

    public function confirm(Request $request, string $token): RedirectResponse|Response {
        $participant = $this->service->resolveUsable($token);
        abort_if($participant === null, 404);
        abort_unless($participant->can(ExternalAbility::Confirm), 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'accept' => ['accepted'],
        ]);

        $this->bindSubject($participant);
        $this->service->confirm($participant, (string) ($data['note'] ?? ''));

        return redirect()
            ->route('external.show', ['token' => $token])
            ->with('success', __('external.public.confirmed'));
    }

    /**
     * Lädt das Subject scope-frei (kein Org-Kontext im öffentlichen Zugriff)
     * und bindet die Org für die Zeitzonen-Auflösung der Read-Only-Seite.
     */
    private function bindSubject(ExternalParticipant $participant): Model {
        /** @var class-string<Model> $class */
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($participant->subject_type) ?? $participant->subject_type;

        $subject = $class::query()->withoutGlobalScopes()->whereKey($participant->subject_id)->first();
        abort_if(! $subject instanceof Model, 404);

        $orgId = $subject->getAttribute('organization_id');
        if (! empty($orgId)) {
            $org = Organization::query()->withoutGlobalScopes()->find($orgId);
            if ($org instanceof Organization) {
                app()->instance('currentOrganization', $org);
            }
        }

        return $subject;
    }

    /**
     * Datensparsame, kontextbezogene Darstellung des Subjects — bewusst NUR
     * Titel/Status/Datum, KEINE internen Notizen oder vertraulichen Felder.
     *
     * @return array{title: string, meta: string, summary: ?string}
     */
    private function buildContext(Model $subject): array {
        return match (true) {
            $subject instanceof \App\Models\DiaryEntry => [
                'title' => ((string) $subject->title) !== '' ? (string) $subject->title : __('external.subject.order') . ' #' . $subject->id,
                'meta' => $subject->start_at !== null ? CarbonFmt::fdatetime($subject->start_at) : '',
                'summary' => \CommonToolkit\Helper\Data\StringHelper::truncate((string) $subject->content, 280),
            ],
            $subject instanceof \App\Models\Protocol => [
                'title' => (string) $subject->title,
                'meta' => CarbonFmt::fdatetime($subject->occurred_at),
                'summary' => \CommonToolkit\Helper\Data\StringHelper::truncate((string) $subject->description, 280),
            ],
            $subject instanceof \App\Models\Document => [
                'title' => (string) $subject->title,
                'meta' => $subject->created_at !== null ? CarbonFmt::fdatetime($subject->created_at) : '',
                'summary' => null,
            ],
            // Feature 075 (MVP-290): Prüftermin für externe Prüfstellen —
            // nur Asset/Prüfart/Fälligkeit, keine internen Daten.
            $subject instanceof \App\Models\AssetCompliance\AssetInspectionSchedule => [
                'title' => __('Prüftermin: :asset', ['asset' => $subject->asset()->withoutGlobalScopes()->first()->name ?? '—']),
                'meta' => CarbonFmt::fdate($subject->due_on),
                'summary' => $subject->assignment()->withoutGlobalScopes()->first()?->profile()->first()?->name,
            ],
            default => ['title' => __('external.subject.generic'), 'meta' => '', 'summary' => null],
        };
    }
}
