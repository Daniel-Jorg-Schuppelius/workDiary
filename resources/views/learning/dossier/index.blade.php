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

    <x-filter-bar :action="route('learning.dossier.index')" method="GET">
        <x-input-field name="as_of" type="date" :label="__('learning.field.as_of')"
                       :value="request('as_of', $asOf->toDateString())" />
        <x-select-field name="team_id" :label="__('learning.field.team')" :value="$teamSqid">
            <option value="">{{ __('learning.field.all_people') }}</option>
            @foreach ($teams as $team)
                <option value="{{ $team->sqid }}" @selected($teamSqid === $team->sqid)>{{ $team->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="reason" :label="__('learning.field.disclosure_reason')"
                       :hint="__('learning.help.disclosure_reason')" maxlength="180"
                       :value="$reason" class="order-30" />
        <x-checkbox-field name="named" :label="__('learning.field.named_dossier')"
                          :checked="$named" class="order-40" />
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
