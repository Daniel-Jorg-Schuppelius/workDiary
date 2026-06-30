@extends('layouts.app')
@section('title', __('Lexoffice-Konflikte'))
@section('nav-title', __('Lexoffice-Konflikte'))

@section('content')
<x-index-page :subtitle="__('Im Modus „Manuelle Prüfung“ landen abweichende Remote-Datensätze hier. Pro Konflikt entscheidest du, ob die lokalen oder die Lexoffice-Werte gewinnen.')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.lexoffice.conflicts.index') }}" class="flex items-center gap-2">
            <select name="status" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="open"            @selected($filters['status'] === 'open')>{{ __('Offen') }}</option>
                <option value="resolved_local"  @selected($filters['status'] === 'resolved_local')>{{ __('Lokal gewählt') }}</option>
                <option value="resolved_remote" @selected($filters['status'] === 'resolved_remote')>{{ __('Remote gewählt') }}</option>
                <option value="dismissed"       @selected($filters['status'] === 'dismissed')>{{ __('Verworfen') }}</option>
                <option value="all"             @selected($filters['status'] === 'all')>{{ __('Alle') }}</option>
            </select>
        </form>
    </x-slot:actions>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">

        @if ($conflicts->isEmpty())
            <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Konflikte im gewählten Status.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Kunde') }}</th>
                            <th>{{ __('Lexoffice-ID') }}</th>
                            <th>{{ __('Abweichende Felder') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Aktionen') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conflicts as $conflict)
                            @php
                                $customer = $conflict->referenceable;
                                $diff = $conflict->diff_fields ?? [];
                            @endphp
                            <tr>
                                <td>
                                    @if ($customer)
                                        <a href="{{ route('customers.show', $customer) }}" class="link">{{ $customer->name }}</a>
                                    @else
                                        <span class="text-base-content/60">{{ __('(gelöscht)') }}</span>
                                    @endif
                                </td>
                                <td class="font-mono text-xs">{{ $conflict->external_id }}</td>
                                <td>
                                    @if ($diff !== [])
                                        @foreach ($diff as $f)
                                            <span class="badge badge-sm">{{ $f }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-sm
                                        @if ($conflict->status === 'open') badge-warning
                                        @elseif ($conflict->status === 'resolved_local') badge-info
                                        @elseif ($conflict->status === 'resolved_remote') badge-success
                                        @else badge-ghost @endif">{{ $conflict->status }}</span>
                                </td>
                                <td class="text-right">
                                    @if ($conflict->isOpen())
                                        <div class="join">
                                            <form method="POST" action="{{ route('admin.lexoffice.conflicts.resolve-local', $conflict) }}" class="join-item inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline">{{ __('Lokal behalten') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.lexoffice.conflicts.resolve-remote', $conflict) }}" class="join-item inline">
                                                @csrf
                                                <button class="btn btn-sm btn-primary">{{ __('Remote übernehmen') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.lexoffice.conflicts.dismiss', $conflict) }}" class="join-item inline">
                                                @csrf
                                                <button class="btn btn-sm btn-ghost">{{ __('Verwerfen') }}</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-base-content/60">
                                            {{ optional($conflict->resolved_at)->format('d.m.Y H:i') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @if ($conflict->isOpen() && $diff !== [])
                                <tr>
                                    <td colspan="5" class="bg-base-200/40 text-xs">
                                        <div class="grid grid-cols-2 gap-3 p-2">
                                            <div>
                                                <div class="font-semibold mb-1">{{ __('Lokal') }}</div>
                                                <pre class="whitespace-pre-wrap">{{ json_encode(array_intersect_key($conflict->local_snapshot, array_flip($diff)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                            <div>
                                                <div class="font-semibold mb-1">{{ __('Lexoffice') }}</div>
                                                <pre class="whitespace-pre-wrap">{{ json_encode($conflict->remote_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination :paginator="$conflicts" standing />
        @endif
    </div>
</x-index-page>
@endsection
