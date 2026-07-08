{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', $rule->name)
@section('nav-title', __('Automatisierung: :name', ['name' => $rule->name]))

@section('content')
<x-page-shell>
    <a href="{{ route('admin.automations.index') }}" class="link link-hover text-sm">&larr; {{ __('Zur Übersicht') }}</a>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body space-y-2">
            <h2 class="card-title">{{ $rule->name }}</h2>
            <div class="flex flex-wrap gap-2 text-sm">
                <x-status-badge tone="ghost">{{ __('Trigger') }}: {{ $rule->trigger_event }}</x-status-badge>
                <x-status-badge tone="ghost">{{ __('Priorität') }}: {{ $rule->priority }}</x-status-badge>
                @if ($rule->is_active)
                    <x-status-badge tone="success">{{ __('Aktiv') }}</x-status-badge>
                @else
                    <x-status-badge tone="error">{{ __('Inaktiv') }}</x-status-badge>
                @endif
            </div>
            <div>
                <h3 class="font-semibold text-sm mt-2">{{ __('Bedingungen') }}</h3>
                <pre class="bg-base-200 p-3 rounded text-xs overflow-x-auto">{{ json_encode($rule->conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            <div>
                <h3 class="font-semibold text-sm">{{ __('Aktionen') }}</h3>
                <pre class="bg-base-200 p-3 rounded text-xs overflow-x-auto">{{ json_encode($rule->actions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>

    <section>
        <h3 class="font-semibold mb-2">{{ __('Audit-Log (letzte 50)') }}</h3>
        <x-table table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date" default="desc">{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Subjekt') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Entscheidung') }}</x-table.th>
                    <th>{{ __('Log') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($runs as $run)
                <tr>
                    <td class="whitespace-nowrap text-sm" data-sort-value="{{ optional($run->ran_at)->format('Y-m-d H:i:s') ?? '' }}">{{ optional($run->ran_at)->format('d.m.Y H:i:s') }}</td>
                    <td class="text-xs font-mono">{{ \App\Support\EntityType::label($run->subject_type) }}#{{ $run->subject_id }}</td>
                    <td>
                        @php($cls = match($run->decision) { 'matched' => 'badge-success', 'error' => 'badge-error', default => 'badge-ghost' })
                        <span class="badge {{ $cls }} badge-sm">{{ $run->decision }}</span>
                    </td>
                    <td><pre class="text-[10px] overflow-x-auto max-w-md">{{ json_encode($run->log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon='<span class="material-symbols-outlined" aria-hidden="true">rule</span>' :title="__('Noch keine Auswertungen.')" compact />
            @endforelse
        </x-table>
    </section>
</x-page-shell>
@endsection
