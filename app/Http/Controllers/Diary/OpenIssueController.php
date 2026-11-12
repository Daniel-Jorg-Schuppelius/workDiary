<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Diary;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueVisibility};
use App\Exceptions\InvalidOpenIssueTransitionException;
use App\Http\Controllers\Concerns\AuthorizesCarrier;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpenIssue\{AssignOpenIssueRequest, StoreOpenIssueRequest, TransitionOpenIssueRequest, UpdateOpenIssueRequest};
use App\Models\Asset\Asset;
use App\Models\Attachments\Attachment;
use App\Models\Communication\Comment;
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Diary\Contracts\IssueTicketOpener;
use App\Services\OpenIssue\OpenIssueService;
use App\Support\ErrorText;
use App\Support\{Setting, Sqid, Tz};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Arr, Carbon};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;
use InvalidArgumentException;

class OpenIssueController extends Controller {
    use AuthorizesCarrier;
    /**
     * Whitelist der erlaubten Subject-Typen. Verhindert, dass Aufrufer beliebige
     * Klassen an `subject_type` setzen können (auch von
     * {@see StoreOpenIssueRequest} referenziert).
     *
     * @var array<string, class-string<Model>>
     */
    public const array SUBJECT_MAP = [
        'diary' => DiaryEntry::class,
        'project' => Project::class,
        'customer' => Customer::class,
        'asset' => Asset::class,
    ];

    public function __construct(
        private readonly OpenIssueService $service,
    ) {}

    public function create(Request $request): View {
        Gate::authorize('create', OpenIssue::class);

        $subjectKind = (string) $request->query('subject_kind', '');
        if (! array_key_exists($subjectKind, self::SUBJECT_MAP)) {
            abort(404);
        }

        $subjectClass = self::SUBJECT_MAP[$subjectKind];
        $rawSubjectId = (string) $request->query('subject_id', '');
        $subjectId = Sqid::decodeOrNumeric($subjectClass, $rawSubjectId);
        if ($subjectId < 1 || $subjectClass::query()->whereKey($subjectId)->doesntExist()) {
            abort(404);
        }

        return view('open-issues._form_dialog', [
            'subjectKind' => $subjectKind,
            'subjectId' => Sqid::encode($subjectClass, $subjectId),
            'canPublishToCustomer' => Gate::allows('publishToCustomer', OpenIssue::class),
            'users' => Gate::allows('assign', OpenIssue::class)
                ? User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /** Detaildialog (MVP-1597): Felder, Verlauf, Kommentare, Anhänge. */
    public function show(OpenIssue $issue): View {
        Gate::authorize('view', $issue);

        return view('open-issues._show_dialog', [
            'issue' => $issue->load(['assignee:id,name', 'creator:id,name', 'journal.actor:id,name', 'comments.user:id,name', 'attachments.uploader:id,name']),
            'canUpload' => Gate::allows('update', $issue) && Gate::allows('create', Attachment::class) && ! $issue->attachmentsLocked(),
        ]);
    }

    public function comment(Request $request, OpenIssue $issue): RedirectResponse {
        Gate::authorize('view', $issue);
        Gate::authorize('create', Comment::class);

        $body = $request->validate([
            'body' => ['required', 'string', 'max:' . (int) Setting::get('validation.comment.body_max', 5000)],
        ])['body'];

        $this->service->addComment($issue, $this->authUser(), $body);

        return redirect()
            ->back()
            ->with('success', __('Kommentar gespeichert.'))
            ->withFragment('open-issue-' . $issue->id);
    }

    /** Titel, Beschreibung, Kategorie sowie Frist, Schwere und Sichtbarkeit bearbeiten. */
    public function edit(OpenIssue $issue): View {
        Gate::authorize('update', $issue);

        return view('open-issues._edit_dialog', [
            'issue' => $issue,
            'canPublishToCustomer' => Gate::allows('publishToCustomer', OpenIssue::class),
        ]);
    }

    /** Zuweisung ändern oder aufheben. */
    public function assignForm(OpenIssue $issue): View {
        Gate::authorize('assign', OpenIssue::class);

        return view('open-issues._assign_dialog', [
            'issue' => $issue->loadMissing('assignee'),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function transitionForm(OpenIssue $issue, string $action): View {
        Gate::authorize('update', $issue);

        return view('open-issues._transition_dialog', [
            'issue' => $issue,
            'action' => $action,
            'requiresResolution' => $action === 'complete',
        ]);
    }

    public function store(StoreOpenIssueRequest $request): RedirectResponse {
        Gate::authorize('create', OpenIssue::class);

        $data = $request->validated();

        if (($data['visibility'] ?? OpenIssueVisibility::Internal->value) === OpenIssueVisibility::Customer->value) {
            Gate::authorize('publishToCustomer', OpenIssue::class);
        }

        if (! empty($data['assignee_user_id'])) {
            Gate::authorize('assign', OpenIssue::class);
        }

        $subjectClass = self::SUBJECT_MAP[$data['subject_kind']];
        $decodedSubjectId = Sqid::decodeOrNumeric($subjectClass, (string) $data['subject_id']);
        if ($decodedSubjectId === null || $decodedSubjectId < 1) {
            abort(404);
        }
        /** @var Model|null $subject */
        $subject = $subjectClass::query()->find($decodedSubjectId);
        if ($subject === null) {
            abort(404);
        }
        $this->authorizeCarrier($subject);

        /** @var User $creator */
        $creator = Auth::user();

        $issue = $this->service->create($subject, $creator, [
            'source_type' => OpenIssueSource::Manual->value,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'severity' => $data['severity'] ?? OpenIssueSeverity::Low->value,
            'assignee_user_id' => $data['assignee_user_id'] ?? null,
            'due_at' => filled($data['due_at'] ?? null) ? Tz::parse($data['due_at'])->format('Y-m-d H:i:s') : null,
            'visibility' => $data['visibility'] ?? OpenIssueVisibility::Internal->value,
        ]);

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.created'))
            ->withFragment('open-issue-' . $issue->id);
    }

    public function update(UpdateOpenIssueRequest $request, OpenIssue $issue): RedirectResponse {
        Gate::authorize('update', $issue);

        $data = $request->validated();
        $actor = $this->authUser();

        $visibility = isset($data['visibility']) ? OpenIssueVisibility::from($data['visibility']) : null;
        if ($visibility === OpenIssueVisibility::Customer && $issue->visibility !== $visibility) {
            Gate::authorize('publishToCustomer', OpenIssue::class);
        }

        DB::transaction(function () use ($issue, $data, $actor, $visibility): void {
            $issue->update(Arr::only($data, ['title', 'description', 'category']));

            // Frist vor Schwere: „kritisch“ ohne Frist setzt der Dienst auf sieben Tage.
            if (array_key_exists('due_at', $data)) {
                $this->service->changeDueDate($issue, filled($data['due_at']) ? Carbon::instance(Tz::parse((string) $data['due_at'])) : null, $actor);
            }
            if (isset($data['severity'])) {
                $this->service->changeSeverity($issue, OpenIssueSeverity::from($data['severity']), $actor);
            }
            if ($visibility !== null) {
                $this->service->changeVisibility($issue, $visibility, $actor);
            }
        });

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.updated'))
            ->withFragment('open-issue-' . $issue->id);
    }

    public function destroy(OpenIssue $issue): RedirectResponse {
        Gate::authorize('delete', $issue);

        $issue->delete();

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.deleted'));
    }

    public function assign(AssignOpenIssueRequest $request, OpenIssue $issue): RedirectResponse {
        Gate::authorize('assign', OpenIssue::class);

        $data = $request->validated();

        /** @var User $actor */
        $actor = Auth::user();
        $assignee = ! empty($data['assignee_user_id']) ? User::query()->find((int) $data['assignee_user_id']) : null;

        $this->service->assign($issue, $assignee, $actor);

        return redirect()->back()->with('success', __('open-issue.flash.assigned'));
    }

    /** Ticket aus dem offenen Punkt (MVP-1549); ohne Helpdesk antwortet die Null-Bindung und es bleibt bei 404. */
    public function ticket(OpenIssue $issue, IssueTicketOpener $opener): RedirectResponse {
        Gate::authorize('update', $issue);

        /** @var User $actor */
        $actor = Auth::user();
        abort_unless($opener->available($actor), 404);

        $ticket = $this->service->openTicket($issue, $actor, $opener);
        abort_if($ticket === null, 404);

        return redirect()
            ->back()
            ->with('success', __($ticket['created'] ? 'open-issue.flash.ticketOpened' : 'open-issue.flash.ticketExists', ['no' => $ticket['label']]))
            ->withFragment('open-issue-' . $issue->id);
    }

    public function transition(TransitionOpenIssueRequest $request, OpenIssue $issue, string $action): RedirectResponse {
        Gate::authorize('update', $issue);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            match ($action) {
                'start' => $this->service->start($issue, $actor),
                'block' => $this->service->block($issue, $actor, (string) $request->validated('reason')),
                'unblock' => $this->service->unblock($issue, $actor),
                'complete' => $this->service->complete($issue, $actor, (string) $request->validated('resolution')),
                'wontDo' => $this->service->wontDo($issue, $actor, (string) $request->validated('reason')),
                'reopen' => $this->service->reopen($issue, $actor, (string) $request->validated('reason')),
                default => throw new InvalidArgumentException('Unbekannte Aktion: ' . $action),
            };
        } catch (InvalidOpenIssueTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['reason' => ErrorText::for($e)]);
        }

        $fresh = $issue->fresh() ?? $issue;
        $status = $fresh->status;

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.status.' . $status->value))
            ->withFragment('open-issue-' . $issue->id);
    }
}
