{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', $activity->name)
@section('nav-title', $activity->name)

@section('content')
    <x-index-page :subtitle="__('Verarbeitungstätigkeit, Versionen und Datenschutz-Folgenabschätzung.')"
                  :badge="$activity->status->label()" badge-tone="ghost">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <x-card>
            <div class="space-y-1 text-sm">
                <p><span class="font-semibold">{{ __('Rolle') }}:</span> {{ $activity->controller_role->label() }}</p>
                <p><span class="font-semibold">{{ __('Zweck') }}:</span> {{ $activity->purpose ?? '—' }}</p>
                <p><span class="font-semibold">{{ __('Review fällig') }}:</span> {{ $activity->review_due_at?->format('d.m.Y') ?? '—' }}</p>
                @if ($activity->currentVersion)
                    <p><span class="font-semibold">{{ __('Gültige Version') }}:</span> v{{ $activity->currentVersion->version_no }}
                        ({{ __('gültig ab') }} {{ $activity->currentVersion->valid_from?->format('d.m.Y') }})</p>
                @endif
            </div>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Versionen') }}</h2>
                @can('update', $activity)
                    <form method="post" action="{{ route('dataprotection.activities.submit', $activity) }}">
                        @csrf
                        <x-icon-btn icon="send" tone="outline" size="sm" type="submit" show-label>{{ __('Zur Prüfung einreichen') }}</x-icon-btn>
                    </form>
                @endcan
            </div>
            <x-table :bare="true">
                <x-slot:head>
<tr><th>{{ __('Version') }}</th><th>{{ __('Notiz') }}</th><th>{{ __('Freigabe') }}</th><th></th></tr>
                </x-slot:head>
                        @foreach ($versions as $v)
                            <tr>
                                <td>v{{ $v->version_no }}</td>
                                <td class="text-sm">{{ $v->note ?? '—' }}</td>
                                <td class="text-sm">{{ $v->approved_at?->format('d.m.Y') ?? __('Entwurf') }}</td>
                                <td class="text-right">
                                    @can('approve', $activity)
                                        @unless ($v->approved_at)
                                            <form method="post" action="{{ route('dataprotection.activities.approve', $activity) }}">
                                                @csrf <input type="hidden" name="version_id" value="{{ $v->sqid }}">
                                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                                            </form>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
            </x-table>
        </x-card>

        @can('update', $activity)
            <x-card>
                <div class="flex items-center justify-between">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Neue Version anlegen') }}</h2>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-open-dialog="dlg-activity-version" show-label>{{ __('Neue Version') }}</x-icon-btn>
                </div>
            </x-card>

            <x-modal :embedded="false" id="dlg-activity-version"
                     :title="__('Neue Version anlegen')"
                     :eyebrow="__('Verzeichnis von Verarbeitungstätigkeiten')"
                     icon="fact_check" tone="primary"
                     :action="route('dataprotection.activities.version', $activity)"
                     method="POST" :submit-label="__('Version speichern')">
                @include('privacy.activities._payload_fields', ['payload' => $activity->currentVersion?->payload])
                <x-form-group :legend="__('Änderung')" icon="edit_note" tone="ghost" cols="1">
                    <x-input-field name="note" :label="__('Änderungsnotiz')" :value="old('note')" />
                </x-form-group>
                {{-- name/role aus dem Kopf erneut mitsenden (validateActivity erwartet sie) --}}
                <input type="hidden" name="name" value="{{ $activity->name }}">
                <input type="hidden" name="controller_role" value="{{ $activity->controller_role->value }}">
            </x-modal>
        @endcan

        @can('create', \App\Models\Privacy\Dpia::class)
            @php
                /** @var \App\Models\Privacy\Dpia|null $dpiaModel */
                $dpiaModel = $activity->dpia;
                $dpiaSteps = $dpiaModel?->steps ?? collect();
                $nextDpiaStep = $dpiaSteps->first(fn ($s) => ! $s->isDone());
            @endphp
            {{-- Geführter DSFA-Workflow (Nachtrag 043a): erzwungene Schrittfolge
                 mit Freigabe; das Formular darunter bleibt als Direktbearbeitung. --}}
            <x-card>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('DSFA-Workflow (geführt)') }}</h2>
                    @if ($dpiaModel && $dpiaModel->outcome->value !== 'open')
                        <a href="{{ route('dataprotection.activities.dpia.report', $activity) }}" class="btn btn-ghost btn-sm">
                            <x-icon name="picture_as_pdf" class="text-base" />
                            {{ __('Bericht (PDF)') }}
                        </a>
                    @endif
                </div>
                <ol class="space-y-2">
                    @foreach (\App\Models\Privacy\DpiaStep::STEPS as $idx => $stepCode)
                        @php $stepModel = $dpiaSteps->firstWhere('step', $stepCode); @endphp
                        <li class="rounded-box border border-base-300 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-medium">
                                    {{ $idx + 1 }}. {{ ($stepModel ?? new \App\Models\Privacy\DpiaStep(['step' => $stepCode]))->label() }}
                                </span>
                                @php
                                    $isActionable = ($stepModel === null && $idx === $dpiaSteps->count())
                                        || ($stepModel !== null && ! $stepModel->isDone() && $stepModel->step === $nextDpiaStep?->step);
                                @endphp
                                @if ($stepModel?->isDone())
                                    <x-status-badge tone="success" size="xs">{{ __('erledigt') }}</x-status-badge>
                                @elseif ($isActionable)
                                    <x-status-badge tone="warning" size="xs">{{ __('nächster Schritt') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="neutral" size="xs">{{ __('offen') }}</x-status-badge>
                                @endif
                            </div>
                            @if ($stepModel?->isDone() && $stepModel->content)
                                <p class="mt-1 whitespace-pre-wrap text-xs text-base-content/70">{{ $stepModel->content }}</p>
                            @endif
                            @if ($isActionable)
                                <form method="post" action="{{ route('dataprotection.activities.dpia.step', [$activity, $stepCode]) }}" class="mt-2 space-y-2">
                                    @csrf
                                    <textarea aria-label="{{ __('Ergebnis dieses Schritts …') }}" name="content" rows="2" class="textarea textarea-bordered w-full"
                                              placeholder="{{ __('Ergebnis dieses Schritts …') }}">{{ old('content') }}</textarea>
                                    @if ($stepCode === 'approval')
                                        <div class="flex flex-wrap gap-2">
                                            <select name="outcome" class="select select-bordered select-sm" required>
                                                <option value="proceed">{{ __('Verarbeitung zulässig') }}</option>
                                                <option value="consult">{{ __('Aufsichtsbehörde konsultieren') }}</option>
                                                <option value="abort">{{ __('Verarbeitung nicht durchführen') }}</option>
                                            </select>
                                            <select name="residual_risk" class="select select-bordered select-sm">
                                                <option value="">{{ __('Restrisiko …') }}</option>
                                                <option value="low">{{ __('gering') }}</option>
                                                <option value="medium">{{ __('mittel') }}</option>
                                                <option value="high">{{ __('hoch') }}</option>
                                            </select>
                                        </div>
                                    @endif
                                    <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Schritt abschließen') }}</x-icon-btn>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-card>

            @php $d = $activity->dpia; @endphp
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold mb-3">{{ __('Datenschutz-Folgenabschätzung (Art. 35)') }}
                    @if ($d && $d->outcome->value !== 'open')<x-status-badge tone="info" size="sm" class="ml-2">{{ $d->outcome->label() }}</x-status-badge>@endif
                </h2>
                <form method="post" action="{{ route('dataprotection.activities.dpia', $activity) }}" class="space-y-2">
                    @csrf
                    <x-form-group :legend="__('Folgenabschätzung')" icon="fact_check" tone="ghost" cols="2">
                        <x-input-field name="necessity" :label="__('Notwendigkeit & Verhältnismäßigkeit')" span="2">
                            <textarea id="necessity" name="necessity" rows="2" class="textarea textarea-bordered w-full">{{ old('necessity', $d?->necessity) }}</textarea>
                        </x-input-field>
                        <x-input-field name="risks" :label="__('Risiken für Betroffene')" span="2">
                            <textarea id="risks" name="risks" rows="2" class="textarea textarea-bordered w-full">{{ old('risks', $d?->risks) }}</textarea>
                        </x-input-field>
                        <x-input-field name="mitigations" :label="__('Abhilfemaßnahmen')" span="2">
                            <textarea id="mitigations" name="mitigations" rows="2" class="textarea textarea-bordered w-full">{{ old('mitigations', $d?->mitigations) }}</textarea>
                        </x-input-field>
                        <x-input-field name="residual_risk" :label="__('Restrisiko')">
                            <select id="residual_risk" name="residual_risk" class="select select-bordered w-full">
                                <option value="">{{ __('Restrisiko …') }}</option>
                                @foreach (['low' => __('gering'), 'medium' => __('mittel'), 'high' => __('hoch')] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('residual_risk', $d?->residual_risk) === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </x-input-field>
                        <x-input-field name="outcome" :label="__('Ergebnis')">
                            <select id="outcome" name="outcome" class="select select-bordered w-full">
                                @foreach (\App\Enums\Privacy\DpiaOutcome::cases() as $o)
                                    <option value="{{ $o->value }}" @selected(old('outcome', $d?->outcome?->value ?? 'open') === $o->value)>{{ $o->label() }}</option>
                                @endforeach
                            </select>
                        </x-input-field>
                    </x-form-group>
                    <x-icon-btn icon="check" tone="ghost" size="sm" type="submit" show-label>{{ __('DSFA speichern') }}</x-icon-btn>
                </form>
            </x-card>
        @endcan
    </x-index-page>
@endsection
