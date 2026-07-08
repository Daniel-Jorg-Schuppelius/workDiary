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
    <x-page-toolbar>
        <x-slot:title>{{ __('Auditprogramme') }}</x-slot:title>
        <x-slot:subtitle>{{ __('Mehrjahres-Zyklen (z. B. Erst-/Überwachungs-/Re-Zertifizierung) — Nachweis über die verknüpften Audits.') }}</x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="checklist" tone="ghost" size="sm" :href="route('isms.audits.index')" show-label>{{ __('isms.title.audits') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    @if ($canManage)
        <x-card :title="__('Programm anlegen')">
            <form method="POST" action="{{ route('isms.audit-programs.store') }}">
                @csrf
                <x-form-group :legend="__('Auditprogramm')" icon="event_repeat" tone="primary" cols="3" compact>
                    <div class="fieldset md:col-span-2">
                        <label class="fieldset-label">{{ __('Name') }}</label>
                        <input name="name" required minlength="3" maxlength="180" class="input input-bordered w-full"
                               placeholder="{{ __('z. B. ISO-27001-Zyklus 2026–2028') }}" value="{{ old('name') }}">
                        @error('name')<p class="text-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Geltungsbereich') }}</label>
                        <select name="isms_scope_id" class="select select-bordered w-full" required>
                            @foreach ($scopes as $scopeOption)
                                <option value="{{ $scopeOption->id }}">{{ $scopeOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Norm') }}</label>
                        <input name="norm" maxlength="64" class="input input-bordered w-full" placeholder="ISO/IEC 27001" value="{{ old('norm') }}">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Ausgabe') }}</label>
                        <input name="edition" maxlength="16" class="input input-bordered w-full" placeholder="2022" value="{{ old('edition') }}">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Zyklus (Jahre)') }}</label>
                        <input name="cycle_years" type="number" min="1" max="6" value="{{ old('cycle_years', 3) }}" class="input input-bordered w-full" required>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Beginn') }}</label>
                        <input name="starts_on" type="date" value="{{ old('starts_on', now()->format('Y-m-d')) }}" class="input input-bordered w-full" required>
                    </div>
                    <div class="fieldset md:col-span-2">
                        <label class="fieldset-label">{{ __('Notizen') }}</label>
                        <input name="notes" maxlength="5000" class="input input-bordered w-full" value="{{ old('notes') }}">
                    </div>
                </x-form-group>
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Programm anlegen') }}</x-icon-btn>
            </form>
        </x-card>
    @endif

    @if ($programs->isEmpty())
        <x-empty-state icon="event_repeat" :title="__('Noch kein Auditprogramm angelegt.')" framed />
    @else
        @foreach ($programs as $program)
            <x-card>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ $program->name }}</h2>
                        <p class="text-xs text-base-content/60">
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
                                <select name="status" class="select select-xs select-bordered" onchange="this.form.submit()">
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
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-base-content/60">
                                {{ __('Jahr :n', ['n' => $year]) }} ({{ $program->starts_on->copy()->addYears($year - 1)->format('Y') }})
                            </p>
                            @if ($yearAudits === [])
                                <p class="text-xs text-base-content/50">{{ __('Kein Audit geplant.') }}</p>
                            @else
                                <ul class="space-y-1 text-sm">
                                    @foreach ($yearAudits as $audit)
                                        <li>
                                            <span class="font-mono text-xs text-base-content/60">{{ $audit->audit_no }}</span>
                                            {{ $audit->title }}
                                            <span class="text-xs text-base-content/60">({{ $audit->status->label() }}@if ($audit->planned_on), {{ $audit->planned_on->format('d.m.Y') }}@endif)</span>
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
                                <option value="{{ $audit->id }}">{{ $audit->audit_no }} — {{ $audit->title }}</option>
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
