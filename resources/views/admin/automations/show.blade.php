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
                <span class="badge badge-ghost">{{ __('Trigger') }}: {{ $rule->trigger_event }}</span>
                <span class="badge badge-ghost">{{ __('Priorität') }}: {{ $rule->priority }}</span>
                @if ($rule->is_active)
                    <span class="badge badge-success">{{ __('Aktiv') }}</span>
                @else
                    <span class="badge badge-error">{{ __('Inaktiv') }}</span>
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
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Zeitpunkt') }}</th>
                    <th>{{ __('Subjekt') }}</th>
                    <th>{{ __('Entscheidung') }}</th>
                    <th>{{ __('Log') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($runs as $run)
                <tr>
                    <td class="whitespace-nowrap text-sm">{{ optional($run->ran_at)->format('d.m.Y H:i:s') }}</td>
                    <td class="text-xs font-mono">{{ class_basename($run->subject_type) }}#{{ $run->subject_id }}</td>
                    <td>
                        @php($cls = match($run->decision) { 'matched' => 'badge-success', 'error' => 'badge-error', default => 'badge-ghost' })
                        <span class="badge {{ $cls }} badge-sm">{{ $run->decision }}</span>
                    </td>
                    <td><pre class="text-[10px] overflow-x-auto max-w-md">{{ json_encode($run->log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-base-content/60 py-4">{{ __('Noch keine Auswertungen.') }}</td></tr>
            @endforelse
        </x-table>
    </section>
</x-page-shell>
@endsection
