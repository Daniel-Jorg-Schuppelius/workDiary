{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('content')
    <x-page-toolbar :title="__('Auftragsbuch')" :subtitle="__('customer_portal.nav.diary')" class="mb-4" />
    <x-card>
    <x-table bare>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Datum') }}</x-table.th>
                <x-table.th>{{ __('Titel') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($entries as $entry)
            <tr>
                <td class="whitespace-nowrap">{{ optional($entry->start_at)->fdate() }}</td>
                <td><a class="link link-hover" href="{{ route('customer.diary.show', $entry) }}">{{ $entry->title }}</a></td>
                <td>@if ($entry->status)<x-status-badge size="sm" outline>{{ $entry->status->label() }}</x-status-badge>@endif</td>
            </tr>
        @empty
            <x-table.empty :colspan="3" icon="menu_book" :title="__('Keine Einträge vorhanden.')" compact />
        @endforelse
    </x-table>
    </x-card>
    <x-pagination :paginator="$entries" standing />
@endsection
