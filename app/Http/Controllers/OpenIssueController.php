<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueVisibility};
use App\Exceptions\InvalidOpenIssueTransitionException;
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, User};
use App\Support\Sqid;
use App\Services\OpenIssue\OpenIssueService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use InvalidArgumentException;

class OpenIssueController extends Controller {
    /**
     * Whitelist der erlaubten Subject-Typen. Verhindert, dass Aufrufer beliebige
     * Klassen an `subject_type` setzen können.
     *
     * @var array<string, class-string<Model>>
     */
    private const SUBJECT_MAP = [
        'diary' => DiaryEntry::class,
        'project' => Project::class,
        'customer' => Customer::class,
    ];

    public function __construct(
        private readonly OpenIssueService $service,
    ) {
    }

    public function create(Request $request): View {
        Gate::authorize('create', OpenIssue::class);

        $subjectKind = (string) $request->query('subject_kind', '');
        if (! array_key_exists($subjectKind, self::SUBJECT_MAP)) {
            abort(404);
        }

        $subjectClass = self::SUBJECT_MAP[$subjectKind];
        $rawSubjectId = (string) $request->query('subject_id', '');
        $subjectId = Sqid::decode($subjectClass, $rawSubjectId);
        if ($subjectId === null && is_numeric($rawSubjectId)) {
            $subjectId = (int) $rawSubjectId;
        }
        if ($subjectId < 1 || $subjectClass::query()->whereKey($subjectId)->doesntExist()) {
            abort(404);
        }

        return view('open-issues._form_dialog', [
            'subjectKind' => $subjectKind,
            'subjectId' => Sqid::encode($subjectClass, $subjectId),
            'canPublishToCustomer' => Gate::allows('publishToCustomer', OpenIssue::class),
            'canAssign' => Gate::allows('assign', OpenIssue::class),
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

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', OpenIssue::class);

        $data = $request->validate([
            'subject_kind' => ['required', 'string', 'in:' . implode(',', array_keys(self::SUBJECT_MAP))],
            'subject_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:40'],
            'severity' => ['nullable', 'string', 'in:' . implode(',', array_column(OpenIssueSeverity::cases(), 'value'))],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:' . implode(',', array_column(OpenIssueVisibility::cases(), 'value'))],
        ]);

        if (($data['visibility'] ?? OpenIssueVisibility::Internal->value) === OpenIssueVisibility::Customer->value) {
            Gate::authorize('publishToCustomer', OpenIssue::class);
        }

        if (! empty($data['assignee_user_id'])) {
            Gate::authorize('assign', OpenIssue::class);
        }

        $subjectClass = self::SUBJECT_MAP[$data['subject_kind']];
        $decodedSubjectId = Sqid::decode($subjectClass, (string) $data['subject_id']);
        if ($decodedSubjectId === null && is_numeric((string) $data['subject_id'])) {
            $decodedSubjectId = (int) $data['subject_id'];
        }
        if ($decodedSubjectId === null || $decodedSubjectId < 1) {
            abort(404);
        }
        /** @var Model|null $subject */
        $subject = $subjectClass::query()->find($decodedSubjectId);
        if ($subject === null) {
            abort(404);
        }

        /** @var User $creator */
        $creator = Auth::user();

        $issue = $this->service->create($subject, $creator, [
            'source_type' => OpenIssueSource::Manual->value,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'severity' => $data['severity'] ?? OpenIssueSeverity::Low->value,
            'assignee_user_id' => $data['assignee_user_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'visibility' => $data['visibility'] ?? OpenIssueVisibility::Internal->value,
        ]);

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.created'))
            ->withFragment('open-issue-' . $issue->id);
    }

    public function update(Request $request, OpenIssue $issue): RedirectResponse {
        Gate::authorize('update', $issue);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        $issue->update($data);

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.updated'));
    }

    public function destroy(OpenIssue $issue): RedirectResponse {
        Gate::authorize('delete', $issue);

        $issue->delete();

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.deleted'));
    }

    public function assign(Request $request, OpenIssue $issue): RedirectResponse {
        Gate::authorize('assign', OpenIssue::class);

        $data = $request->validate([
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $assignee = ! empty($data['assignee_user_id']) ? User::query()->find((int) $data['assignee_user_id']) : null;

        $this->service->assign($issue, $assignee, $actor);

        return redirect()->back()->with('success', __('open-issue.flash.assigned'));
    }

    public function transition(Request $request, OpenIssue $issue, string $action): RedirectResponse {
        Gate::authorize('update', $issue);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            match ($action) {
                'start' => $this->service->start($issue, $actor),
                'block' => $this->service->block(
                    $issue,
                    $actor,
                    (string) $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'],
                ),
                'unblock' => $this->service->unblock($issue, $actor),
                'complete' => $this->service->complete(
                    $issue,
                    $actor,
                    (string) $request->validate(['resolution' => ['required', 'string', 'max:5000']])['resolution'],
                ),
                'wontDo' => $this->service->wontDo(
                    $issue,
                    $actor,
                    (string) $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'],
                ),
                'reopen' => $this->service->reopen(
                    $issue,
                    $actor,
                    (string) $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'],
                ),
                default => throw new InvalidArgumentException('Unbekannte Aktion: ' . $action),
            };
        } catch (InvalidOpenIssueTransitionException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['reason' => $e->getMessage()]);
        }

        $fresh = $issue->fresh() ?? $issue;
        $status = $fresh->status;

        return redirect()
            ->back()
            ->with('success', __('open-issue.flash.status.' . $status->value))
            ->withFragment('open-issue-' . $issue->id);
    }
}
