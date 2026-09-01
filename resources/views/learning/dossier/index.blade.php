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
         inline davor).

         KEIN eigenes Datumsfeld mehr: Der Zeitraum kommt aus dem Kopfbereich.
         Vorher standen hier ein Stichtag und dort ein wirkungsloser Regler —
         zwei Bedienelemente fuer dieselbe Frage, eines davon ohne Wirkung. --}}
    <x-filter-bar :action="route('learning.dossier.index')" :reset="route('learning.dossier.index')">
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

    {{-- Die Ampel bewertet die Besetzbarkeit ueber den GANZEN Zeitraum, nicht
         die Person: gruen = durchgehend gedeckt, gelb = nur einen Teil davon
         (ein Nachweis laeuft mittendrin ab oder beginnt erst spaeter),
         rot = im Zeitraum gar nicht gedeckt. --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-kpi-tile icon="groups" :label="__('learning.field.people')" :value="$summary['people']" />
        <x-kpi-tile icon="verified" :label="__('learning.field.ready')" :value="$summary['ready']"
                    :tone="$summary['tone']" />
        <x-kpi-tile icon="timelapse" :label="__('learning.field.partial')" :value="$summary['partial']"
                    :tone="$summary['partial'] > 0 ? 'warning' : 'success'" />
        <x-kpi-tile icon="event_busy" :label="__('learning.field.uncovered')" :value="$summary['expired']"
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
        {{-- Legende: eine Farbskala ohne Erklaerung ist eine Ratefrage — und
             fuer Farbfehlsichtige unbrauchbar. Die Zahl „voll/gesamt" steht
             deshalb im Badge, die Farbe ist nur die Zusammenfassung. --}}
        <p class="text-xs text-muted">{{ __('learning.help.coverage_legend') }}</p>

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

            @php
                // Kein `use` hier: Blade schiebt @php-Bloecke in den
                // Kontrollfluss der Seite, dort ist ein Import ein Parse-Fehler.
                $dossierService = app(\App\Services\Learning\QualificationDossierService::class);
                $covFull = \App\Services\Learning\QualificationDossierService::COVERAGE_FULL;
                $covPartial = \App\Services\Learning\QualificationDossierService::COVERAGE_PARTIAL;

                // Zelle = schlechteste Deckung ihrer Nachweise. Die Zahl sagt
                // „so viele decken den ganzen Zeitraum", die Farbe sagt, was
                // mit dem Rest ist.
                $tone = static fn (string $coverage): string => match ($coverage) {
                    $covFull => 'success',
                    $covPartial => 'warning',
                    default => 'error',
                };
                $full = static fn (array $entries): int => count(array_filter(
                    $entries,
                    static fn (array $e): bool => ($e['coverage'] ?? null) === $covFull,
                ));
            @endphp
            @forelse ($rows as $row)
                <tr>
                    <td class="font-medium">{{ $row['user']->name }}</td>
                    @foreach (['qualifications', 'instructions', 'certificates'] as $area)
                        @php($entries = $row[$area])
                        <td>
                            @if ($entries === [])
                                <span class="text-muted">–</span>
                            @else
                                <x-status-badge :tone="$tone($dossierService->worstCoverage($entries))" size="sm">
                                    {{ $full($entries) }} / {{ count($entries) }}
                                </x-status-badge>
                            @endif
                        </td>
                    @endforeach
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
