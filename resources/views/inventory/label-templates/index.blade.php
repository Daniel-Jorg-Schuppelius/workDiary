@extends('layouts.app')
@section('title', __('inventory.label_template.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.label_template.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.label_template.subtitle')">
    @if ($canManage)
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('inventory.label-templates.create')" show-label>{{ __('inventory.label_template.add') }}</x-icon-btn>
        </x-slot:actions>
    @endif

    @if ($templates->isEmpty())
        <x-empty-state framed :title="__('inventory.label_template.empty')" />
    @else
        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('inventory.label_template.name') }}</th>
                        <th>{{ __('inventory.label_template.paper_size') }}</th>
                        <th>{{ __('inventory.label_template.fields') }}</th>
                        @if ($canManage)<th></th>@endif
                    </tr>
                </x-slot:head>
                @forelse ($templates as $tpl)
                    <tr>
                        <td>{{ $tpl->name }} @if ($tpl->is_default)<span class="badge badge-sm badge-primary">{{ __('inventory.label_template.default') }}</span>@endif</td>
                        <td class="uppercase">{{ $tpl->paper_size }} · {{ __('inventory.label_template.orientation_' . $tpl->orientation) }}{{ $tpl->with_qr ? ' · QR' : '' }}</td>
                        <td class="text-xs">{{ collect($tpl->fields)->map(fn ($f) => __('inventory.label_template.field.' . $f))->implode(', ') }}</td>
                        @if ($canManage)
                            <td class="text-right whitespace-nowrap">
                                <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger :href="route('inventory.label-templates.edit', $tpl)" :title="__('Bearbeiten')" />
                                <form method="POST" action="{{ route('inventory.label-templates.destroy', $tpl) }}" class="inline" onsubmit="return confirm('{{ __('inventory.label_template.delete') }}?')">@csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :title="__('inventory.label_template.delete')" />
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <x-table.empty :colspan="$canManage ? 4 : 3"
                                   icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>'
                                   :title="__('inventory.label_template.empty')" compact />
                @endforelse
            </x-table>
        </x-card>
    @endif
</x-index-page>
@endsection
