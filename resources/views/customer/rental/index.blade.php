{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('title', __('Meine Leihgeräte'))

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ __('Meine Leihgeräte') }}</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('Nummer') }}</th>
                <th>{{ __('Geräte') }}</th>
                <th>{{ __('Zeitraum') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <tr class="hover">
                <td><a class="link font-mono" href="{{ route('customer.rentals.show', $case) }}">{{ $case->number }}</a></td>
                <td>{{ $case->caseAssets->map(fn($ca) => $ca->asset?->name)->filter()->implode(', ') ?: '—' }}</td>
                <td>{{ $case->starts_at->fdate() }} – {{ $case->ends_at->fdate() }}</td>
                <td><span class="badge badge-outline">{{ $case->status->label() }}</span></td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Keine Verleihvorgänge vorhanden.')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$cases" standing />
</div>
@endsection
