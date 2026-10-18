{{--
  Created on   : Sun Oct 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : rules.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Regeln des Rechnungseingangs (Feature 163, MVP-1208): Absenderregeln und Ausschlüsse. --}}
@extends('layouts.app')
@section('title', __('Regeln des Rechnungseingangs'))
@section('nav-title', __('Regeln des Rechnungseingangs'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" back-route="finance.incoming-invoices.index"
              :subtitle="__('Absenderregeln ordnen künftige Mails eines Absenders einer Partei zu. Ausschlüsse legen Eingänge einer Gegenpartei automatisch als keine Rechnung ab.')">
    <x-table scroll="flex" :pinRows="true" :zebra="true">
        <x-slot:head>
            <tr>
                <th>{{ __('Art') }}</th>
                <th>{{ __('Merkmal') }}</th>
                <th>{{ __('Richtung') }}</th>
                <th>{{ __('Wirkung') }}</th>
                <th>{{ __('Angelegt') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($senderRules->map(static fn ($rule) => ['kind' => 'sender', 'rule' => $rule])->concat($exclusions->map(static fn ($rule) => ['kind' => 'exclusion', 'rule' => $rule])) as $row)
            @php($rule = $row['rule'])
            <tr>
                <td class="whitespace-nowrap"><x-status-badge tone="ghost">{{ $row['kind'] === 'sender' ? __('Absenderregel') : __('Ausschluss') }}</x-status-badge></td>
                <td class="max-w-80 truncate">{{ $row['kind'] === 'sender' ? $rule->email : $rule->party_name }}</td>
                <td class="whitespace-nowrap">{{ $rule->direction->label() }}</td>
                <td class="max-w-96 truncate">
                    @if ($row['kind'] === 'sender')
                        {{ ($rule->supplier ?? $rule->customer)?->name ?? '—' }}
                    @else
                        {{ __('Keine Rechnung') }}@if ($rule->note) · <span class="text-muted">{{ $rule->note }}</span>@endif
                    @endif
                </td>
                <td class="whitespace-nowrap tabular-nums text-sm">{{ $rule->created_at?->fdatetime() }}</td>
                <td class="whitespace-nowrap text-right">
                    <x-action-form :action="route($row['kind'] === 'sender' ? 'finance.incoming-invoices.rules.sender.destroy' : 'finance.incoming-invoices.rules.exclusion.destroy', $rule)"
                                   method="DELETE" :confirm="__('Regel löschen?')">
                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('Regel löschen')" />
                    </x-action-form>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" icon="rule" :title="__('Keine Regeln.')" />
        @endforelse
    </x-table>
</x-index-page>
@endsection
