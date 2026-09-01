{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachweismappe (Feature 149, MVP-750). Aggregiert ist die Vorgabe — die
  namentliche Ausprägung braucht einen Anlass und wird protokolliert.
--}}
@extends('layouts.app')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('title', __('learning.title.dossier'))
@section('nav-title', __('learning.title.dossier'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('learning.subtitle.dossier')">
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm"
                            :href="route('learning.dossier.pdf', request()->query())"
                            show-label>{{ __('learning.action.dossier_pdf') }}</x-icon-btn>
                <x-icon-btn icon="download" tone="ghost" size="sm"
                            :href="route('learning.dossier.json', request()->query())"
                            show-label>{{ __('learning.action.dossier_json') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Filterleisten-Standard: x-filter-field mit schlichtem Input/Select in
         `sm`, Schalter als x-filter-toggle (order-40). Die Formular-
         Komponenten (x-input-field & Co.) gehoeren in Formularkoerper — sie
         ziehen sich ueber die volle Breite und sprengen die Leiste.
         Beschriftung nach Komponentenregel: Selects tragen ihre Bedeutung in
         der „Alle …"-Option (Label sr-only), Eingabefelder nicht (Label
         inline davor). --}}
    <x-filter-bar :action="route('learning.dossier.index')" :reset="route('learning.dossier.index')">
        <x-filter-field :label="__('learning.field.as_of')" for="dossier-as-of" inline>
            <input id="dossier-as-of" type="date" name="as_of"
                   value="{{ request('as_of', $asOf->toDateString()) }}"
                   class="input input-bordered input-sm shrink-0">
        </x-filter-field>

        <x-filter-field :label="__('learning.field.team')" for="dossier-team" class="min-w-44 flex-1">
            <select id="dossier-team" name="team_id" class="select select-bordered select-sm w-full">
                <option value="">{{ __('learning.field.all_people') }}</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->sqid }}" @selected($teamSqid === $team->sqid)>{{ $team->name }}</option>
                @endforeach
            </select>
        </x-filter-field>

        {{-- Der Anlass ist kein Suchfeld, sondern die Begruendung der
             Weitergabe — er wird protokolliert. Der Hinweistext dazu steht im
             Info-Kasten unter der Leiste und hier als Tooltip; als Platzhalter
             waere er zu lang und verschwaende beim Tippen. --}}
        <x-filter-field :label="__('learning.field.disclosure_reason')" for="dossier-reason" inline>
            <input id="dossier-reason" type="text" name="reason" maxlength="180"
                   value="{{ $reason }}"
                   title="{{ __('learning.help.disclosure_reason') }}"
                   class="input input-bordered input-sm w-56">
        </x-filter-field>

        {{-- Warnfarbe mit Absicht: der Schalter wechselt von aggregierter zu
             namentlicher Auskunft. --}}
        <x-filter-toggle name="named" tone="warning"
                         :label="__('learning.field.named_dossier')"
                         :checked="$named"
                         :title="__('learning.help.disclosure_reason')" />
    </x-filter-bar>

    {{-- Die Ampel bewertet die Besetzbarkeit, nicht die Person. --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-tile icon="groups" :label="__('learning.field.people')" :value="$summary['people']" />
        <x-kpi-tile icon="verified" :label="__('learning.field.ready')" :value="$summary['ready']"
                    :tone="$summary['tone']" />
        <x-kpi-tile icon="event_busy" :label="__('learning.field.expired')" :value="$summary['expired']"
                    :tone="$summary['expired'] > 0 ? 'error' : 'success'" />
        <x-kpi-tile icon="pending_actions" :label="__('learning.field.open_obligations')"
                    :value="$summary['open_obligations']"
                    :tone="$summary['open_obligations'] > 0 ? 'warning' : 'success'" />
    </div>

    @if (! $named)
        <div class="alert alert-info" role="status">
            <x-icon name="privacy_tip" />
            <span>{{ __('learning.help.dossier_aggregated') }}</span>
        </div>
    @else
        <x-table scroll="flex" hover :caption="__('learning.title.dossier')">
            <x-slot:head>
                <tr>
                    <th>{{ __('learning.field.person') }}</th>
                    <th>{{ __('learning.field.qualifications') }}</th>
                    <th>{{ __('learning.field.instructions') }}</th>
                    <th>{{ __('learning.field.certificates') }}</th>
                    <th class="text-right">{{ __('learning.field.open_obligations') }}</th>
                </tr>
            </x-slot:head>

            @forelse ($rows as $row)
                @php
                    $valid = static fn (array $entries): int => count(array_filter($entries, static fn (array $e): bool => (bool) ($e['valid_on'] ?? false)));
                @endphp
                <tr>
                    <td>{{ $row['user']->name }}</td>
                    <td>{{ $valid($row['qualifications']) }} / {{ count($row['qualifications']) }}</td>
                    <td>{{ $valid($row['instructions']) }} / {{ count($row['instructions']) }}</td>
                    <td>{{ $valid($row['certificates']) }} / {{ count($row['certificates']) }}</td>
                    <td class="text-right">
                        @if ($row['open_obligations'] > 0)
                            <x-status-badge tone="warning" size="sm">{{ $row['open_obligations'] }}</x-status-badge>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="folder_off" :message="__('learning.empty.dossier')" colspan="5" />
            @endforelse
        </x-table>
    @endif
</x-page-shell>
@endsection
