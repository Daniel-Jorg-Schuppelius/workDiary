{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _result_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ergebnis erfassen (in #entry-modal geladen). Variablen: $event, $match, $format (ClubResultFormat). --}}
@php
    $result = $match->result ?? [];
    $rows = $result['sets'] ?? $result['periods'] ?? [];
    $rowCount = max(count($rows) + 2, $format === \App\Enums\Club\ClubResultFormat::Sets ? 5 : 4);
@endphp
<x-modal
    :title="__('club.matches.action.record_result')"
    :eyebrow="$event->title"
    icon="scoreboard"
    tone="primary"
    :action="route('club.matches.result', $event)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="$format->label()" icon="scoreboard" tone="primary" cols="2" :description="__('club.matches.hint.result_' . $format->value)">
        @if ($format === \App\Enums\Club\ClubResultFormat::Goals)
            <x-input-field name="home" type="number" min="0" max="999" required :label="__('club.matches.field.score_home')" :value="old('home', $result['home'] ?? null)" />
            <x-input-field name="away" type="number" min="0" max="999" required :label="__('club.matches.field.score_away')" :value="old('away', $result['away'] ?? null)" />
        @elseif ($format !== \App\Enums\Club\ClubResultFormat::None)
            @for ($i = 0; $i < $rowCount; $i++)
                <x-input-field :name="'periods[' . $i . '][home]'" :id="'res-home-' . $i" type="number" min="0" max="999" :label="__('club.matches.field.period_home', ['no' => $i + 1])" :value="old('periods.' . $i . '.home', $rows[$i][0] ?? null)" />
                <x-input-field :name="'periods[' . $i . '][away]'" :id="'res-away-' . $i" type="number" min="0" max="999" :label="__('club.matches.field.period_away', ['no' => $i + 1])" :value="old('periods.' . $i . '.away', $rows[$i][1] ?? null)" />
            @endfor
        @endif
        <x-textarea-field name="result_note" :label="__('club.matches.field.result_note')" rows="3" maxlength="2000" span="2" :value="old('result_note', $match->result_note)" :hint="__('club.matches.hint.result_note')" />
    </x-form-group>
</x-modal>
