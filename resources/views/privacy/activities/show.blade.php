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
            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('Version') }}</th><th>{{ __('Notiz') }}</th><th>{{ __('Freigabe') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($versions as $v)
                            <tr>
                                <td>v{{ $v->version_no }}</td>
                                <td class="text-sm">{{ $v->note ?? '—' }}</td>
                                <td class="text-sm">{{ $v->approved_at?->format('d.m.Y') ?? __('Entwurf') }}</td>
                                <td>
                                    @can('approve', $activity)
                                        @unless ($v->approved_at)
                                            <form method="post" action="{{ route('dataprotection.activities.approve', $activity) }}">
                                                @csrf <input type="hidden" name="version_id" value="{{ $v->id }}">
                                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                                            </form>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        @can('update', $activity)
            <x-card>
                <div class="flex items-center justify-between">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Neue Version anlegen') }}</h2>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                onclick="document.getElementById('dlg-activity-version').showModal()" show-label>{{ __('Neue Version') }}</x-icon-btn>
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
