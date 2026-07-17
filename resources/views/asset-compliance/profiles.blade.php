@extends('layouts.app')

@section('title', __('Prüfprofile'))
@section('nav-title', __('Prüfprofile'))

@section('content')
<x-index-page :subtitle="__('Prüfprofile als Katalogdaten (P1): globale Vorlagen + Organisations-Overrides; Zuweisung erzeugt Prüfpflichten mit Fälligkeit und Sperrwirkung.')">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    @foreach ($profiles as $profile)
        <x-card padding="p-0">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ $profile->name }}</span>
                    <span class="badge badge-outline">{{ $profile->inspection_kind->label() }}</span>
                    <span class="badge badge-outline">{{ __('alle :n Monate', ['n' => $profile->interval_months]) }}</span>
                    <span class="badge badge-outline">{{ $profile->blocking_mode->label() }}</span>
                    @if ($profile->requires_certificate)
                        <span class="badge badge-info badge-outline">{{ __('Zertifikatspflicht') }}</span>
                    @endif
                    @if ($profile->organization_id === null)
                        <span class="badge badge-ghost badge-sm">{{ __('globale Vorlage') }}</span>
                    @endif
                </div>
                @can('create', \App\Models\AssetCompliance\AssetComplianceProfile::class)
                    <details class="text-left">
                        <summary class="btn btn-xs btn-primary">{{ __('Asset zuweisen') }}</summary>
                        <form method="POST" action="{{ route('asset-compliance.profiles.assign', $profile) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
                            @csrf
                            <x-select-field name="asset_id" :label="__('Asset')" required>
                                @foreach ($assets as $a)
                                    <option value="{{ $a->sqid }}">{{ $a->name }}</option>
                                @endforeach
                            </x-select-field>
                            <x-input-field name="last_done_on" type="date" :label="__('Zuletzt geprüft am')" />
                            <x-input-field name="next_due_on" type="date" :label="__('Nächste Fälligkeit (optional)')" />
                            <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
                                <option value="">{{ __('—') }}</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->sqid }}">{{ $u->name }}</option>
                                @endforeach
                            </x-select-field>
                            <x-select-field name="external_contact_id" :label="__('Prüfstelle (extern)')">
                                <option value="">{{ __('intern') }}</option>
                                @foreach ($externalContacts as $contact)
                                    <option value="{{ $contact->sqid }}">{{ $contact->name }}</option>
                                @endforeach
                            </x-select-field>
                            <button type="submit" class="btn btn-sm">{{ __('Zuweisen') }}</button>
                        </form>
                    </details>
                @endcan
            </div>
            <x-table bare>
                <x-slot:head><tr><th>{{ __('Anforderung') }}</th><th>{{ __('Grenzwerte') }}</th><th>{{ __('Pflicht') }}</th></tr></x-slot:head>
                @forelse ($profile->requirements as $requirement)
                    <tr>
                        <td>{{ $requirement->label }}</td>
                        <td class="font-mono text-sm">
                            {{ $requirement->limit_min !== null ? '≥ ' . $requirement->limit_min : '' }}
                            {{ $requirement->limit_max !== null ? '≤ ' . $requirement->limit_max : '' }}
                            {{ $requirement->unit ?? '' }}
                        </td>
                        <td>{{ $requirement->is_mandatory ? __('ja') : __('optional') }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" :title="__('Keine messbaren Anforderungen — Ergebnis wird pauschal dokumentiert.')" compact />
                @endforelse
            </x-table>
            @can('update', $profile)
                @if ($profile->organization_id !== null)
                    <form method="POST" action="{{ route('asset-compliance.profiles.requirements.store', $profile) }}" class="flex flex-wrap items-end gap-2 border-t border-base-300 p-3">
                        @csrf
                        <x-input-field name="label" :label="__('Anforderung')" required />
                        <x-input-field name="limit_min" type="number" step="0.0001" :label="__('Min')" />
                        <x-input-field name="limit_max" type="number" step="0.0001" :label="__('Max')" />
                        <x-input-field name="unit" :label="__('Einheit')" />
                        <button type="submit" class="btn btn-sm">{{ __('Ergänzen') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>
    @endforeach

    @can('create', \App\Models\AssetCompliance\AssetComplianceProfile::class)
        <x-card :title="__('Neues Organisationsprofil (überschreibt globale Vorlage gleichen Codes)')">
            <form method="POST" action="{{ route('asset-compliance.profiles.store') }}" class="grid gap-3 sm:grid-cols-3">
                @csrf
                <x-input-field name="code" :label="__('Code')" required />
                <x-input-field name="name" :label="__('Name')" required />
                <x-select-field name="inspection_kind" :label="__('Prüfart')" required>
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="interval_months" type="number" min="1" max="240" :label="__('Intervall (Monate)')" value="12" required />
                <x-input-field name="warn_days_before" type="number" min="0" :label="__('Vorwarnzeit (Tage)')" value="30" />
                <x-input-field name="tolerance_days" type="number" min="0" :label="__('Toleranz (Tage)')" value="0" />
                <x-input-field name="grace_days" type="number" min="0" :label="__('Nachfrist (Tage)')" value="0" />
                <x-select-field name="blocking_mode" :label="__('Sperrwirkung')" required>
                    @foreach ($blockModes as $mode)
                        <option value="{{ $mode->value }}" @selected($mode === \App\Enums\AssetCompliance\AssetComplianceBlockMode::Warn)>{{ $mode->label() }}</option>
                    @endforeach
                </x-select-field>
                <div class="flex items-end">
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="requires_certificate" value="0">
                        <input type="checkbox" name="requires_certificate" value="1" class="checkbox checkbox-sm">
                        <span class="label-text text-sm">{{ __('Zertifikatsnachweis Pflicht') }}</span>
                    </label>
                </div>
                <x-input-field name="default_authority" :label="__('Standard-Prüfstelle')" span="2" />
                <div class="sm:col-span-3">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Profil anlegen') }}</button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card :title="__('Normen-Referenzmatrix — Referenz ohne Konformitätszusage')" padding="p-0">
        <x-table bare>
            <x-slot:head><tr><th>{{ __('Prüfart') }}</th><th>{{ __('Rechtsraum') }}</th><th>{{ __('Norm/Regelwerk') }}</th><th>{{ __('Gültigkeit') }}</th><th>{{ __('Rahmenversion') }}</th></tr></x-slot:head>
            @forelse ($norms as $norm)
                <tr>
                    <td>{{ $norm->inspection_kind->label() }}</td>
                    <td>{{ $norm->jurisdiction }}</td>
                    <td>
                        @if ($norm->source_url !== null)
                            <a class="link" href="{{ $norm->source_url }}" target="_blank" rel="noopener">{{ $norm->norm_label }}</a>
                        @else
                            {{ $norm->norm_label }}
                        @endif
                    </td>
                    <td>{{ optional($norm->valid_from)->fdate() ?? '—' }} – {{ optional($norm->valid_to)->fdate() ?? __('offen') }}</td>
                    <td class="font-mono text-sm">{{ $norm->frame_version ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine Referenzeinträge — Katalog wird per Seeder/Datenpflege befüllt (W12).')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
