{{--
  Filename     : _offboard_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Austritts-Dialog (MVP-798, Befund P1-21): Stichtag statt fest „heute", dazu die
     Übergabeliste aus dem Bestand. Variablen: $member, $checklist --}}
<x-modal
    :title="__('Austritt von :name', ['name' => $member->name])"
    :eyebrow="__('Mitarbeiterverwaltung')"
    icon="logout"
    tone="warning"
    :action="route('org.members.offboard', $member)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Austritt vollziehen')">

    <x-form-group :legend="__('Stichtag')" icon="event" tone="warning">
        <x-input-field name="left_at" type="date" :label="__('Letzter Arbeitstag')" :value="now()->toDateString()" required />
        <p class="text-sm text-muted">{{ __('Liegt der Tag in der Zukunft, wird der Austritt vorgemerkt und am Stichtag vollzogen. Das Konto wird deaktiviert, Nachweise bleiben erhalten.') }}</p>
    </x-form-group>

    <x-form-group :legend="__('Übergabeliste')" icon="checklist" tone="primary">
        @foreach ($checklist['blockers'] as $blocker)
            <div role="alert" class="alert alert-error text-sm"><span>{{ $blocker }}</span></div>
        @endforeach

        @if ($checklist['assets']->isEmpty() && $checklist['tasks']->isEmpty() && $checklist['open_attendances'] === 0 && $checklist['blockers'] === [])
            <p class="text-sm text-muted">{{ __('Nichts offen — es gibt nichts zu übergeben.') }}</p>
        @else
            <ul class="space-y-2 text-sm">
                @if ($checklist['assets']->isNotEmpty())
                    <li>
                        <strong>{{ __('Zugewiesene Geräte') }} ({{ $checklist['assets']->count() }}):</strong>
                        {{ $checklist['assets']->map(fn ($assignment) => $assignment->asset?->name ?? '—')->implode(', ') }}
                    </li>
                @endif
                @if ($checklist['tasks']->isNotEmpty())
                    <li>
                        <strong>{{ __('Offene Aufgaben') }} ({{ $checklist['tasks']->count() }}):</strong>
                        {{ $checklist['tasks']->pluck('title')->implode(', ') }}
                    </li>
                @endif
                @if ($checklist['open_attendances'] > 0)
                    <li><strong>{{ __('Offene Anwesenheitsbuchungen') }}:</strong> {{ $checklist['open_attendances'] }}</li>
                @endif
            </ul>
        @endif
    </x-form-group>

    @if ($checklist['deputies']->isNotEmpty())
        {{-- Vertretungen neu besetzen (MVP-941) --}}
        <x-form-group :legend="__('hr.offboarding.deputies')" icon="swap_horiz" tone="primary">
            <p class="text-sm text-muted">{{ __('hr.offboarding.deputies_hint') }}</p>
            @foreach ($checklist['deputies'] as $person)
                <x-select-field :name="'deputy_replacements[' . $person->sqid . ']'" :id="'deputy-' . $person->sqid" :label="__('hr.offboarding.deputy_for', ['name' => $person->name])">
                    <option value="">{{ __('hr.offboarding.no_deputy') }}</option>
                    @foreach ($deputyCandidates as $candidate)
                        @continue($candidate->id === $person->id)
                        <option value="{{ $candidate->sqid }}">{{ $candidate->name }}</option>
                    @endforeach
                </x-select-field>
            @endforeach
        </x-form-group>
    @endif
</x-modal>
