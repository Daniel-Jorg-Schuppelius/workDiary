@extends('layouts.app')

@section('title', __('Automatisierungen'))
@section('nav-title', __('Automatisierungen'))

@section('content')
<x-page-shell gap="6">
    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="bolt" />
        <div>
            <h3 class="font-semibold">{{ __('Workflow-Automatisierung') }}</h3>
            <div class="text-sm">
                {{ __('Regeln werten bei festgelegten Ereignissen Bedingungen aus und führen Aktionen automatisch aus (z. B. Kleinbetragsspesen genehmigen). Jede Auswertung wird im Audit-Log protokolliert.') }}
            </div>
        </div>
    </div>

    <x-table>
        <x-slot:head>
            <tr>
                <th class="w-16">{{ __('Prio') }}</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Trigger') }}</th>
                <th>{{ __('Aktion(en)') }}</th>
                <th class="text-center">{{ __('Aktiv') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($rules as $rule)
            <tr>
                <td class="tabular-nums">{{ $rule->priority }}</td>
                <td class="font-medium">
                    <a href="{{ route('admin.automations.show', $rule) }}" class="link link-hover">{{ $rule->name }}</a>
                </td>
                <td><code class="text-xs">{{ $rule->trigger_event }}</code></td>
                <td class="text-xs">
                    @foreach ((array) $rule->actions as $a)
                        <span class="badge badge-ghost badge-sm">{{ $a['type'] ?? '?' }}</span>
                    @endforeach
                </td>
                <td class="text-center">
                    @if ($rule->is_active)
                        <span class="badge badge-success badge-sm">{{ __('Ja') }}</span>
                    @else
                        <span class="badge badge-ghost badge-sm">{{ __('Nein') }}</span>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <form method="POST" action="{{ route('admin.automations.toggle', $rule) }}" class="inline">
                            @csrf
                            <x-icon-btn icon="{{ $rule->is_active ? 'pause' : 'play_arrow' }}" type="submit"
                                        :label="$rule->is_active ? __('Deaktivieren') : __('Aktivieren')" />
                        </form>
                        <form method="POST" action="{{ route('admin.automations.destroy', $rule) }}" class="inline"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Regel wirklich löschen?') }}"
                              data-confirm-label="{{ __('Löschen') }}">
                            @csrf @method('DELETE')
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-base-content/60 py-8">{{ __('Keine Regeln definiert.') }}</td>
            </tr>
        @endforelse
    </x-table>

    <details class="collapse collapse-arrow bg-base-200">
        <summary class="collapse-title font-medium">{{ __('Neue Regel anlegen (JSON)') }}</summary>
        <div class="collapse-content">
            <form method="POST" action="{{ route('admin.automations.store') }}" class="space-y-3">
                @csrf
                <x-form-group :label="__('Name')" name="name">
                    <input type="text" name="name" class="input input-bordered w-full" required maxlength="255">
                </x-form-group>
                <x-form-group :label="__('Trigger-Event')" name="trigger_event">
                    <input type="text" name="trigger_event" class="input input-bordered w-full" value="expense.submitted" required>
                </x-form-group>
                <x-form-group :label="__('Priorität')" name="priority">
                    <input type="number" name="priority" class="input input-bordered w-32" value="100" min="1" max="9999">
                </x-form-group>
                <x-form-group :label="__('Bedingungen (JSON)')" name="conditions">
                    <textarea name="conditions" rows="4" class="textarea textarea-bordered w-full font-mono text-xs" required>{"all":[{"field":"amount_gross","op":"<=","value":50}]}</textarea>
                </x-form-group>
                <x-form-group :label="__('Aktionen (JSON)')" name="actions">
                    <textarea name="actions" rows="3" class="textarea textarea-bordered w-full font-mono text-xs" required>[{"type":"expense.approve","params":{}}]</textarea>
                </x-form-group>
                <button type="submit" class="btn btn-primary">{{ __('Regel anlegen') }}</button>
            </form>
        </div>
    </details>
</x-page-shell>
@endsection
