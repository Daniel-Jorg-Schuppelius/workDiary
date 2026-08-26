{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Change-Detail (Feature 065, MVP-157): Pläne, eingefrorener
     Vorlagen-Snapshot (READ-ONLY), Genehmigungsstatus (Entscheide fallen
     in der gemeinsamen Inbox!), verknüpfte Tickets/Problem/Assets, PIR. --}}

@extends('layouts.app')
@section('title', __('Change') . ': ' . $change->title)
@section('nav-title', __('Change'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$change->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('servicedesk.changes.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
                @if ($canManage && in_array($change->status, ['approved', 'implementing'], true))
                    <x-icon-btn icon="task_alt" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('servicedesk.changes.complete-form', $change)"
                                show-label>{{ __('Abschließen') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <x-status-badge tone="ghost" size="md">{{ $typeLabels[$change->change_type] ?? $change->change_type }}</x-status-badge>
            <x-status-badge size="md" outline>{{ $statusLabels[$change->status] ?? $change->status }}</x-status-badge>
            @if ($change->outcome !== null)
                <x-status-badge tone="info" size="md">{{ $outcomeLabels[$change->outcome] ?? $change->outcome }}</x-status-badge>
            @endif
            <span class="ml-auto text-sm text-muted">
                {{ __('Angelegt von') }}: {{ $change->creator?->name ?? '—' }}
            </span>
        </div>

        <dl class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div>
                <dt class="text-muted">{{ __('Wartungsfenster') }}</dt>
                <dd>
                    @if ($change->window_from !== null)
                        {{ $change->window_from->translatedFormat('d.m.Y H:i') }}
                        – {{ $change->window_to?->translatedFormat('d.m.Y H:i') ?? '…' }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-muted">{{ __('Risiko / Auswirkung / Dringlichkeit') }}</dt>
                <dd class="tabular-nums">{{ $change->risk ?? '—' }} / {{ $change->impact ?? '—' }} / {{ $change->urgency ?? '—' }}</dd>
            </div>
            @if ($change->reason)
                <div class="md:col-span-2">
                    <dt class="text-muted">{{ __('Grund') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $change->reason }}</dd>
                </div>
            @endif
            @if ($change->scope)
                <div class="md:col-span-2">
                    <dt class="text-muted">{{ __('Umfang') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $change->scope }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    <x-card :title="__('Pläne')" icon="checklist">
        <dl class="grid grid-cols-1 gap-y-3 text-sm">
            <div><dt class="text-muted">{{ __('Umsetzungsplan') }}</dt><dd class="whitespace-pre-wrap">{{ $change->implementation_plan ?: '—' }}</dd></div>
            <div><dt class="text-muted">{{ __('Testplan') }}</dt><dd class="whitespace-pre-wrap">{{ $change->test_plan ?: '—' }}</dd></div>
            <div><dt class="text-muted">{{ __('Rollback-Plan') }}</dt><dd class="whitespace-pre-wrap">{{ $change->rollback_plan ?: '—' }}</dd></div>
        </dl>

        @if ($canManage && $change->status === 'approved')
            <form method="POST" action="{{ route('servicedesk.changes.implement', $change) }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                @if ($procedureTemplates->isNotEmpty())
                    <div class="fieldset">
                        <label class="fieldset-label" for="procedure_template_id">{{ __('Optional als Verfahrenslauf starten') }}</label>
                        <select id="procedure_template_id" name="procedure_template_id" class="select select-sm select-bordered w-64">
                            <option value="">—</option>
                            @foreach ($procedureTemplates as $template)
                                <option value="{{ $template->sqid }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('Umsetzung starten') }}</x-icon-btn>
            </form>
        @endif
    </x-card>

    @if ($change->template_snapshot !== null)
        {{-- Eingefrorener Vorlagenstand — bewusst READ-ONLY (MVP-157):
             spätere Vorlagenänderungen deuten den Change nicht um. --}}
        <x-card :title="__('Vorlagen-Snapshot (eingefroren)')" icon="ac_unit">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div><dt class="text-muted">{{ __('Vorlage') }}</dt><dd>{{ $change->template_snapshot['name'] ?? '—' }}</dd></div>
                <div><dt class="text-muted">{{ __('Version') }}</dt><dd class="tabular-nums">{{ $change->template_snapshot['version'] ?? '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-muted">{{ __('Rollback-Plan') }}</dt><dd class="whitespace-pre-wrap">{{ $change->template_snapshot['rollback_plan'] ?? '—' }}</dd></div>
            </dl>
        </x-card>
    @endif

    <x-card :title="__('Freigaben')" icon="approval">
        @if ($change->approvals->isEmpty())
            <p class="text-sm text-muted">
                {{ $change->change_type === 'standard'
                    ? __('Standard-Change — vorab genehmigt über die freigegebene Vorlage.')
                    : __('Keine Genehmigungsschritte hinterlegt.') }}
            </p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($change->approvals as $approval)
                    @php $rule = (array) $approval->approver_rule; @endphp
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs">{{ __('Schritt') }} {{ $approval->step }}</span>
                        <span class="text-muted">
                            @if ((string) ($rule['type'] ?? '') === 'role')
                                {{ __('Rolle') }}: {{ \App\Enums\User\UserRole::tryFrom((string) ($rule['value'] ?? ''))?->label() ?? (string) ($rule['value'] ?? '') }}
                            @else
                                {{ __('Persönlich') }}
                            @endif
                        </span>
                        @if ($approval->decision === null)
                            <x-status-badge tone="info" size="xs">{{ __('Offen') }}</x-status-badge>
                        @else
                            <x-status-badge size="xs" outline>{{ $approval->decision }}</x-status-badge>
                            @if ($approval->decidedBy !== null)
                                <span class="text-muted">{{ $approval->decidedBy->name }}</span>
                            @endif
                            @if ($approval->reason)
                                <span class="text-muted">— {{ $approval->reason }}</span>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="text-xs text-muted mt-2">{{ __('Entschieden wird in der gemeinsamen Genehmigungs-Inbox.') }}</p>
    </x-card>

    <x-card :title="__('Verknüpfungen')" icon="link">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-xs uppercase text-muted mb-1">{{ __('Tickets') }}</div>
                @if ($change->tickets->isEmpty())
                    <p class="text-muted">—</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($change->tickets as $ticket)
                            <li>
                                <a href="{{ route('service-tickets.show', $ticket) }}" class="link link-hover">
                                    <span class="font-mono text-xs">{{ $ticket->ticket_no }}</span>
                                    {{ $ticket->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <div class="text-xs uppercase text-muted mb-1">{{ __('Problem') }}</div>
                @if ($change->problem !== null)
                    <a href="{{ route('servicedesk.problems.show', $change->problem) }}" class="link link-hover">{{ $change->problem->title }}</a>
                @else
                    <p class="text-muted">—</p>
                @endif
            </div>
        </div>
    </x-card>

    <x-card :title="__('Betroffene Assets')" icon="devices">
        @if ($change->assets->isEmpty())
            <p class="text-sm text-muted">{{ __('Keine Assets verknüpft.') }}</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($change->assets as $asset)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs">{{ $asset->asset_no }}</span>
                        <span>{{ $asset->name }}</span>
                        @if ($canManage)
                            <x-action-form :action="route('servicedesk.changes.assets.destroy', [$change, $asset])"
                                           method="DELETE" class="inline">
                                <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :label="__('Asset-Verknüpfung entfernen')" />
                            </x-action-form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canManage && $assetOptions->isNotEmpty())
            <form method="POST" action="{{ route('servicedesk.changes.assets.store', $change) }}" class="mt-3 flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset">
                    <label class="fieldset-label" for="asset_id">{{ __('Asset verknüpfen') }}</label>
                    <select id="asset_id" name="asset_id" required class="select select-sm select-bordered w-64">
                        <option value="">—</option>
                        @foreach ($assetOptions as $asset)
                            <option value="{{ $asset->sqid }}">{{ $asset->asset_no }} — {{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-icon-btn icon="add_link" tone="primary" size="sm" type="submit" show-label>{{ __('Verknüpfen') }}</x-icon-btn>
                @error('asset_id')<p class="text-error text-xs w-full">{{ $message }}</p>@enderror
            </form>
        @endif
    </x-card>

    <x-card :title="__('Post Implementation Review (PIR)')" icon="fact_check">
        @if ($change->pir_notes)
            <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ $change->pir_notes }}</div>
            <p class="text-xs text-muted mt-2">{{ __('Dokumentiert am') }} {{ $change->pir_done_at?->translatedFormat('d.m.Y H:i') ?? '—' }}</p>
        @else
            <p class="text-sm text-muted">
                {{ $change->change_type === 'emergency'
                    ? __('Pflicht bei Emergency-Changes — ohne PIR kein Abschluss.')
                    : __('Noch kein PIR dokumentiert.') }}
            </p>
        @endif
    </x-card>
</x-page-shell>
@endsection
