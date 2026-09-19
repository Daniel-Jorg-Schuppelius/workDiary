<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionCatalogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningQuestionKind;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningQuestion, LearningQuestionCategory, LearningQuiz};
use App\Models\User;
use App\Services\Learning\{LearningQuestionCatalogService, LearningQuestionEditorService};
use App\Support\Sqid;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Fragenkatalog (Feature 149, MVP-782): Fragen gehören der Organisation und
 * werden hier gepflegt; Prüfungen zeigen auf sie. Recht: `learning.author`
 * (wie der Kurs-Editor). Der Katalog kennt keine Kursfreigabe — eine
 * geänderte Katalogfrage wirkt auf künftige Versuche, nie auf alte
 * (Prüfungsakte = Snapshot).
 */
class LearningQuestionCatalogController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly LearningQuestionCatalogService $catalog,
        private readonly LearningQuestionEditorService $editor,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::LearningAuthor->value);
        $organization = $this->currentOrganization();

        $category = Sqid::decode(LearningQuestionCategory::class, (string) $request->query('category', ''));
        $kind = LearningQuestionKind::tryFrom((string) $request->query('kind', ''));
        $quizId = Sqid::decode(LearningQuiz::class, (string) $request->query('quiz', ''));
        $search = trim((string) $request->query('q', ''));

        $questions = LearningQuestion::query()
            ->with(['category', 'quizzes'])
            ->where('organization_id', $organization->id)
            ->when($category !== null, fn (Builder $q) => $q->where('learning_question_category_id', $category))
            ->when($kind, fn (Builder $q, LearningQuestionKind $k) => $q->where('kind', $k->value))
            ->when($quizId !== null, fn (Builder $q) => $q->whereHas('quizzes', fn (Builder $w) => $w->where('learning_quizzes.id', $quizId)))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereLikeEscaped('prompt', $search)->orWhereLikeEscaped('title', $search)))
            ->orderBy('learning_question_category_id')
            ->orderBy('title')
            ->orderBy('prompt')
            ->paginate(50)
            ->withQueryString();

        return view('learning.questions.index', [
            'questions' => $questions,
            'categories' => $this->categories(),
            'quizzes' => LearningQuiz::query()->where('organization_id', $organization->id)->orderBy('title')->get(['id', 'title']),
            'category' => $category,
            'kind' => $kind,
            'quizId' => $quizId,
            'search' => $search,
            'total' => LearningQuestion::query()->where('organization_id', $organization->id)->count(),
        ]);
    }

    public function create(): View {
        Gate::authorize(Permission::LearningAuthor->value);

        return view('learning.questions._form_dialog', [
            'question' => null,
            'lines' => '',
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningAuthor->value);
        $data = $this->validated($request);
        $image = $request->file('image');

        $this->editor->create($this->currentOrganization()->id, $data, $image instanceof UploadedFile ? $image : null, $this->actor());

        return redirect()->toList('learning.questions.index')->with('success', __('learning.flash.question_added'));
    }

    public function edit(LearningQuestion $question): View {
        Gate::authorize(Permission::LearningAuthor->value);

        return view('learning.questions._form_dialog', [
            'question' => $question,
            'lines' => $this->editor->toLines($question),
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, LearningQuestion $question): RedirectResponse {
        Gate::authorize(Permission::LearningAuthor->value);
        $data = $this->validated($request);
        $image = $request->file('image');

        $this->editor->update($question, $data, $image instanceof UploadedFile ? $image : null, $this->actor());

        return redirect()->toList('learning.questions.index')->with('success', __('learning.flash.question_updated'));
    }

    public function duplicate(LearningQuestion $question): RedirectResponse {
        Gate::authorize(Permission::LearningAuthor->value);

        $this->editor->duplicate($question, $this->actor());

        return redirect()->toList('learning.questions.index')->with('success', __('learning.flash.question_duplicated'));
    }

    public function destroy(LearningQuestion $question): RedirectResponse {
        Gate::authorize(Permission::LearningAuthor->value);

        $this->catalog->delete($question);

        return redirect()->toList('learning.questions.index')->with('success', __('learning.flash.question_deleted'));
    }

    public function storeCategory(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningAuthor->value);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120']]);

        $this->catalog->createCategory($this->currentOrganization(), $data['name']);

        return redirect()->toList('learning.questions.index')->with('success', __('learning.flash.category_saved'));
    }

    public function updateCategory(Request $request, LearningQuestionCategory $category): RedirectResponse {
        Gate::authorize(Permission::LearningAuthor->value);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120']]);

        $this->catalog->renameCategory($category, $data['name']);

        return redirect()->toList('learning.questions.index')->with('success', __('learning.flash.category_saved'));
    }

    public function destroyCategory(LearningQuestionCategory $category): RedirectResponse {
        Gate::authorize(Permission::LearningAuthor->value);

        $this->catalog->deleteCategory($category);

        return redirect()->toList('learning.questions.index')->with('success', __('learning.flash.category_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(LearningQuestionKind::class)],
            'title' => ['nullable', 'string', 'max:180'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'prompt' => ['required', 'string', 'min:2', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'hint' => ['nullable', 'string', 'max:1000'],
            'feedback_correct' => ['nullable', 'string', 'max:1000'],
            'feedback_incorrect' => ['nullable', 'string', 'max:1000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'options' => ['nullable', 'string', 'max:5000'],
            'partial_credit' => ['nullable', 'boolean'],
            // Aufsatz (MVP-793): Text, Datei oder beides.
            'submission_kind' => ['nullable', 'string', 'in:text,upload,both'],
            'case_sensitive' => ['nullable', 'boolean'],
            'image' => array_merge(['nullable'], \App\Services\Attachments\FileAttacher::rule()),
        ]);

        $data['category_id'] = null;
        $raw = (string) $request->input('category_id', '');
        if ($raw !== '') {
            $id = Sqid::decode(LearningQuestionCategory::class, $raw);
            $category = $id !== null ? LearningQuestionCategory::query()->where('organization_id', $this->currentOrganization()->id)->find($id) : null;
            if ($category === null) {
                throw ValidationException::withMessages(['category_id' => (string) __('learning.errors.category_required')]);
            }
            $data['category_id'] = $category->id;
        }

        return $data;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, LearningQuestionCategory>
     */
    private function categories() {
        return LearningQuestionCategory::query()
            ->where('organization_id', $this->currentOrganization()->id)
            ->withCount('questions')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
