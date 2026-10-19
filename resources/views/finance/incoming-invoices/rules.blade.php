{{--
  Created on   : Sun Oct 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : rules.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Regeln des Rechnungseingangs (Feature 163, MVP-1208/1211): Absenderregeln und Posteingangsregeln. --}}
@extends('layouts.app')
@section('title', __('Regeln des Rechnungseingangs'))
@section('nav-title', __('Regeln des Rechnungseingangs'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" back-route="finance.incoming-invoices.index"
              :subtitle="__('Absenderregeln ordnen künftige Mails eines Absenders einer Partei zu. Posteingangsregeln verwerfen, lehnen ab oder ordnen zu, sobald ein Beleg eingeht; die erste passende Regel gewinnt.')">
    @if ($canEditAutomations)
        <x-slot:actions>
            <x-icon-btn icon="tune" size="sm" :href="route('admin.automations.index')" show-label>{{ __('Automationsregeln bearbeiten') }}</x-icon-btn>
        </x-slot:actions>
    @endif
    <x-table scroll="flex" :pinRows="true" :zebra="true">
        <x-slot:head>
            <tr>
                <th>{{ __('Art') }}</th>
                <th>{{ __('Merkmal') }}</th>
                <th>{{ __('Wirkung') }}</th>
                <th class="text-right">{{ __('Treffer') }}</th>
                <th>{{ __('Angelegt') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($senderRules->map(static fn ($rule) => ['kind' => 'sender', 'rule' => $rule])->concat($rules->map(static fn ($rule) => ['kind' => 'automation', 'rule' => $rule]))->concat($templates->map(static fn ($rule) => ['kind' => 'template', 'rule' => $rule])) as $row)
            @php($rule = $row['rule'])
            <tr>
                <td class="whitespace-nowrap">
                    <x-status-badge tone="ghost">{{ match ($row['kind']) { 'sender' => __('Absenderregel'), 'template' => __('Belegvorlage'), default => __('Posteingangsregel') } }}</x-status-badge>
                    @if ($row['kind'] === 'automation' && ! $rule->is_active)
                        <x-status-badge tone="warning">{{ __('Inaktiv') }}</x-status-badge>
                    @endif
                </td>
                <td class="max-w-96 truncate">
                    @if ($row['kind'] === 'sender')
                        {{ $rule->email }} <span class="text-xs text-muted">· {{ $rule->direction->label() }}</span>
                    @elseif ($row['kind'] === 'template')
                        {{ $rule->name }}@if ($rule->supplier) <span class="text-xs text-muted">· {{ $rule->supplier->name }}</span>@endif
                    @else
                        {{ $describe->describeConditions($rule) }}
                    @endif
                </td>
                <td class="max-w-96 truncate">
                    @if ($row['kind'] === 'template')
                        {{ implode(', ', array_map(\App\Services\Invoicing\Recognition\ZoneFields::label(...), $rule->fieldKeys())) }}
                    @else
                        {{ $row['kind'] === 'sender' ? (($rule->supplier ?? $rule->customer)?->name ?? '—') : $describe->describeAction($rule) }}
                    @endif
                </td>
                <td class="whitespace-nowrap text-right tabular-nums text-sm">{{ match ($row['kind']) { 'sender' => '—', 'template' => $rule->hits, default => $hits[$rule->id] ?? 0 } }}</td>
                <td class="whitespace-nowrap tabular-nums text-sm">{{ $rule->created_at?->fdatetime() }}</td>
                <td class="whitespace-nowrap text-right">
                    @if ($row['kind'] === 'automation')
                        <x-action-form :action="route('finance.incoming-invoices.rules.automation.toggle', $rule)" class="inline">
                            <x-icon-btn :icon="$rule->is_active ? 'pause' : 'play_arrow'" size="xs" type="submit" :label="$rule->is_active ? __('Deaktivieren') : __('Aktivieren')" />
                        </x-action-form>
                    @endif
                    <x-action-form :action="route(match ($row['kind']) { 'sender' => 'finance.incoming-invoices.rules.sender.destroy', 'template' => 'finance.incoming-invoices.rules.template.destroy', default => 'finance.incoming-invoices.rules.automation.destroy' }, $rule)"
                                   method="DELETE" :confirm="__('Regel löschen?')" class="inline">
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
