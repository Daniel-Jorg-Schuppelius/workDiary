@extends('layouts.app')

@section('title', __('surcharge.title.rules'))
@section('nav-title', __('surcharge.title.rules'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('surcharge.title.rules_subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.surcharge-rules.create')"
                        show-label>{{ __('surcharge.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('surcharge.title.rules_help') }}</h3>
            <div class="text-sm">{{ __('surcharge.title.rules_help_text') }}</div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('surcharge.field.code') }}</th>
                <th>{{ __('surcharge.field.label') }}</th>
                <th>{{ __('surcharge.field.kind') }}</th>
                <th>{{ __('surcharge.field.window') }}</th>
                <th class="text-right">{{ __('surcharge.field.percentage') }}</th>
                <th>{{ __('surcharge.field.wage_type_code') }}</th>
                <th class="text-right">{{ __('surcharge.field.priority') }}</th>
                <th>{{ __('surcharge.field.validity') }}</th>
                <th class="text-center">{{ __('surcharge.field.active') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($rules as $rule)
            @php /** @var \App\Models\Surcharge\SurchargeRule $rule */ @endphp
            <tr>
                <td class="font-mono text-sm">{{ $rule->code }}</td>
                <td class="font-medium">{{ $rule->label }}</td>
                <td>
                    <x-status-badge size="xs" :tone="$rule->kind->tone()">
                        <x-icon :name="$rule->kind->icon()" class="text-sm" />
                        {{ $rule->kind->label() }}
                    </x-status-badge>
                </td>
                <td class="tabular-nums text-sm">
                    @if ($rule->kind->requiresWindow() && $rule->window_start && $rule->window_end)
                        {{ substr($rule->window_start, 0, 5) }} – {{ substr($rule->window_end, 0, 5) }}
                    @else
                        <span class="opacity-50">{{ __('surcharge.field.whole_day') }}</span>
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ number_format((float) $rule->percentage, 2, ',', '.') }} %</td>
                <td class="font-mono text-sm">{{ $rule->wage_type_code ?? '—' }}</td>
                <td class="text-right tabular-nums">{{ $rule->priority }}</td>
                <td class="text-sm tabular-nums">
                    @if ($rule->valid_from || $rule->valid_until)
                        {{ $rule->valid_from?->fdate() ?? '…' }} – {{ $rule->valid_until?->fdate() ?? '…' }}
                    @else
                        <span class="opacity-50">{{ __('surcharge.field.unlimited') }}</span>
                    @endif
                </td>
                <td class="text-center">
                    @if ($rule->active)
                        <x-status-badge size="xs" tone="success">{{ __('surcharge.field.yes') }}</x-status-badge>
                    @else
                        <x-status-badge size="xs" tone="error">{{ __('surcharge.field.no') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-right">
                    @if ($canManage ?? false)
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('admin.surcharge-rules.edit', $rule)"
                                        :label="__('surcharge.action.edit')" />
                            <form method="POST" action="{{ route('admin.surcharge-rules.destroy', $rule) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('surcharge.action.delete_confirm') }}"
                                  data-confirm-label="{{ __('surcharge.action.delete') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('surcharge.action.delete')" />
                            </form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">percent</span>' :colspan="10" :title="__('surcharge.title.empty')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
