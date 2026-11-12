{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _timeline_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auftrags-Timeline „Verlauf" (MVP-010).
  Erwartet: $diary, $timelineItems (list<TimelineItem>), $timelineHasMore (bool),
            $timelineTypes (list<string>, leer = alle), $timelineFilter (TimelineFilter),
            $timelineActors (array<int, string>), $timelineQuery (array), $timelineLimit (int)
--}}
@php
    // Kurznarrativ des Verlaufs (Feature 148, MVP-732): verdichtet die
    // rechtegeprüfte Timeline; Übernahme = interner Kommentar am Auftrag.
    $aiView = app(\App\Services\Ai\Contracts\SuggestionView::class);
    $aiNarrativeUsable = $aiView->capabilityUsable(\App\Services\Ai\Suggestions\CaseNarrativeSuggestionService::CAPABILITY);
    $aiNarrative = $aiNarrativeUsable
        ? $aiView->openSuggestionsFor((new \App\Models\Diary\DiaryEntry)->getMorphClass(), collect([$diary]), \App\Services\Ai\Suggestions\CaseNarrativeSuggestionService::CAPABILITY)->get($diary->id)
        : null;
@endphp
<x-card as="section" id="timeline" :title="__('timeline.title.section')" icon="history">
    @if ($aiNarrativeUsable && $aiNarrative === null && $timelineItems !== [])
        <x-slot:actions>
            <x-action-form :action="route('ai.assist.case-narrative', $diary)">
                <x-icon-btn icon="auto_awesome" tone="info" size="sm" type="submit" show-label
                            :title="__('ai.assist.narrate_case')">{{ __('ai.assist.narrate_case') }}</x-icon-btn>
            </x-action-form>
        </x-slot:actions>
    @endif

    @if ($aiNarrative !== null)
        @include('ai._insight', [
            'suggestion' => $aiNarrative,
            'acceptAction' => route('ai.assist.accept', $aiNarrative),
            'regenerateAction' => route('ai.assist.case-narrative', $diary),
        ])
    @endif

    {{-- Filterleiste (MVP-1596): Zeitraum, Typen, Person, „nur kundenrelevant“ — alles serverseitig. --}}
    <x-filter-bar class="mb-4" :action="route('diary.show', $diary) . '#timeline'" :reset="route('diary.show', $diary)"
                  aria-label="{{ __('timeline.filter.label') }}">
        <x-date-range class="w-80 shrink-0" :label="false" from-name="timeline_from" to-name="timeline_to"
                      :from="request('timeline_from')" :to="request('timeline_to')" />
        <x-filter-field :label="__('timeline.filter.actor')" for="timeline-actor">
            <select id="timeline-actor" name="timeline_actor" class="select select-sm select-bordered shrink-0">
                <option value="">{{ __('timeline.filter.all_actors') }}</option>
                @foreach ($timelineActors as $actorId => $actorName)
                    <option value="{{ \App\Support\Sqid::encode(\App\Models\Platform\User::class, $actorId) }}" @selected($timelineFilter->actorId === $actorId)>{{ $actorName }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-toggle name="timeline_customer" id="timeline-customer" :label="__('timeline.filter.customer_only')"
                         :checked="$timelineFilter->customerOnly" />
        {{-- Typen mehrfach wählbar; ohne Auswahl gelten alle. --}}
        <fieldset class="order-60 flex basis-full flex-wrap items-center gap-1.5">
            <legend class="sr-only">{{ __('timeline.filter.types') }}</legend>
            @foreach (\App\Services\Timeline\DiaryEntryTimelineService::TYPES as $typeKey)
                <label @class(['badge badge-sm cursor-pointer gap-1 whitespace-nowrap', 'badge-primary' => in_array($typeKey, $timelineTypes, true), 'badge-ghost' => ! in_array($typeKey, $timelineTypes, true)])>
                    <input type="checkbox" class="checkbox checkbox-xs" name="timeline_types[]" value="{{ $typeKey }}"
                           @checked(in_array($typeKey, $timelineTypes, true))>
                    {{ __('timeline.filter.' . $typeKey) }}
                </label>
            @endforeach
        </fieldset>
    </x-filter-bar>

    @include('timeline._items', ['items' => $timelineItems])

    @if ($timelineHasMore)
        <div class="mt-4 text-center">
            <x-icon-btn icon="expand_more" tone="outline" size="sm" show-label
                        :href="route('diary.show', ['diary' => $diary, 'timeline_limit' => $timelineLimit + 50] + $timelineQuery) . '#timeline'">
                {{ __('timeline.action.load_more') }}
            </x-icon-btn>
        </div>
    @endif
</x-card>
