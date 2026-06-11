@extends('layouts.app')

@section('title', ($asset->name ?: $asset->asset_no) . ' — ' . __('Asset'))
@section('nav-title', $asset->name ?: $asset->asset_no)

@section('content')
    @php
        $assetClassValue = $asset->asset_class instanceof \BackedEnum ? $asset->asset_class->value : (string) $asset->asset_class;
        $assetStatusValue = $asset->status instanceof \BackedEnum ? $asset->status->value : (string) $asset->status;

        $attentionLevel = (string) ($statusSummary['attention_level'] ?? 'normal');
        $isBlocked = (bool) ($statusSummary['is_blocked'] ?? false);
        $openIssueTotal = (int) ($statusSummary['open_issues']['total'] ?? 0);
        $criticalIssueTotal = (int) ($statusSummary['open_issues']['critical'] ?? 0);
        $defectProtocolTotal = (int) ($statusSummary['defect_protocols']['total'] ?? 0);
        $linkedTotal = $visibleCounts['diary'] + $visibleCounts['protocols'] + $visibleCounts['material'] + $visibleCounts['attachments'];
    @endphp

    <x-page-shell>
        {{-- ── Kopf ──────────────────────────────────────────────────────── --}}
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <h2 class="font-['Space_Grotesk'] text-xl font-bold">{{ $asset->name }}</h2>
                    <div class="text-sm text-base-content/70">
                        {{ __('Asset-Nr.') }}: <span class="font-mono">{{ $asset->asset_no }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <x-status-badge tone="ghost" outline>{{ $classOptions[$assetClassValue] ?? $assetClassValue }}</x-status-badge>
                        <x-status-badge :tone="$isBlocked ? 'error' : 'ghost'" :outline="! $isBlocked">{{ $statusOptions[$assetStatusValue] ?? $assetStatusValue }}</x-status-badge>
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

            @if ($isBlocked || $openIssueTotal > 0 || $defectProtocolTotal > 0)
                <div class="alert mt-4 {{ $attentionLevel === 'critical' ? 'alert-error' : 'alert-warning' }}">
                    <x-icon :name="$isBlocked ? 'lock' : 'warning'" />
                    <span class="font-semibold">{{ $isBlocked ? __('Asset gesperrt') : __('Asset unter Beobachtung') }}</span>
                </div>
            @endif

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-kpi-tile :label="__('Offene Issues')" :value="$openIssueTotal" :tone="$openIssueTotal > 0 ? 'warning' : 'neutral'" />
                <x-kpi-tile :label="__('Kritisch')" :value="$criticalIssueTotal" :tone="$criticalIssueTotal > 0 ? 'error' : 'neutral'" />
                <x-kpi-tile :label="__('Defektprotokolle')" :value="$defectProtocolTotal" :tone="$defectProtocolTotal > 0 ? 'warning' : 'neutral'" />
                <x-kpi-tile :label="__('Verknüpfungen sichtbar')" :value="$linkedTotal" tone="neutral" />
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

        {{-- ── Verortung + Betriebssystem (nebeneinander) ──────────────────── --}}
        <div class="grid gap-4 md:grid-cols-2">
            <x-card>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                        <x-icon name="location_on" class="text-base-content/60" /> {{ __('Verortung') }}
                    </h2>
                    @if ($canEditAsset)
                        <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('assets.edit', $asset)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @endif
                </div>
                @if ($room || $site || $building || $floor)
                    <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-base-content/60">{{ __('Kunde') }}</dt>
                            <dd>{{ $asset->customer?->name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-base-content/60">{{ __('Standort') }}</dt>
                            <dd>{{ $site?->name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-base-content/60">{{ __('Gebäude') }}</dt>
                            <dd>{{ $building?->name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-base-content/60">{{ __('Etage') }}</dt>
                            <dd>{{ $floor?->label ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-base-content/60">{{ __('Raum') }}</dt>
                            <dd>
                                @if ($room)
                                    @if ($floor)
                                        <a class="link link-hover" href="{{ route('floors.show', $floor) }}">{{ $room->name }}</a>
                                    @else
                                        {{ $room->name }}
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                @else
                    <x-empty-state compact icon='<span class="material-symbols-outlined">location_off</span>'
                                   :title="__('Keinem Raum zugeordnet')"
                                   :message="__('Dieses Asset hat noch keine Verortung.')" />
                @endif
            </x-card>

            <x-card>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                        <x-icon name="desktop_windows" class="text-base-content/60" /> {{ __('Betriebssystem') }}
                    </h2>
                    @if ($canEditAsset)
                        <x-icon-btn icon="add" tone="primary" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('assets.software-installations.create', ['asset' => $asset, 'os' => 1])"
                                    show-label>{{ $os ? __('OS ersetzen') : __('OS zuweisen') }}</x-icon-btn>
                    @endif
                </div>
                @if ($os)
                    <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-base-content/60">{{ __('Software') }}</dt><dd class="font-medium">{{ $os->software?->name }}</dd></div>
                        <div><dt class="text-xs text-base-content/60">{{ __('Version') }}</dt><dd>{{ $os->version ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-base-content/60">{{ __('Sitze') }}</dt><dd>{{ $os->seats ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-base-content/60">{{ __('Läuft ab') }}</dt><dd>{{ $os->expires_on?->isoFormat('L') ?: '—' }}</dd></div>
                    </dl>
                @else
                    <x-empty-state compact icon='<span class="material-symbols-outlined">desktop_access_disabled</span>'
                                   :title="__('Kein Betriebssystem')"
                                   :message="__('Kein Betriebssystem hinterlegt.')" />
                @endif
            </x-card>
        </div>

        {!! app(\App\Plugins\PluginManager::class)->renderSlot('asset-show.aside', $asset) !!}

        {{-- ── Installierte Software ────────────────────────────────────────── --}}
        <x-card>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="apps" class="text-base-content/60" /> {{ __('Installierte Software') }}
                    <span class="font-normal text-base-content/50">({{ $installations->where('is_operating_system', false)->count() }})</span>
                </h2>
                @if ($canEditAsset)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.software-installations.create', $asset)"
                                show-label>{{ __('Software zuweisen') }}</x-icon-btn>
                @endif
            </div>
            @php $apps = $installations->where('is_operating_system', false); @endphp
            @if ($apps->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">apps</span>'
                               :title="__('Keine Software')"
                               :message="__('Noch keine Software hinterlegt.')" />
            @else
                <div class="overflow-x-auto">
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

        {{-- ── Wartungspläne ────────────────────────────────────────────────── --}}
        <x-card>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="event_repeat" class="text-base-content/60" /> {{ __('Wartungspläne') }}
                    <span class="font-normal text-base-content/50">({{ $maintenancePlans->count() }})</span>
                </h2>
                @if ($canManageMaintenance)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.maintenance-plans.create', $asset)"
                                show-label>{{ __('Plan anlegen') }}</x-icon-btn>
                @endif
            </div>

            @if ($maintenancePlans->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">event_repeat</span>'
                               :title="__('Keine Wartungspläne')"
                               :message="__('Noch keine Wartungspläne hinterlegt.')" />
            @else
                <div class="overflow-x-auto">
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
                                                {{ $plan->next_due_on->fdate() }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ optional($plan->last_run_at)->fdatetime() ?: '—' }}</td>
                                    <td>
                                        @if (! $plan->is_active)
                                            <x-status-badge tone="ghost">{{ __('pausiert') }}</x-status-badge>
                                        @elseif ($isDue)
                                            <x-status-badge tone="error">{{ __('fällig') }}</x-status-badge>
                                        @else
                                            <x-status-badge tone="success">{{ __('aktiv') }}</x-status-badge>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($canManageMaintenance)
                                            <div class="join">
                                                <form method="POST" action="{{ route('assets.maintenance-plans.complete', [$asset, $plan]) }}" class="join-item">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-ghost" title="{{ __('Erledigt') }}">
                                                        <x-icon name="check" />
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('assets.maintenance-plans.toggle', [$asset, $plan]) }}" class="join-item">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-ghost" title="{{ $plan->is_active ? __('Pausieren') : __('Reaktivieren') }}">
                                                        <x-icon :name="$plan->is_active ? 'pause' : 'play_arrow'" />
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('assets.maintenance-plans.destroy', [$asset, $plan]) }}" class="join-item"
                                                      data-confirm-dialog
                                                      data-confirm-message="{{ __('Plan wirklich löschen?') }}"
                                                      data-confirm-label="{{ __('Löschen') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-ghost text-error" title="{{ __('Löschen') }}">
                                                        <x-icon name="delete" />
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

        {{-- ── Aufträge ─────────────────────────────────────────────────────── --}}
        <x-card>
            <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="assignment" class="text-base-content/60" /> {{ __('Aufträge') }}
                <span class="font-normal text-base-content/50">({{ $visibleCounts['diary'] }})</span>
            </h2>
            @if ($diaryEntries->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">assignment</span>'
                               :title="__('Keine Aufträge')"
                               :message="__('Keine verknüpften Aufträge sichtbar.')" />
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
                                    <td>{{ optional($entry->start_at)->fdatetime() ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- ── Protokolle ───────────────────────────────────────────────────── --}}
        <x-card>
            <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="description" class="text-base-content/60" /> {{ __('Protokolle') }}
                <span class="font-normal text-base-content/50">({{ $visibleCounts['protocols'] }})</span>
            </h2>
            @if ($protocols->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">description</span>'
                               :title="__('Keine Protokolle')"
                               :message="__('Keine verknüpften Protokolle sichtbar.')" />
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
                                    <td>{{ optional($protocol->occurred_at)->fdatetime() ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- ── Materialeinsatz ──────────────────────────────────────────────── --}}
        <x-card>
            <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="inventory_2" class="text-base-content/60" /> {{ __('Materialeinsatz') }}
                <span class="font-normal text-base-content/50">({{ $visibleCounts['material'] }})</span>
            </h2>
            @if ($materialUsages->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">inventory_2</span>'
                               :title="__('Kein Materialeinsatz')"
                               :message="__('Kein verknüpfter Materialeinsatz sichtbar.')" />
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
                                        {{ optional($usage->timesheet?->work_date)->fdate() ?: ($usage->timesheet ? ('#' . $usage->timesheet->id) : '—') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{-- ── Timeline + Anhänge (nebeneinander) ───────────────────────────── --}}
        <div class="grid gap-4 md:grid-cols-2">
            <x-card>
                <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="timeline" class="text-base-content/60" /> {{ __('Timeline') }}
                    <span class="font-normal text-base-content/50">({{ $timelineEntries->count() }})</span>
                </h2>
                @if ($timelineEntries->isEmpty())
                    <x-empty-state compact icon='<span class="material-symbols-outlined">timeline</span>'
                                   :title="__('Keine Timeline-Einträge')"
                                   :message="__('Keine Timeline-Einträge vorhanden.')" />
                @else
                    <ul class="divide-y divide-base-300">
                        @foreach ($timelineEntries as $event)
                            <li class="flex items-start justify-between gap-3 py-3">
                                <div class="space-y-1">
                                    <div class="text-sm font-semibold">{{ $event['title'] }}</div>
                                    @if ($event['detail'] !== '')
                                        <div class="text-sm text-base-content/80">{{ $event['detail'] }}</div>
                                    @endif
                                </div>
                                <span class="shrink-0 text-xs text-base-content/60">{{ $event['occurred_at_formatted'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card>
                <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="attach_file" class="text-base-content/60" /> {{ __('Anhänge') }}
                    <span class="font-normal text-base-content/50">({{ $visibleCounts['attachments'] }})</span>
                </h2>
                @if ($attachments->isEmpty())
                    <x-empty-state compact icon='<span class="material-symbols-outlined">attach_file</span>'
                                   :title="__('Keine Anhänge')"
                                   :message="__('Keine verknüpften Anhänge sichtbar.')" />
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
        </div>

        @include('documents._panel', ['documentable' => $asset, 'documentableKind' => 'asset'])

        {{-- Wissensbasis (Feature 011): verknüpfte Artikel + Vorschläge zum Asset --}}
        @include('knowledge._context_card', ['subject' => $asset, 'subjectKind' => 'asset', 'texts' => [(string) $asset->name, (string) ($asset->notes ?? '')]])
    </x-page-shell>
@endsection
