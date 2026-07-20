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
                        <x-status-badge :tone="$lifecycle['phase_tone']">{{ $lifecycle['phase_label'] }}</x-status-badge>
                        @if ($asset->serial_no)
                            <span class="text-base-content/70">{{ __('Seriennummer') }}: {{ $asset->serial_no }}</span>
                        @endif
                    </div>
                    @if ($asset->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-1">
                            @foreach ($asset->tags as $tag)
                                <span class="badge badge-sm badge-outline"
                                      @if ($tag->color) style="border-color: {{ $tag->color }}; color: {{ $tag->color }};" @endif>#{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if ($canUnblock && $assetStatusValue === \App\Enums\Asset\AssetStatus::Blocked->value)
                        <x-action-form :action="route('assets.unblock', $asset)">
                            <x-icon-btn icon="lock_open" tone="success" size="sm" type="submit" show-label>{{ __('Sperre aufheben') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    <x-icon-btn icon="description" size="sm" :href="route('assets.dossier', $asset)" target="_blank" show-label>{{ __('Objektakte') }}</x-icon-btn>
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
            <x-card :title="__('Verortung')" icon="location_on">
                @if ($canEditAsset)
                    <x-slot:actions>
                        <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('assets.edit', $asset)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    </x-slot:actions>
                @endif
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

                @if ($roomRequirements->isNotEmpty() || $room?->cleaningProfile)
                    {{-- Raumbezogene Anforderungen des Standort-Raums (Feature 027). --}}
                    <div class="mt-4 border-t border-base-200 pt-3">
                        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold">
                            <x-icon name="rule" class="text-warning" /> {{ __('Raumanforderungen') }}
                        </h3>
                        <div class="flex flex-wrap gap-1">
                            @if ($room?->cleaningProfile)
                                <x-status-badge tone="info">{{ __('Reinigung') }}: {{ $room->cleaningProfile->label }}</x-status-badge>
                            @endif
                            @foreach ($roomRequirements as $req)
                                <x-status-badge tone="warning" :title="$req->note ?? ''">
                                    {{ $req->kind->label() }}@if ($req->level): {{ $req->level }}@endif
                                </x-status-badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-card>

            <x-card :title="__('Betriebssystem')" icon="desktop_windows">
                @if ($canEditAsset)
                    <x-slot:actions>
                        <x-icon-btn icon="add" tone="primary" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('assets.software-installations.create', ['asset' => $asset, 'os' => 1])"
                                    show-label>{{ $os ? __('OS ersetzen') : __('OS zuweisen') }}</x-icon-btn>
                    </x-slot:actions>
                @endif
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
        <x-card :title="__('Installierte Software')" icon="apps" :count="$installations->where('is_operating_system', false)->count()">
            @if ($canEditAsset)
                <x-slot:actions>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.software-installations.create', $asset)"
                                show-label>{{ __('Software zuweisen') }}</x-icon-btn>
                </x-slot:actions>
            @endif
            @php $apps = $installations->where('is_operating_system', false); @endphp
            @if ($apps->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">apps</span>'
                               :title="__('Keine Software')"
                               :message="__('Noch keine Software hinterlegt.')" />
            @else
                <x-table>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Version') }}</th>
                                <th>{{ __('Sitze') }}</th>
                                <th>{{ __('Installiert') }}</th>
                                <th>{{ __('Läuft ab') }}</th>
                                <th></th>
                            </tr>
                    </x-slot:head>
                            @foreach ($apps as $inst)
                                <tr class="hover">
                                    <td class="font-medium">{{ $inst->software?->name }}<div class="text-xs text-base-content/60">{{ $inst->software?->vendor }}</div></td>
                                    <td>{{ $inst->version ?: '—' }}</td>
                                    <td>{{ $inst->seats ?: '—' }}</td>
                                    <td>{{ $inst->installed_on?->isoFormat('L') ?: '—' }}</td>
                                    <td>{{ $inst->expires_on?->isoFormat('L') ?: '—' }}</td>
                                    <td class="text-right">
                                        @if ($canEditAsset)
                                            <x-action-form :action="route('assets.software-installations.destroy', [$asset, $inst])"
                                                  method="DELETE"
                                                  :confirm="__('Diese Software wirklich entfernen?')"
                                                  :confirm-label="__('Entfernen')">
                                                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" />
                                            </x-action-form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- ── Wartungspläne ────────────────────────────────────────────────── --}}
        <x-card :title="__('Wartungspläne')" icon="event_repeat" :count="$maintenancePlans->count()">
            @if ($canManageMaintenance)
                <x-slot:actions>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.maintenance-plans.create', $asset)"
                                show-label>{{ __('Plan anlegen') }}</x-icon-btn>
                </x-slot:actions>
            @endif

            @if ($maintenancePlans->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">event_repeat</span>'
                               :title="__('Keine Wartungspläne')"
                               :message="__('Noch keine Wartungspläne hinterlegt.')" />
            @else
                <x-table bare :size="null">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Plan') }}</th>
                                <th>{{ __('Intervall') }}</th>
                                <th>{{ __('Nächste Fälligkeit') }}</th>
                                <th>{{ __('Letzte Ausführung') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Aktionen') }}</th>
                            </tr>
                    </x-slot:head>
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
                                                <x-action-form :action="route('assets.maintenance-plans.complete', [$asset, $plan])" class="join-item">
                                                    <x-icon-btn type="submit" tone="ghost" size="xs" icon="check" :label="__('Erledigt')" />
                                                </x-action-form>
                                                <x-action-form :action="route('assets.maintenance-plans.toggle', [$asset, $plan])" class="join-item">
                                                    <x-icon-btn type="submit" tone="ghost" size="xs" :icon="$plan->is_active ? 'pause' : 'play_arrow'" :label="$plan->is_active ? __('Pausieren') : __('Reaktivieren')" />
                                                </x-action-form>
                                                <x-action-form :action="route('assets.maintenance-plans.destroy', [$asset, $plan])" class="join-item"
                                                      method="DELETE"
                                                      :confirm="__('Plan wirklich löschen?')"
                                                      :confirm-label="__('Löschen')">
                                                    <x-icon-btn type="submit" tone="error" size="xs" icon="delete" :label="__('Löschen')" />
                                                </x-action-form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- ── Ausgabe / Rückgabe (Feature 009) ─────────────────────────────── --}}
        <x-card :title="__('Ausgabe / Rückgabe')" icon="swap_horiz">
            @if ($canCheckout && ! $isCheckedOut && ! $isDefectBlocked && empty($activeBlocks))
                <x-slot:actions>
                    <x-icon-btn icon="logout" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.checkout.create', $asset)"
                                show-label>{{ __('Ausgeben') }}</x-icon-btn>
                </x-slot:actions>
            @endif

            @if ($isDefectBlocked && ! $isCheckedOut)
                <div class="alert alert-warning mb-3">
                    <x-icon name="lock" />
                    <span>{{ __('Gesperrt wegen Defekt — keine Ausgabe möglich.') }}</span>
                </div>
            @endif

            @if (! empty($activeBlocks) && ! $isCheckedOut)
                {{-- Vollaudit 2026-07 (H2/H3): D12-Sperren sichtbar machen. --}}
                <div class="alert alert-warning mb-3">
                    <x-icon name="lock" />
                    <span>
                        {{ __('Gesperrt (:reasons) — keine Ausgabe möglich.', ['reasons' => collect($activeBlocks)->pluck('reason_label')->implode(', ')]) }}
                    </span>
                </div>
            @endif

            @if ($currentAssignment)
                @php
                    $overdue = $currentAssignment->isOverdue();
                    $targetName = $currentAssignment->assignedToUser?->name
                        ?? $currentAssignment->assignedToTeam?->name
                        ?? '—';
                @endphp
                <div class="rounded-lg border {{ $overdue ? 'border-error' : 'border-base-300' }} p-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1 text-sm">
                            <div class="font-semibold">{{ __('Aktuell ausgegeben an') }}: {{ $targetName }}</div>
                            <div class="text-base-content/70">
                                {{ __('Seit') }}: {{ optional($currentAssignment->checked_out_at)->fdatetime() }}
                                @if ($currentAssignment->expected_return_at)
                                    · {{ __('Erwartete Rückgabe') }}:
                                    <span class="@if ($overdue) text-error font-semibold @endif">{{ $currentAssignment->expected_return_at->fdatetime() }}</span>
                                    @if ($overdue)
                                        <x-status-badge tone="error" size="sm">{{ __('überfällig') }}</x-status-badge>
                                    @endif
                                @endif
                            </div>
                            @if ($currentAssignment->diaryEntry)
                                <div class="text-base-content/70">{{ __('Auftrag') }}:
                                    <a class="link link-hover" href="{{ route('diary.show', $currentAssignment->diaryEntry) }}">{{ $currentAssignment->diaryEntry->title ?: ('#' . $currentAssignment->diaryEntry->id) }}</a>
                                </div>
                            @endif
                            @if ($currentAssignment->condition_out)
                                <div class="text-base-content/70">{{ __('Zustand bei Ausgabe') }}: {{ $currentAssignment->condition_out }}</div>
                            @endif
                        </div>
                        @if ($canCheckout)
                            <x-action-form :action="route('assets.checkout.return', [$asset, $currentAssignment])">
                                <x-icon-btn icon="login" tone="success" size="sm" type="submit" show-label>{{ __('Zurücknehmen') }}</x-icon-btn>
                            </x-action-form>
                        @endif
                    </div>
                </div>
            @else
                <x-empty-state compact icon='<span class="material-symbols-outlined">check_circle</span>'
                               :title="__('Verfügbar')"
                               :message="__('Das Asset ist aktuell nicht ausgegeben.')" />
            @endif

            @if ($assignmentHistory->isNotEmpty())
                <x-table class="mt-4">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Empfänger') }}</th>
                                <th>{{ __('Ausgegeben') }}</th>
                                <th>{{ __('Zurückgegeben') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($assignmentHistory as $past)
                                <tr>
                                    <td>{{ $past->assignedToUser?->name ?? $past->assignedToTeam?->name ?? '—' }}</td>
                                    <td>{{ optional($past->checked_out_at)->fdatetime() ?: '—' }}</td>
                                    <td>{{ optional($past->returned_at)->fdatetime() ?: '—' }}</td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- ── Defekte / Sperren (Feature 009) ──────────────────────────────── --}}
        <x-card :title="__('Defekte / Sperren')" icon="report" :count="$defects->count()">
            @if ($canManageDefects)
                <x-slot:actions>
                    <x-icon-btn icon="add" tone="error" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.defects.create', $asset)"
                                show-label>{{ __('Defekt melden') }}</x-icon-btn>
                </x-slot:actions>
            @endif

            @if ($defects->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">verified</span>'
                               :title="__('Keine Defekte')"
                               :message="__('Es liegen keine Defektmeldungen vor.')" />
            @else
                <x-table bare :size="null">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Titel') }}</th>
                                <th>{{ __('Schweregrad') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Sperrt') }}</th>
                                <th>{{ __('Gemeldet') }}</th>
                                @if ($canManageDefects)
                                    <th class="text-end">{{ __('Aktionen') }}</th>
                                @endif
                            </tr>
                    </x-slot:head>
                            @foreach ($defects as $defect)
                                <tr>
                                    <td>
                                        <div class="font-medium">{{ $defect->title }}</div>
                                        @if ($defect->description)
                                            <div class="text-xs text-base-content/60">{{ \Illuminate\Support\Str::limit($defect->description, 80) }}</div>
                                        @endif
                                        @if ($defect->attachments->isNotEmpty())
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach ($defect->attachments as $photo)
                                                    <a href="{{ route('attachments.download', $photo) }}" target="_blank" rel="noopener"
                                                       class="badge badge-ghost badge-sm gap-1" title="{{ $photo->original_name }}">
                                                        <span class="material-symbols-outlined text-sm" aria-hidden="true">image</span>
                                                        {{ \Illuminate\Support\Str::limit($photo->original_name, 16) }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td><x-status-badge :tone="$defect->severity->tone()" size="sm">{{ $defect->severity->label() }}</x-status-badge></td>
                                    <td><x-status-badge :tone="$defect->status->tone()" size="sm">{{ $defect->status->label() }}</x-status-badge></td>
                                    <td>
                                        @if ($defect->isBlocking())
                                            <x-status-badge tone="error" size="sm">{{ __('gesperrt') }}</x-status-badge>
                                        @else
                                            <span class="text-base-content/50">—</span>
                                        @endif
                                    </td>
                                    <td class="text-base-content/70">{{ optional($defect->reported_at)->fdate() ?: '—' }}</td>
                                    @if ($canManageDefects)
                                        <td class="text-end">
                                            @if ($defect->status->isOpen())
                                                <div class="join">
                                                    @if ($defect->status === \App\Enums\Asset\DefectStatus::Open)
                                                        <x-action-form :action="route('assets.defects.transition', [$asset, $defect])" class="join-item">
                                                            <input type="hidden" name="action" value="inRepair" />
                                                            <x-icon-btn type="submit" tone="ghost" size="xs" icon="build" :label="__('In Reparatur')" />
                                                        </x-action-form>
                                                    @endif
                                                    <a class="btn btn-xs btn-ghost text-success join-item" title="{{ __('Erledigen') }}"
                                                       data-entry-modal-trigger
                                                       href="{{ route('assets.defects.resolve-form', [$asset, $defect, 'action' => 'resolve']) }}"><x-icon name="check" /></a>
                                                    <a class="btn btn-xs btn-ghost text-error join-item" title="{{ __('Ausbuchen') }}"
                                                       data-entry-modal-trigger
                                                       href="{{ route('assets.defects.resolve-form', [$asset, $defect, 'action' => 'writeOff']) }}"><x-icon name="delete_forever" /></a>
                                                </div>
                                            @else
                                                <span class="text-xs text-base-content/50">{{ optional($defect->resolved_at)->fdate() }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- ── Aufträge ─────────────────────────────────────────────────────── --}}
        <x-card :title="__('Aufträge')" icon="assignment" :count="$visibleCounts['diary']">
            @if ($diaryEntries->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">assignment</span>'
                               :title="__('Keine Aufträge')"
                               :message="__('Keine verknüpften Aufträge sichtbar.')" />
            @else
                <x-table bare :size="null">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Titel') }}</th>
                                <th>{{ __('Projekt') }}</th>
                                <th>{{ __('Mitarbeiter') }}</th>
                                <th>{{ __('Start') }}</th>
                            </tr>
                    </x-slot:head>
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
                </x-table>
            @endif
        </x-card>

        {{-- ── Protokolle ───────────────────────────────────────────────────── --}}
        <x-card :title="__('Protokolle')" icon="description" :count="$visibleCounts['protocols']">
            @if ($protocols->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">description</span>'
                               :title="__('Keine Protokolle')"
                               :message="__('Keine verknüpften Protokolle sichtbar.')" />
            @else
                <x-table bare :size="null">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Titel') }}</th>
                                <th>{{ __('Typ') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Datum') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($protocols as $protocol)
                                <tr>
                                    <td>{{ $protocol->title }}</td>
                                    <td>{{ $protocol->type->label() }}</td>
                                    <td>{{ $protocol->status->label() }}</td>
                                    <td>{{ optional($protocol->occurred_at)->fdatetime() ?: '—' }}</td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- ── Materialeinsatz ──────────────────────────────────────────────── --}}
        <x-card :title="__('Materialeinsatz')" icon="inventory_2" :count="$visibleCounts['material']">
            @if ($materialUsages->isEmpty())
                <x-empty-state compact icon='<span class="material-symbols-outlined">inventory_2</span>'
                               :title="__('Kein Materialeinsatz')"
                               :message="__('Kein verknüpfter Materialeinsatz sichtbar.')" />
            @else
                <x-table bare :size="null">
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Beschreibung') }}</th>
                                <th>{{ __('Menge') }}</th>
                                <th>{{ __('Nettobetrag') }}</th>
                                <th>{{ __('Stundenzettel') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($materialUsages as $usage)
                                <tr>
                                    <td>{{ $usage->description }}</td>
                                    <td>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $usage->quantity, 3, withThousandsSeparator: true) }} {{ $usage->unit }}</td>
                                    <td>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $usage->line_total_net, 2, withThousandsSeparator: true) }} €</td>
                                    <td>
                                        {{ optional($usage->timesheet?->work_date)->fdate() ?: ($usage->timesheet ? ('#' . $usage->timesheet->id) : '—') }}
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- ── Timeline + Anhänge (nebeneinander) ───────────────────────────── --}}
        <div class="grid gap-4 md:grid-cols-2">
            <x-card :title="__('Timeline')" icon="timeline" :count="$timelineEntries->count()">
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

            <x-card :title="__('Anhänge')" icon="attach_file" :count="$visibleCounts['attachments']">
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

        {{-- Vollaudit 2026-07 (M12): Kommunikationsnotizen am Objekt/Asset (Spec §5). --}}
        @include('communication-notes._panel', ['notable' => $asset, 'notableKind' => 'asset'])

        {{-- Wissensbasis (Feature 011): verknüpfte Artikel + Vorschläge zum Asset --}}
        @include('knowledge._context_card', ['subject' => $asset, 'subjectKind' => 'asset', 'texts' => [(string) $asset->name, (string) ($asset->notes ?? '')]])
    </x-page-shell>
@endsection
