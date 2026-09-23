<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSynonymController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Search;

use App\Http\Controllers\Controller;
use App\Models\Search\SearchSynonymGroup;
use App\Services\Search\{SearchSynonyms, SearchTextNormalizer};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Pflege der Such-Synonyme (Feature 153, MVP-772): Gruppen gleichbedeutender
 * Begriffe je Organisation, dazu Vorlagen zum Übernehmen. Recht
 * `organization.update` ({@see \App\Policies\Search\SearchSynonymGroupPolicy}).
 */
class SearchSynonymController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', SearchSynonymGroup::class);

        return view('admin.search-synonyms.index', [
            'groups' => SearchSynonymGroup::query()
                ->with('creator:id,name')
                ->orderByDesc('active')
                ->orderBy('id')
                ->paginate(50)
                ->withQueryString(),
            'canManage' => Gate::allows('create', SearchSynonymGroup::class),
            'presets' => array_keys((array) config('search.synonym_presets', [])),
        ]);
    }

    /** Anlege-Dialog (modal-first). */
    public function create(): View {
        Gate::authorize('create', SearchSynonymGroup::class);

        return view('admin.search-synonyms._dialog', ['group' => null]);
    }

    /** Bearbeiten-Dialog (modal-first). */
    public function edit(SearchSynonymGroup $group): View {
        Gate::authorize('update', $group);

        return view('admin.search-synonyms._dialog', ['group' => $group]);
    }

    public function store(Request $request, SearchSynonyms $synonyms): RedirectResponse {
        Gate::authorize('create', SearchSynonymGroup::class);

        SearchSynonymGroup::query()->create([
            'terms' => $this->validatedTerms($request),
            'active' => true,
            'created_by_user_id' => Auth::id(),
        ]);
        $synonyms->flush();

        return redirect()->route('admin.search-synonyms.index')->with('success', __('search.synonyms.flash.saved'));
    }

    public function update(Request $request, SearchSynonymGroup $group, SearchSynonyms $synonyms): RedirectResponse {
        Gate::authorize('update', $group);

        $group->update(['terms' => $this->validatedTerms($request)]);
        $synonyms->flush();

        return redirect()->route('admin.search-synonyms.index')->with('success', __('search.synonyms.flash.updated'));
    }

    public function toggle(SearchSynonymGroup $group, SearchSynonyms $synonyms): RedirectResponse {
        Gate::authorize('update', $group);

        $group->forceFill(['active' => ! $group->active])->save();
        $synonyms->flush();

        return back()->with('success', $group->active ? __('search.synonyms.flash.activated') : __('search.synonyms.flash.deactivated'));
    }

    public function destroy(SearchSynonymGroup $group, SearchSynonyms $synonyms): RedirectResponse {
        Gate::authorize('delete', $group);

        $group->delete();
        $synonyms->flush();

        return back()->with('success', __('search.synonyms.flash.deleted'));
    }

    /**
     * Vorlage übernehmen: legt nur Gruppen an, von deren Begriffen noch keiner
     * in einer bestehenden Gruppe steht — eigene Pflege wird nie überschrieben.
     */
    public function importPreset(Request $request, SearchSynonyms $synonyms, SearchTextNormalizer $normalizer): RedirectResponse {
        Gate::authorize('create', SearchSynonymGroup::class);

        $presets = (array) config('search.synonym_presets', []);
        $data = $request->validate(['preset' => ['required', 'string', Rule::in(array_keys($presets))]]);

        $known = [];
        foreach (SearchSynonymGroup::query()->get(['terms']) as $existing) {
            foreach ((array) $existing->terms as $term) {
                $known[$normalizer->key((string) $term)] = true;
            }
        }

        $added = 0;
        foreach ((array) $presets[$data['preset']] as $terms) {
            $keys = array_map(static fn(string $t): string => $normalizer->key($t), (array) $terms);
            if (array_intersect_key($known, array_flip($keys)) !== []) {
                continue;
            }
            SearchSynonymGroup::query()->create([
                'terms' => array_values((array) $terms),
                'active' => true,
                'created_by_user_id' => Auth::id(),
            ]);
            foreach ($keys as $key) {
                $known[$key] = true;
            }
            $added++;
        }
        $synonyms->flush();

        return back()->with('success', trans_choice('search.synonyms.flash.preset_imported', $added, ['count' => $added]));
    }

    /** @return list<string> */
    private function validatedTerms(Request $request): array {
        $request->validate(['terms' => ['required', 'string', 'max:2000']]);
        $terms = SearchSynonymGroup::parseTerms((string) $request->input('terms'));

        if (count($terms) < 2) {
            throw ValidationException::withMessages(['terms' => __('search.synonyms.validation.min_terms')]);
        }
        if (count($terms) > SearchSynonymGroup::MAX_TERMS) {
            throw ValidationException::withMessages(['terms' => __('search.synonyms.validation.max_terms', ['max' => SearchSynonymGroup::MAX_TERMS])]);
        }
        foreach ($terms as $term) {
            if (mb_strlen($term) > SearchSynonymGroup::MAX_TERM_LENGTH) {
                throw ValidationException::withMessages(['terms' => __('search.synonyms.validation.term_length', ['max' => SearchSynonymGroup::MAX_TERM_LENGTH])]);
            }
        }

        return $terms;
    }
}
