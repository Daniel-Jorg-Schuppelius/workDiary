@extends('layouts.app')
@section('title', __('Zuordnungen'))
@section('nav-title', __('Zuordnungen'))

@section('content')
<x-index-page :subtitle="__('Alle gespeicherten Verknüpfungen zwischen lokalen Datensätzen und ihren Fremd-IDs in den angebundenen Systemen. Eine gelöste Verknüpfung führt beim nächsten Import zu einer erneuten Zuordnung über die Inbox.')">
    <x-slot:actions>
        <a href="{{ route('admin.integration.inbox') }}" class="btn btn-sm btn-outline">{{ __('Zur Inbox') }}</a>
        <form method="GET" action="{{ route('admin.integration.mappings.index') }}" class="flex items-center gap-2">
            <select name="plugin" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($filters['plugin'] === 'all')>{{ __('Alle Quellen') }}</option>
                @foreach ($plugins as $p)
                    <option value="{{ $p }}" @selected($filters['plugin'] === $p)>{{ $p }}</option>
                @endforeach
            </select>
            <select name="type" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($filters['type'] === 'all')>{{ __('Alle Typen') }}</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected($filters['type'] === $t)>{{ \App\Support\Trans::or('integration.external_type.' . $t, $t) }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if ($references->isEmpty())
            {{-- Prerequisite-Audit (MVP-181): erklären, WIE Zuordnungen
                 entstehen, statt nur den leeren Zustand zu zeigen. --}}
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">link</span>'
                           :title="__('Keine Zuordnungen im gewählten Filter.')"
                           :message="__('prerequisites.mappings.hint')">
                <x-slot:action>
                    <x-button :href="route('admin.integration.inbox')" tone="primary" size="sm" icon="arrow_forward">
                        {{ __('prerequisites.mappings.cta') }}
                    </x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Quelle') }}</th>
                            <th>{{ __('Typ') }}</th>
                            <th>{{ __('Fremd-ID') }}</th>
                            <th>{{ __('Lokaler Datensatz') }}</th>
                            <th>{{ __('Zuletzt synchronisiert') }}</th>
                            <th class="text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($references as $ref)
                            @php $target = $ref->referenceable; @endphp
                            <tr>
                                <td><span class="badge badge-sm badge-outline">{{ $ref->plugin_id }}</span></td>
                                <td class="text-xs">{{ \App\Support\Trans::or('integration.external_type.' . $ref->external_type, $ref->external_type) }}</td>
                                <td class="font-mono text-xs">{{ $ref->external_id }}</td>
                                <td>
                                    @if ($target)
                                        {{ $registry->label($ref->referenceable_type) }}:
                                        <span class="font-medium">{{ $target->name ?? ('#' . $target->getKey()) }}</span>
                                    @else
                                        <span class="text-error">{{ __('(verwaist)') }}</span>
                                    @endif
                                </td>
                                <td class="text-xs text-base-content/60">{{ optional($ref->synced_at)->format('d.m.Y H:i') }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.integration.mappings.destroy', $ref) }}"
                                          data-confirm-dialog data-confirm-message="{{ __('Diese Verknüpfung wirklich lösen?') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-ghost text-error">{{ __('Lösen') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination :paginator="$references" standing />
        @endif
    </div>
</x-index-page>
@endsection
