{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portal-Objektakte (Feature 027, Rang 50): eigene Objekte des Kunden.
--}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Objekte') }}</h1>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Name') }}</x-table.th>
                <x-table.th>{{ __('Seriennummer') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($assets as $asset)
            <tr>
                <td><a class="link link-hover" href="{{ route('customer.assets.show', $asset) }}">{{ $asset->name }}</a></td>
                <td class="font-mono text-sm">{{ $asset->serial_number ?? '—' }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="2" :title="__('Keine Objekte vorhanden.')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$assets" />
@endsection
