{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entry-type-protocols.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Drilldown: Defektprotokolle (Auftragstyp)'))
@section('nav-title', __('Drilldown: Defektprotokolle (Auftragstyp)'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Defektprotokolle') }}</x-slot:title>
        <x-slot:subtitle>
            {{ __('Auftragstyp') }}: {{ $entryType?->label ?? ('#' . $entryTypeId) }} · {{ $label }}
        </x-slot:subtitle>
        <x-slot:actions>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.entry-types.drilldown.protocols', array_filter(['entry_type_id' => $entryTypeId, 'customer_id' => $customerId, 'user_id' => $userId, 'status' => $statusFilter, 'export' => 'csv']))"
                        show-label>CSV</x-icon-btn>
            <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                        :href="route('reports.entry-types.drilldown.protocols', array_filter(['entry_type_id' => $entryTypeId, 'customer_id' => $customerId, 'user_id' => $userId, 'status' => $statusFilter, 'export' => 'pdf']))"
                        show-label>PDF</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="outline" size="sm"
                        :href="route('reports.entry-types', array_filter(['customer_id' => $customerId, 'user_id' => $userId, 'entry_type_id' => $entryTypeId, 'status' => $statusFilter]))"
                        show-label>{{ __('Zur Auftragstypanalyse') }}</x-icon-btn>
        </x-slot:actions>
    </x-page-toolbar>

    <x-card>
        @if ($protocols->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">fact_check</span>' :title="__('Keine Defektprotokolle für diesen Drilldown gefunden.')" />
        @else
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Titel') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <x-table.th sort type="date">{{ __('Zeitpunkt') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Erstellt von') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('Auftrag') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($protocols as $protocol)
                    <tr>
                        <td class="font-medium">{{ $protocol->title }}</td>
                        <td><x-status-badge tone="ghost" outline>{{ $protocol->status->label() }}</x-status-badge></td>
                        <td>{{ $protocol->occurred_at?->orgTz()->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $protocol->creator?->name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('diary.show', $protocol->subject_id) }}" class="link link-hover">#{{ $protocol->subject_id }}</a>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            @if ($protocols->hasPages())
                <div class="mt-4">{{ $protocols->links('pagination::simple-tailwind') }}</div>
            @endif
        @endif
    </x-card>
</x-page-shell>
@endsection
