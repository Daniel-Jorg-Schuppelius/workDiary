{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Dienstleister'))
@section('nav-title', __('Dienstleister & Vertragspartner'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
    <x-index-page overflow="clip" :subtitle="__('Dienstleister und Vertragspartner mit ihren Auftragsverarbeitungsverträgen verwalten.')">
        <x-slot:actions>
            <x-icon-btn icon="handshake" tone="ghost" size="sm"
                        :href="route('dataprotection.agreements.index')"
                        show-label>{{ __('AVV-Register') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('dataprotection.processors.create')"
                        show-label>{{ __('Neuer Dienstleister') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Name') }}</x-table.th>
                    <x-table.th>{{ __('Rolle') }}</x-table.th>
                    <x-table.th>{{ __('Ort') }}</x-table.th>
                    <x-table.th>{{ __('Drittland') }}</x-table.th>
                    <x-table.th>{{ __('AVV') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($processors as $p)
                <tr class="hover">
                    <td><a class="link" href="{{ route('dataprotection.processors.show', $p) }}">{{ $p->name }}</a></td>
                    <td>{{ $p->role->label() }}</td>
                    <td>{{ $p->location ?? '—' }}</td>
                    <td>{{ $p->third_country ? __('ja') : '—' }}</td>
                    <td>{{ $p->agreements_count }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Keine Dienstleister erfasst.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$processors" standing />
    </x-index-page>
@endsection
