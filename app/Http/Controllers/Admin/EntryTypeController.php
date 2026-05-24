<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Diary\{Priority, Status as DiaryStatus};
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEntryTypeRequest;
use App\Models\EntryType;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EntryTypeController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', EntryType::class);

        $query = EntryType::query()->withCount('diaryEntries');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'sort' => 'sort',
            'label' => 'label',
            'slug' => 'slug',
            'is_active' => 'is_active',
            'entries' => 'diary_entries_count',
        ], 'sort', 'asc');

        $entryTypes = $query->paginate((int) setting('pagination.entry_types', 25))->withQueryString();

        return view('admin.entry-types.index', compact('entryTypes', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', EntryType::class);

        return view('admin.entry-types._form_dialog', [
            'entryType' => new EntryType,
            'statusOptions' => $this->statusOptions(),
            'priorityOptions' => Priority::cases(),
        ]);
    }

    public function store(SaveEntryTypeRequest $request): RedirectResponse {
        Gate::authorize('create', EntryType::class);

        EntryType::create($request->validated());

        return redirect()->route('admin.entry-types.index')
            ->with('success', __('Eintragstyp wurde angelegt.'));
    }

    public function edit(EntryType $entryType): View {
        Gate::authorize('update', $entryType);

        return view('admin.entry-types._form_dialog', [
            'entryType' => $entryType,
            'statusOptions' => $this->statusOptions(),
            'priorityOptions' => Priority::cases(),
        ]);
    }

    public function update(SaveEntryTypeRequest $request, EntryType $entryType): RedirectResponse {
        Gate::authorize('update', $entryType);

        $entryType->update($request->validated());

        return redirect()->route('admin.entry-types.index')
            ->with('success', __('Eintragstyp wurde aktualisiert.'));
    }

    public function destroy(EntryType $entryType): RedirectResponse {
        Gate::authorize('delete', $entryType);

        if ($entryType->diaryEntries()->exists()) {
            return redirect()->route('admin.entry-types.index')
                ->with('error', __('Typ wird noch von Einträgen verwendet und kann nicht gelöscht werden.'));
        }

        $entryType->delete();

        return redirect()->route('admin.entry-types.index')
            ->with('success', __('Eintragstyp wurde gelöscht.'));
    }

    /** @return array<int, string> */
    private function statusOptions(): array {
        return [
            DiaryStatus::Open->value => __('Offen'),
            DiaryStatus::InProgress->value => __('In Bearbeitung'),
            DiaryStatus::Done->value => __('Erledigt'),
            DiaryStatus::Problem->value => __('Problem'),
        ];
    }
}
