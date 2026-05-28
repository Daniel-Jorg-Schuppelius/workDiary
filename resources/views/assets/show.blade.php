@extends('layouts.app')

@section('title', ($asset->name ?: $asset->asset_no) . ' — ' . __('Asset'))
@section('nav-title', $asset->name ?: $asset->asset_no)

@section('content')
    @php
        $assetClassValue = $asset->asset_class instanceof \BackedEnum ? $asset->asset_class->value : (string) $asset->asset_class;
        $assetStatusValue = $asset->status instanceof \BackedEnum ? $asset->status->value : (string) $asset->status;
    @endphp

    <x-page-shell>
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <h2 class="text-xl font-semibold">{{ $asset->name }}</h2>
                    <div class="text-sm text-base-content/70">
                        {{ __('Asset-Nr.') }}: <span class="font-mono">{{ $asset->asset_no }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="badge badge-outline">{{ $classOptions[$assetClassValue] ?? $assetClassValue }}</span>
                        <span class="badge badge-outline">{{ $statusOptions[$assetStatusValue] ?? $assetStatusValue }}</span>
                        @if ($asset->serial_no)
                            <span class="text-base-content/70">{{ __('Seriennummer') }}: {{ $asset->serial_no }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if ($canUnblock && $assetStatusValue === \App\Enums\Asset\AssetStatus::Blocked->value)
                        <form method="POST" action="{{ route('assets.unblock', $asset) }}">
                            @csrf
                            <x-icon-btn icon="lock_open" tone="success" size="sm" type="submit" show-label>{{ __('Sperre aufheben') }}</x-icon-btn>
                        </form>
                    @endif
                    <x-icon-btn icon="arrow_back" size="sm" :href="route('assets.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
                </div>
            </div>

            @php
                $attentionLevel = (string) ($statusSummary['attention_level'] ?? 'normal');
                $isBlocked = (bool) ($statusSummary['is_blocked'] ?? false);
                $openIssueTotal = (int) ($statusSummary['open_issues']['total'] ?? 0);
                $criticalIssueTotal = (int) ($statusSummary['open_issues']['critical'] ?? 0);
                $defectProtocolTotal = (int) ($statusSummary['defect_protocols']['total'] ?? 0);
            @endphp

            @if ($isBlocked || $openIssueTotal > 0 || $defectProtocolTotal > 0)
                <div class="alert mt-4 {{ $attentionLevel === 'critical' ? 'alert-error' : 'alert-warning' }}">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold">{{ $isBlocked ? __('Asset gesperrt') : __('Asset unter Beobachtung') }}</span>
                        <span class="badge badge-outline">{{ __('Offene Issues: :count', ['count' => $openIssueTotal]) }}</span>
                        <span class="badge badge-outline">{{ __('Kritisch: :count', ['count' => $criticalIssueTotal]) }}</span>
                        <span class="badge badge-outline">{{ __('Defektprotokolle: :count', ['count' => $defectProtocolTotal]) }}</span>
                    </div>
                </div>
            @endif

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-box border border-base-300 p-3">
                    <div class="text-xs text-base-content/60">{{ __('Standort') }}</div>
                    <div class="font-medium">{{ $asset->location_text ?: '—' }}</div>
                </div>
                <div class="rounded-box border border-base-300 p-3">
                    <div class="text-xs text-base-content/60">{{ __('Kunde') }}</div>
                    <div class="font-medium">{{ $asset->customer?->name ?: '—' }}</div>
                </div>
                <div class="rounded-box border border-base-300 p-3">
                    <div class="text-xs text-base-content/60">{{ __('Verknüpfungen sichtbar') }}</div>
                    <div class="font-medium">
                        {{ $visibleCounts['diary'] + $visibleCounts['protocols'] + $visibleCounts['material'] + $visibleCounts['attachments'] }}
                    </div>
                </div>
            </div>
        </x-card>

        @php
            $room = $asset->room;
            $floor = $room?->floorRelation;
            $building = $floor?->building;
            $site = $building?->site;
            $os = $asset->operatingSystem;
            $installations = $asset->softwareInstallations;
            $canEditAsset = auth()->user()?->can('update', $asset) ?? false;
        @endphp

        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">{{ __('Verortung') }}</h2>
                @if ($canEditAsset)
                    <x-icon-btn icon="edit" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.edit', $asset)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endif
            </div>
            @if ($room || $site || $building || $floor)
                <div class="mt-3 grid gap-2 text-sm md:grid-cols-5">
                    <div>
                        <div class="text-xs text-base-content/60">{{ __('Kunde') }}</div>
                        <div>{{ $asset->customer?->name ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60">{{ __('Standort') }}</div>
                        <div>{{ $site?->name ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60">{{ __('Gebäude') }}</div>
                        <div>{{ $building?->name ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60">{{ __('Etage') }}</div>
                        <div>{{ $floor?->label ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60">{{ __('Raum') }}</div>
                        <div>
                            @if ($room)
                                @if ($floor)
                                    <a class="link link-hover" href="{{ route('floors.show', $floor) }}">{{ $room->name }}</a>
                                @else
                                    {{ $room->name }}
                                @endif
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <p class="mt-3 text-sm text-base-content/60">{{ __('Keinem Raum zugeordnet.') }}</p>
            @endif
        </x-card>

        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">{{ __('Betriebssystem') }}</h2>
                @if ($canEditAsset)
                    <details class="dropdown dropdown-end">
                        <summary class="btn btn-sm btn-primary">
                            <span class="material-symbols-outlined" aria-hidden="true">add</span>
                            {{ $os ? __('OS ersetzen') : __('OS zuweisen') }}
                        </summary>
                        <form method="POST"
                              action="{{ $os ? route('assets.software-installations.update', [$asset, $os]) : route('assets.software-installations.store', $asset) }}"
                              class="dropdown-content z-10 mt-2 w-96 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg space-y-2">
                            @csrf
                            @if ($os) @method('PUT') @endif
                            <input type="hidden" name="is_operating_system" value="1" />
                            @unless ($os)
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Software') }}</span>
                                    <select name="software_id" required class="select select-sm select-bordered">
                                        @foreach ($softwareCatalog->where('kind', \App\Enums\Software\SoftwareKind::OperatingSystem) as $sw)
                                            <option value="{{ $sw->sqid }}">{{ $sw->name }}@if ($sw->vendor) — {{ $sw->vendor }}@endif</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endunless
                            <div class="grid grid-cols-2 gap-2">
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Version') }}</span>
                                    <input type="text" name="version" value="{{ $os?->version }}" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Sitze') }}</span>
                                    <input type="number" name="seats" min="1" value="{{ $os?->seats }}" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control col-span-2">
                                    <span class="label-text text-xs">{{ __('Lizenzschlüssel') }}</span>
                                    <input type="text" name="license_key" value="{{ $os?->license_key }}" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Installiert am') }}</span>
                                    <input type="date" name="installed_on" value="{{ $os?->installed_on?->format('Y-m-d') }}" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Läuft ab') }}</span>
                                    <input type="date" name="expires_on" value="{{ $os?->expires_on?->format('Y-m-d') }}" class="input input-sm input-bordered">
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-full">{{ __('Speichern') }}</button>
                        </form>
                    </details>
                @endif
            </div>
            @if ($os)
                <div class="mt-3 grid gap-2 text-sm md:grid-cols-4">
                    <div><div class="text-xs text-base-content/60">{{ __('Software') }}</div><div class="font-medium">{{ $os->software?->name }}</div></div>
                    <div><div class="text-xs text-base-content/60">{{ __('Version') }}</div><div>{{ $os->version ?: '—' }}</div></div>
                    <div><div class="text-xs text-base-content/60">{{ __('Sitze') }}</div><div>{{ $os->seats ?: '—' }}</div></div>
                    <div><div class="text-xs text-base-content/60">{{ __('Läuft ab') }}</div><div>{{ $os->expires_on?->isoFormat('L') ?: '—' }}</div></div>
                </div>
            @else
                <p class="mt-3 text-sm text-base-content/60">{{ __('Kein Betriebssystem hinterlegt.') }}</p>
            @endif
        </x-card>

        {!! app(\App\Plugins\PluginManager::class)->renderSlot('asset-show.aside', $asset) !!}

        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">{{ __('Installierte Software') }} ({{ $installations->where('is_operating_system', false)->count() }})</h2>
                @if ($canEditAsset)
                    <details class="dropdown dropdown-end">
                        <summary class="btn btn-sm btn-primary">
                            <span class="material-symbols-outlined" aria-hidden="true">add</span>
                            {{ __('Software zuweisen') }}
                        </summary>
                        <form method="POST" action="{{ route('assets.software-installations.store', $asset) }}"
                              class="dropdown-content z-10 mt-2 w-96 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg space-y-2">
                            @csrf
                            <label class="form-control">
                                <span class="label-text text-xs">{{ __('Software') }}</span>
                                <select name="software_id" required class="select select-sm select-bordered">
                                    @foreach ($softwareCatalog->where('kind', '!=', \App\Enums\Software\SoftwareKind::OperatingSystem) as $sw)
                                        <option value="{{ $sw->sqid }}">{{ $sw->name }}@if ($sw->vendor) — {{ $sw->vendor }}@endif</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Version') }}</span>
                                    <input type="text" name="version" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Sitze') }}</span>
                                    <input type="number" name="seats" min="1" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control col-span-2">
                                    <span class="label-text text-xs">{{ __('Lizenzschlüssel') }}</span>
                                    <input type="text" name="license_key" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Installiert am') }}</span>
                                    <input type="date" name="installed_on" class="input input-sm input-bordered">
                                </label>
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Läuft ab') }}</span>
                                    <input type="date" name="expires_on" class="input input-sm input-bordered">
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-full">{{ __('Hinzufügen') }}</button>
                        </form>
                    </details>
                @endif
            </div>
            @php $apps = $installations->where('is_operating_system', false); @endphp
            @if ($apps->isEmpty())
                <p class="mt-3 text-sm text-base-content/60">{{ __('Noch keine Software hinterlegt.') }}</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Version') }}</th>
                                <th>{{ __('Sitze') }}</th>
                                <th>{{ __('Installiert') }}</th>
                                <th>{{ __('Läuft ab') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($apps as $inst)
                                <tr class="hover">
                                    <td class="font-medium">{{ $inst->software?->name }}<div class="text-xs text-base-content/60">{{ $inst->software?->vendor }}</div></td>
                                    <td>{{ $inst->version ?: '—' }}</td>
                                    <td>{{ $inst->seats ?: '—' }}</td>
                                    <td>{{ $inst->installed_on?->isoFormat('L') ?: '—' }}</td>
                                    <td>{{ $inst->expires_on?->isoFormat('L') ?: '—' }}</td>
                                    <td class="text-right">
                                        @if ($canEditAsset)
                                            <form method="POST" action="{{ route('assets.software-installations.destroy', [$asset, $inst]) }}"
                                                  data-confirm-dialog
                                                  data-confirm-message="{{ __('Diese Software wirklich entfernen?') }}"
                                                  data-confirm-label="{{ __('Entfernen') }}"
                                                  class="inline">
                                                @csrf @method('DELETE')
                                                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" />
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">{{ __('Wartungspläne') }} ({{ $maintenancePlans->count() }})</h2>
                @if ($canManageMaintenance)
                    <details class="dropdown dropdown-end">
                        <summary class="btn btn-sm btn-primary">
                            <span class="material-symbols-outlined" aria-hidden="true">add</span>
                            {{ __('Plan anlegen') }}
                        </summary>
                        <form method="POST" action="{{ route('assets.maintenance-plans.store', $asset) }}"
                              class="dropdown-content z-10 mt-2 w-80 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg space-y-2">
                            @csrf
                            <label class="form-control">
                                <span class="label-text text-xs">{{ __('Bezeichnung') }}</span>
                                <input type="text" name="label" required maxlength="180"
                                       class="input input-sm input-bordered" />
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Intervall') }}</span>
                                    <select name="interval_kind" class="select select-sm select-bordered">
                                        @foreach ($intervalKindOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($value === 'months')>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="form-control">
                                    <span class="label-text text-xs">{{ __('Wert') }}</span>
                                    <input type="number" name="interval_value" min="1" value="6"
                                           class="input input-sm input-bordered" required />
                                </label>
                            </div>
                            <label class="form-control">
                                <span class="label-text text-xs">{{ __('Toleranz (Tage)') }}</span>
                                <input type="number" name="tolerance_days" min="0" max="365" value="7"
                                       class="input input-sm input-bordered" />
                            </label>
                            <label class="form-control">
                                <span class="label-text text-xs">{{ __('Erste Fälligkeit') }}</span>
                                <input type="date" name="next_due_on" class="input input-sm input-bordered" />
                            </label>
                            <button type="submit" class="btn btn-sm btn-primary w-full">{{ __('Anlegen') }}</button>
                        </form>
                    </details>
                @endif
            </div>

            @if ($maintenancePlans->isEmpty())
                <p class="mt-3 text-sm text-base-content/70">{{ __('Noch keine Wartungspläne hinterlegt.') }}</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Plan') }}</th>
                                <th>{{ __('Intervall') }}</th>
                                <th>{{ __('Nächste Fälligkeit') }}</th>
                                <th>{{ __('Letzte Ausführung') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Aktionen') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($maintenancePlans as $plan)
                                @php
                                    $kindValue = $plan->interval_kind instanceof \BackedEnum ? $plan->interval_kind->value : (string) $plan->interval_kind;
                                    $isDue = $plan->isDue();
                                @endphp
                                <tr>
                                    <td>
                                        <div class="font-medium">{{ $plan->label }}</div>
                                        <div class="text-xs text-base-content/60 font-mono">{{ $plan->code }}</div>
                                    </td>
                                    <td>{{ $plan->interval_value }} {{ $intervalKindOptions[$kindValue] ?? $kindValue }}</td>
                                    <td>
                                        @if ($plan->next_due_on)
                                            <span class="@if ($isDue) text-error font-semibold @endif">
                                                {{ $plan->next_due_on->format('d.m.Y') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ optional($plan->last_run_at)->format('d.m.Y H:i') ?: '—' }}</td>
                                    <td>
                                        @if (! $plan->is_active)
                                            <span class="badge badge-ghost">{{ __('pausiert') }}</span>
                                        @elseif ($isDue)
                                            <span class="badge badge-error">{{ __('fällig') }}</span>
                                        @else
                                            <span class="badge badge-success">{{ __('aktiv') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($canManageMaintenance)
                                            <div class="join">
                                                <form method="POST" action="{{ route('assets.maintenance-plans.complete', [$asset, $plan]) }}" class="join-item">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-ghost" title="{{ __('Erledigt') }}">
                                                        <span class="material-symbols-outlined">check</span>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('assets.maintenance-plans.toggle', [$asset, $plan]) }}" class="join-item">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-ghost" title="{{ $plan->is_active ? __('Pausieren') : __('Reaktivieren') }}">
                                                        <span class="material-symbols-outlined">{{ $plan->is_active ? 'pause' : 'play_arrow' }}</span>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('assets.maintenance-plans.destroy', [$asset, $plan]) }}" class="join-item"
                                                      onsubmit="return confirm('{{ __('Plan wirklich löschen?') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-ghost text-error" title="{{ __('Löschen') }}">
                                                        <span class="material-symbols-outlined">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Aufträge') }} ({{ $visibleCounts['diary'] }})</h2>
            @if ($diaryEntries->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine verknüpften Aufträge sichtbar.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Titel') }}</th>
                                <th>{{ __('Projekt') }}</th>
                                <th>{{ __('Mitarbeiter') }}</th>
                                <th>{{ __('Start') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($diaryEntries as $entry)
                                <tr>
                                    <td>
                                        <a href="{{ route('diary.show', $entry) }}" class="link link-hover">{{ $entry->title ?: ('#' . $entry->id) }}</a>
                                    </td>
                                    <td>{{ $entry->project?->name ?: '—' }}</td>
                                    <td>{{ $entry->user?->name ?: '—' }}</td>
                                    <td>{{ optional($entry->start_at)->format('d.m.Y H:i') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Timeline') }} ({{ $timelineEntries->count() }})</h2>
            @if ($timelineEntries->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine Timeline-Einträge vorhanden.') }}</p>
            @else
                <ul class="divide-y divide-base-300">
                    @foreach ($timelineEntries as $event)
                        <li class="flex items-start justify-between gap-3 py-3">
                            <div class="space-y-1">
                                <div class="text-sm font-semibold">{{ $event['title'] }}</div>
                                <div class="text-sm text-base-content/80">{{ $event['detail'] }}</div>
                            </div>
                            <span class="shrink-0 text-xs text-base-content/60">{{ $event['occurred_at_formatted'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Protokolle') }} ({{ $visibleCounts['protocols'] }})</h2>
            @if ($protocols->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine verknüpften Protokolle sichtbar.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Titel') }}</th>
                                <th>{{ __('Typ') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Datum') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($protocols as $protocol)
                                <tr>
                                    <td>{{ $protocol->title }}</td>
                                    <td>{{ $protocol->type->label() }}</td>
                                    <td>{{ $protocol->status->label() }}</td>
                                    <td>{{ optional($protocol->occurred_at)->format('d.m.Y H:i') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Materialeinsatz') }} ({{ $visibleCounts['material'] }})</h2>
            @if ($materialUsages->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Kein verknüpfter Materialeinsatz sichtbar.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Beschreibung') }}</th>
                                <th>{{ __('Menge') }}</th>
                                <th>{{ __('Nettobetrag') }}</th>
                                <th>{{ __('Stundenzettel') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($materialUsages as $usage)
                                <tr>
                                    <td>{{ $usage->description }}</td>
                                    <td>{{ number_format((float) $usage->quantity, 3, ',', '.') }} {{ $usage->unit }}</td>
                                    <td>{{ number_format((float) $usage->line_total_net, 2, ',', '.') }} €</td>
                                    <td>
                                        {{ optional($usage->timesheet?->work_date)->format('d.m.Y') ?: ($usage->timesheet ? ('#' . $usage->timesheet->id) : '—') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 text-base font-semibold">{{ __('Anhänge') }} ({{ $visibleCounts['attachments'] }})</h2>
            @if ($attachments->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Keine verknüpften Anhänge sichtbar.') }}</p>
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($attachments as $attachment)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('attachments.download', $attachment) }}" class="link link-hover truncate">{{ $attachment->original_name }}</a>
                            <span class="text-base-content/60">{{ $attachment->humanSize() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </x-page-shell>
@endsection
