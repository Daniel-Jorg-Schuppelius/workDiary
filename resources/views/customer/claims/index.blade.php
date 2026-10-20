{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('title', __('Meine Reklamationen'))

@section('content')
<div class="space-y-4">
    <x-page-toolbar :title="__('Meine Reklamationen')" :subtitle="__('customer_portal.nav.claims')" />

    <x-card>
    <x-table bare>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('Titel') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
                <x-table.th>{{ __('Gemeldet am') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <tr class="hover">
                <td><a class="link font-mono" href="{{ route('customer.claims.show', $case) }}">{{ $case->number }}</a></td>
                <td>{{ $case->title }}</td>
                <td><x-status-badge tone="plain" size="md" outline>{{ $case->status->label() }}</x-status-badge></td>
                <td>{{ $case->reported_at->fdate() }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" icon="report_problem" :title="__('Keine Reklamationen vorhanden.')" compact />
        @endforelse
    </x-table>
    </x-card>
    <x-pagination :paginator="$cases" standing />
</div>
@endsection
