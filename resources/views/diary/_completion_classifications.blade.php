{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _completion_classifications.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Pflichtklassifikationen vor dem Abschluss (etwa „Ergebnis“, Feature 198):
  je fehlender, blockierender Domäne eine Auswahl im Abschlussdialog.
  Erwartet: $diary, $dialogSuffix.
--}}
@php
    $completionGaps = array_values(array_filter(
        app(\App\Services\Classification\DataQualityInspector::class)->diaryEntryGaps($diary, \App\Enums\Classification\ClassificationRequirementPhase::BeforeComplete),
        static fn (array $gap): bool => $gap['blocking'],
    ));
@endphp
@foreach ($completionGaps as $gap)
    @php($gapDomain = \App\Enums\Classification\ClassificationDomain::tryFrom($gap['domain']))
    @continue($gapDomain === null)
    @php($gapOptions = app(\App\Services\Classification\ClassificationResolver::class)->list((int) $diary->organization_id, $gapDomain))
    @continue($gapOptions->isEmpty())
    <div class="fieldset mt-3">
        <label class="fieldset-label" for="completion-{{ $gap['domain'] }}-{{ $dialogSuffix }}">{{ $gap['label'] }} *</label>
        <select id="completion-{{ $gap['domain'] }}-{{ $dialogSuffix }}" name="classifications[{{ $gap['domain'] }}]" required class="select select-sm select-bordered w-full">
            <option value="">—</option>
            @foreach ($gapOptions as $option)
                <option value="{{ $option->code }}">{{ $option->display_label }}</option>
            @endforeach
        </select>
    </div>
@endforeach
