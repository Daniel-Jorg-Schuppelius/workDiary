{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $incident->incident_number)
@section('nav-title', $incident->incident_number . ' — ' . $incident->type->label())
@section('content')
    <x-index-page :subtitle="__('Vorfall bewerten, melden, Maßnahmen verfolgen und dokumentieren.')">
        <x-slot:actions>
            <x-status-badge :tone="$incident->isDeadlineBreached() ? 'error' : 'ghost'" size="sm">{{ $incident->status->label() }}</x-status-badge>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.incidents.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        @php $isProcessor = $incident->controller_role?->value === 'processor'; @endphp
        @if ($incident->authority_deadline_at)
            <div class="alert {{ $incident->isDeadlineBreached() ? 'alert-error' : 'alert-warning' }}">
                @if ($isProcessor)
                    {{ __('72-h-Frist (Kunde meldet der Behörde)') }}: {{ $incident->authority_deadline_at->format('d.m.Y H:i') }} — {{ __('ihr informiert den Verantwortlichen/Kunden unverzüglich') }}
                    @if ($incident->controller_notified_at) — {{ __('Kunde informiert am') }} {{ $incident->controller_notified_at->format('d.m.Y H:i') }} @endif
                @else
                    {{ __('72-h-Meldefrist') }}: {{ $incident->authority_deadline_at->format('d.m.Y H:i') }}
                    @if ($incident->authority_notified_at) — {{ __('Behörde gemeldet am') }} {{ $incident->authority_notified_at->format('d.m.Y H:i') }} @endif
                @endif
            </div>
        @endif

        @if ($incident->own_infrastructure_affected)
            <div class="alert alert-warning text-sm">{{ __('Eigene Infrastruktur mitbetroffen – ggf. zusätzlich einen eigenen Meldefall (als Verantwortlicher) prüfen.') }}</div>
        @endif
        @if ($authorityRecommendation)
            <div class="alert alert-info text-sm">
                <x-icon name="location_on" />
                <span>
                    {{ __('Bundesland-Vorschlag anhand der :source-PLZ :postal: :state.', [
                        'source' => $authorityRecommendation['source'] === 'customer' ? __('Kunden') : __('Unternehmens'),
                        'postal' => $authorityRecommendation['postal_code'],
                        'state' => $authorityRecommendation['state_name'],
                    ]) }}
                    {{ __('Bitte die Zuständigkeit vor der Meldung prüfen.') }}
                </span>
            </div>
        @endif

        <div class="grid md:grid-cols-3 gap-4">
            <x-card class="md:col-span-2 text-sm space-y-2">
                <p class="whitespace-pre-line"><span class="font-semibold">{{ __('Sachverhalt') }}:</span><br>{{ $incident->summary_ciphertext ?? '—' }}</p>
                <p class="whitespace-pre-line"><span class="font-semibold">{{ __('Betroffenes') }}:</span><br>{{ $incident->affected_ciphertext ?? '—' }}</p>
                @if ($incident->measures_ciphertext)<p class="whitespace-pre-line"><span class="font-semibold">{{ __('Sofortmaßnahmen') }}:</span><br>{{ $incident->measures_ciphertext }}</p>@endif
                <p><span class="font-semibold">{{ __('Risiko') }}:</span> {{ $incident->risk_level ?? '—' }}
                   · {{ __('Meldung Behörde') }}: {{ $incident->notify_authority ? __('ja') : '—' }}
                   · {{ __('Betroffene') }}: {{ $incident->notify_subjects ? __('ja') : '—' }}</p>
                <p><span class="font-semibold">{{ __('Rolle') }}:</span>
                    @if ($isProcessor)
                        <x-status-badge tone="info" size="sm">{{ __('AV-Vorfall') }}</x-status-badge>
                        @if ($incident->controller_name) · {{ __('Verantwortlicher/Kunde') }}: {{ $incident->controller_name }} @endif
                    @else
                        <x-status-badge tone="ghost" size="sm">{{ __('Eigener Vorfall (Verantwortlicher)') }}</x-status-badge>
                    @endif
                </p>
            </x-card>

            @can('update', $incident)
                <x-card class="space-y-2">
                    <form method="post" action="{{ route('dataprotection.incidents.assess', $incident) }}" class="space-y-1">
                        @csrf
                        <select name="risk_level" class="select select-sm select-bordered w-full">
                            <option value="low">{{ __('Geringes Risiko') }}</option>
                            <option value="medium">{{ __('Mittleres Risiko') }}</option>
                            <option value="high">{{ __('Hohes Risiko') }}</option>
                        </select>
                        <textarea name="measures" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Sofortmaßnahmen') }}"></textarea>
                        <button class="btn btn-sm w-full">{{ __('Bewertung speichern') }}</button>
                    </form>
                    @if ($isProcessor)
                        {{-- AV-Vorfall (Art. 33 Abs. 2): Verantwortlichen/Kunden informieren --}}
                        <form method="post" action="{{ route('dataprotection.incidents.notify-controller', $incident) }}" class="space-y-1 border-t border-base-300 pt-2">
                            @csrf
                            <label class="fieldset-label">{{ __('Verantwortlichen/Kunden informiert am') }}</label>
                            <input type="datetime-local" name="notified_at" class="input input-sm input-bordered w-full">
                            <x-button tone="primary" type="submit" class="w-full">{{ __('Als informiert vermerken') }}</x-button>
                            <p class="text-xs text-base-content/60">{{ __('Die Behördenmeldung obliegt dem Verantwortlichen (Kunden).') }}</p>
                        </form>
                    @else
                        <form method="post" action="{{ route('dataprotection.incidents.decide', $incident) }}" class="space-y-1 border-t border-base-300 pt-2">
                            @csrf
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="authority" value="1" class="checkbox checkbox-sm"> {{ __('Behörde melden') }}</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="subjects" value="1" class="checkbox checkbox-sm"> {{ __('Betroffene benachrichtigen') }}</label>
                            <button class="btn btn-sm w-full">{{ __('Meldeentscheidung') }}</button>
                        </form>
                    @endif
                    @if ($incident->status->isOpen())
                        <form method="post" action="{{ route('dataprotection.incidents.close', $incident) }}" class="space-y-1 border-t border-base-300 pt-2">
                            @csrf
                            <textarea name="lessons" rows="2" class="textarea textarea-sm textarea-bordered w-full" placeholder="{{ __('Lessons Learned') }}"></textarea>
                            <x-button tone="primary" type="submit" class="w-full">{{ __('Abschließen') }}</x-button>
                        </form>
                    @endif
                </x-card>
            @endcan
        </div>

        {{-- Maßnahmenverfolgung --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Maßnahmen') }}</h2>
            <ul class="space-y-1">
                @forelse ($incident->measures as $m)
                    <li class="flex items-center justify-between text-sm rounded-box border border-base-300 px-3 py-2">
                        <span>{{ $m->title }} @if ($m->due_at)<span class="{{ $m->isOverdue() ? 'text-error' : 'text-base-content/60' }}">({{ __('bis') }} {{ $m->due_at->format('d.m.Y') }})</span>@endif</span>
                        @if ($m->status === 'done')
                            <x-status-badge tone="success" size="sm">{{ __('erledigt') }}</x-status-badge>
                        @else
                            @can('update', $incident)
                                <form method="post" action="{{ route('dataprotection.incidents.measure.complete', [$incident, $m]) }}">@csrf <button class="btn btn-xs">{{ __('Erledigt') }}</button></form>
                            @endcan
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-base-content/60">{{ __('Keine Maßnahmen.') }}</li>
                @endforelse
            </ul>
            @can('update', $incident)
                <div class="pt-2">
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-open-dialog="dlg-incident-measure" show-label>{{ __('Hinzufügen') }}</x-icon-btn>
                </div>
                <x-modal :embedded="false" id="dlg-incident-measure" :title="__('Maßnahme hinzufügen')"
                         icon="add_task" tone="primary"
                         :action="route('dataprotection.incidents.measure.store', $incident)" method="POST"
                         :submit-label="__('Hinzufügen')">
                    <x-form-group :legend="__('Maßnahme')" icon="add_task" tone="primary" cols="2">
                        <x-input-field name="title" :label="__('Maßnahme')" required span="2" />
                        <x-input-field name="due_at" type="date" :label="__('Fällig bis')" />
                    </x-form-group>
                </x-modal>
            @endcan
        </x-card>

        @unless ($isProcessor)
            <x-card class="space-y-4">
                <div>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Meldeassistent Aufsichtsbehörde') }}</h2>
                    <p class="text-xs text-base-content/60">{{ __('Die App bereitet die Meldung vor und dokumentiert den Nachweis. Sie übermittelt keine Daten automatisch an Behördenportale.') }}</p>
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    @foreach ($authorityPortals as $key => $portal)
                        <a href="{{ $portal['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="rounded-box border border-base-300 p-3 transition-colors hover:bg-base-200">
                            <span class="flex items-center gap-2 font-semibold">
                                <x-icon name="open_in_new" class="text-primary" />
                                {{ $portal['name'] }}
                            </span>
                            <span class="mt-1 block text-xs text-base-content/60">{{ $portal['hint'] }}</span>
                        </a>
                    @endforeach
                </div>
                <a href="{{ $authorityDirectoryUrl }}" target="_blank" rel="noopener noreferrer" class="link text-sm">
                    {{ __('Verzeichnis aller deutschen Datenschutzaufsichtsbehörden öffnen') }}
                </a>

                @if ($incident->authority_notified_at)
                    <div class="rounded-box bg-base-200 p-3 text-sm">
                        <strong>{{ __('Dokumentierte Meldung') }}:</strong>
                        {{ $incident->authority_name ?? '—' }} ·
                        {{ $incident->authority_report_type === 'follow_up' ? __('Folgemeldung') : __('Erstmeldung') }} ·
                        {{ $incident->authority_notified_at->format('d.m.Y H:i') }}
                        @if ($incident->authority_report_reference) · {{ __('Kennung') }}: {{ $incident->authority_report_reference }} @endif
                        @if ($incident->authority_case_number) · {{ __('Aktenzeichen') }}: {{ $incident->authority_case_number }} @endif
                    </div>
                @endif

                @can('update', $incident)
                    <form method="post" action="{{ route('dataprotection.incidents.authority-report', $incident) }}" class="grid gap-3 md:grid-cols-2">
                        @csrf
                        <x-select-field name="authority_key" :label="__('Offizielles Portal')" span="2" class="select-sm">
                            <option value="">{{ __('Andere / manuell eintragen') }}</option>
                            @foreach ($authorityPortals as $key => $portal)
                                <option value="{{ $key }}" @selected(($authorityRecommendation['portal_key'] ?? null) === $key)>{{ $portal['name'] }}</option>
                            @endforeach
                        </x-select-field>
                        <x-input-field name="authority_name" :label="__('Behördenname bei manuellem Eintrag')" maxlength="255" class="input-sm" />
                        <x-input-field name="authority_portal_url" type="url" :label="__('Portal-URL bei manuellem Eintrag')" maxlength="2000" class="input-sm" />
                        <x-select-field name="report_type" :label="__('Meldeart')" required class="select-sm">
                            <option value="initial">{{ __('Erstmeldung') }}</option>
                            <option value="follow_up">{{ __('Folgemeldung') }}</option>
                        </x-select-field>
                        <x-input-field name="reported_at" type="datetime-local" :label="__('Gemeldet am')" class="input-sm" />
                        <x-input-field name="report_reference" :label="__('Meldekennung / Bestätigungs-ID')" maxlength="255" class="input-sm" />
                        <x-input-field name="case_number" :label="__('Behördliches Aktenzeichen')" maxlength="255" class="input-sm" />
                        <x-button tone="primary" type="submit" class="md:col-span-2">{{ __('Behördenmeldung dokumentieren') }}</x-button>
                    </form>
                @endcan
            </x-card>
        @endunless

        {{-- Meldungsentwürfe (nicht versendet) --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Meldungsentwürfe') }}</h2>
            <p class="text-xs text-base-content/60">{{ __('Vorbereitete Entwürfe – werden NICHT automatisch versendet.') }}</p>
            <div class="flex flex-wrap gap-2">
                <x-button tone="outline" :href="route('dataprotection.incidents.draft', [$incident, 'authority'])">{{ __('Art. 33 TXT') }}</x-button>
                <x-button tone="outline" :href="route('dataprotection.incidents.draft', [$incident, 'authority', 'format' => 'pdf'])">{{ __('Art. 33 PDF') }}</x-button>
                <x-button tone="outline" :href="route('dataprotection.incidents.draft', [$incident, 'subjects'])">{{ __('Art. 34 TXT') }}</x-button>
                <x-button tone="outline" :href="route('dataprotection.incidents.draft', [$incident, 'subjects', 'format' => 'pdf'])">{{ __('Art. 34 PDF') }}</x-button>
            </div>
        </x-card>

        {{-- Anhänge --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Anhänge') }}</h2>
            <ul class="text-sm space-y-1">
                @forelse ($incident->attachments as $att)
                    <li class="flex items-center justify-between">
                        <a class="link" href="{{ route('dataprotection.attachment.download', $att) }}">{{ $att->filename }}</a>
                        @can('update', $incident)
                            <form method="post" action="{{ route('dataprotection.attachment.destroy', $att) }}">@csrf @method('DELETE')<x-icon-btn icon="close" tone="error" size="xs" type="submit" :label="__('Löschen')" /></form>
                        @endcan
                    </li>
                @empty
                    <li class="text-base-content/60">{{ __('Keine Anhänge.') }}</li>
                @endforelse
            </ul>
            @can('update', $incident)
                <form method="post" action="{{ route('dataprotection.incidents.attach', $incident) }}" enctype="multipart/form-data" class="flex gap-2 pt-2">
                    @csrf
                    <input type="file" name="file" class="file-input file-input-sm file-input-bordered flex-1" required>
                    <button class="btn btn-sm">{{ __('Hochladen') }}</button>
                </form>
            @endcan
        </x-card>

        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Verlauf') }}</h2>
            <ul class="timeline timeline-vertical">
                @foreach ($events as $e)
                    <li>
                        <div class="timeline-start text-xs text-base-content/60">{{ $e->created_at?->format('d.m.Y H:i') }}</div>
                        <div class="timeline-middle">●</div>
                        <div class="timeline-end timeline-box text-sm">{{ $e->event }}</div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </x-index-page>
@endsection
