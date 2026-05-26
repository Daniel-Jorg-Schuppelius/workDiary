@extends('layouts.app')

@section('title', __('Korrekturen-Inbox'))
@section('nav-title', __('Korrekturen-Inbox'))

@section('content')
    <x-index-page :subtitle="__('Offene und entschiedene Korrekturanträge der Organisation.')">
        <x-filter-bar :action="route('admin.corrections.index')" :reset="route('admin.corrections.index')">
            <select name="status" class="select select-sm select-bordered w-40 shrink-0">
                <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>
                        {{ $s->label() }}
                    </option>
                @endforeach
            </select>
        </x-filter-bar>

        @if ($requests->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">inbox</span>'
                :title="__('Keine Korrekturanträge im Filter')"
                :message="__('Setzen Sie den Statusfilter auf „Alle Status", um auch entschiedene Anträge zu sehen.')" />
        @else
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Bezug') }}</th>
                        <th>{{ __('Mitarbeiter:in') }}</th>
                        <th>{{ __('Antragsteller:in') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Items') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($requests as $r)
                    <tr>
                        <td class="font-medium">{{ optional($r->scope_date)->format('d.m.Y') }}</td>
                        <td>{{ $r->user?->name }}</td>
                        <td>{{ $r->requestedBy?->name }}</td>
                        <td>
                            <span class="badge badge-{{ $r->status->tone() }} badge-sm">{{ $r->status->label() }}</span>
                        </td>
                        <td class="text-right tabular-nums">{{ $r->items->count() }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="arrow_forward" size="sm" tone="ghost"
                                        :href="route('admin.corrections.show', $r)"
                                        :aria-label="__('Öffnen')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <div class="mt-3">{{ $requests->links() }}</div>
        @endif
    </x-index-page>
@endsection
