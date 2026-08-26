{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Mehrjähriges Auditprogramm (Nachtrag 044d): Zyklus je Scope/Norm,
     Audits nach Programmjahr gruppiert, Zuordnung offener Audits. --}}

@extends('layouts.app')

@section('title', __('Auditprogramme'))
@section('nav-title', __('Auditprogramme'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:subtitle>{{ __('Mehrjahres-Zyklen (z. B. Erst-/Überwachungs-/Re-Zertifizierung) — Nachweis über die verknüpften Audits.') }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="checklist" tone="ghost" size="sm" :href="route('isms.audits.index')" show-label>{{ __('isms.title.audits') }}</x-icon-btn>
                @if ($canManage)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('isms.audit-programs.create')"
                                show-label>{{ __('Programm anlegen') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($programs->isEmpty())
        <x-empty-state icon="event_repeat" :title="__('Noch kein Auditprogramm angelegt.')" framed />
    @else
        @foreach ($programs as $program)
            <x-card>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ $program->name }}</h2>
                        <p class="text-xs text-muted">
                            {{ $program->scope?->name }} ·
                            {{ trim(($program->norm ?? '—') . ' ' . ($program->edition ?? '')) }} ·
                            {{ __(':years Jahre ab :start', ['years' => $program->cycle_years, 'start' => $program->starts_on->format('d.m.Y')]) }}
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-2">
                        @if ($program->status === 'active')
                            <x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge>
                        @elseif ($program->status === 'completed')
                            <x-status-badge tone="info" size="sm">{{ __('abgeschlossen') }}</x-status-badge>
                        @else
                            <x-status-badge tone="neutral" size="sm">{{ __('abgebrochen') }}</x-status-badge>
                        @endif
                        @if ($canManage)
                            <form method="POST" action="{{ route('isms.audit-programs.update', $program) }}">
                                @csrf @method('PUT')
                                <select name="status" class="select select-xs select-bordered" data-autosubmit>
                                    <option value="">{{ __('Status …') }}</option>
                                    <option value="active">{{ __('aktiv') }}</option>
                                    <option value="completed">{{ __('abgeschlossen') }}</option>
                                    <option value="cancelled">{{ __('abgebrochen') }}</option>
                                </select>
                            </form>
                            <form method="POST" action="{{ route('isms.audit-programs.destroy', $program) }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit" :label="__('Löschen')" />
                            </form>
                        @endif
                    </span>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    @for ($year = 1; $year <= $program->cycle_years; $year++)
                        @php $yearAudits = $program->auditsByCycleYear()[$year] ?? []; @endphp
                        <div class="rounded-box border border-base-300 p-3">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">
                                {{ __('Jahr :n', ['n' => $year]) }} ({{ $program->starts_on->copy()->addYears($year - 1)->format('Y') }})
                            </p>
                            @if ($yearAudits === [])
                                <p class="text-xs text-muted">{{ __('Kein Audit geplant.') }}</p>
                            @else
                                <ul class="space-y-1 text-sm">
                                    @foreach ($yearAudits as $audit)
                                        <li>
                                            <span class="font-mono text-xs text-muted">{{ $audit->audit_no }}</span>
                                            {{ $audit->title }}
                                            <span class="text-xs text-muted">({{ $audit->status->label() }}@if ($audit->planned_on), {{ $audit->planned_on->format('d.m.Y') }}@endif)</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endfor
                </div>

                @if ($canManage && $unassignedAudits->isNotEmpty())
                    <form method="POST" action="{{ route('isms.audit-programs.update', $program) }}" class="mt-3 flex items-center gap-2">
                        @csrf @method('PUT')
                        <select name="attach_audit_id" class="select select-sm select-bordered flex-1">
                            @foreach ($unassignedAudits as $audit)
                                <option value="{{ $audit->sqid }}">{{ $audit->audit_no }} — {{ $audit->title }}</option>
                            @endforeach
                        </select>
                        <x-icon-btn icon="link" tone="outline" size="sm" type="submit" show-label>{{ __('Audit zuordnen') }}</x-icon-btn>
                    </form>
                @endif
            </x-card>
        @endforeach

        <div class="page-footer-stack">
            <x-pagination :paginator="$programs" standing />
        </div>
    @endif
</x-page-shell>
@endsection
