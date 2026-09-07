{{--
  Created on   : Mon Sep 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : products.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Produkt-Einstufung (Feature 152): welche Lexoffice-Artikel Abo-Produkte
  sind. Erkannt über den Namen, je Artikel übersteuerbar — „nie Abo-Position"
  hält Dienstleistungen mit Microsoft im Namen aus Vorschlägen und Listen.
--}}
@extends('layouts.app')
@section('title', __('resale.products.title'))
@section('nav-title', __('resale.title.menu'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
@endphp

@section('content')
    <x-index-page overflow="clip" :title="__('resale.products.title')" :subtitle="__('resale.products.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>
        <p class="text-xs text-muted mb-2">{{ __('resale.products.hint') }}</p>
        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('resale.field.article') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.products.number') }}</x-table.th>
                    <x-table.th>{{ __('resale.products.unit') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.products.price') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.products.subscriptions') }}</x-table.th>
                    <x-table.th>{{ __('resale.products.detected') }}</x-table.th>
                    <x-table.th>{{ __('resale.products.override') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                @php $article = $row['article']; @endphp
                <tr @class(['hover', 'opacity-70' => ! $row['effective']])>
                    <td class="font-medium">{{ $article->name }}</td>
                    <td class="font-mono text-xs">{{ $article->article_number ?? '—' }}</td>
                    <td class="text-sm">{{ $article->unit_name ?? '—' }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ $article->net_unit_price?->withScale(2)->format() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $row['subscriptions'] }}</td>
                    <td><x-status-badge size="xs" :tone="$row['detected']->tone()" :label="$row['detected']->label()" /></td>
                    <td>
                        @if ($canManage)
                            <form method="POST" action="{{ route('finance.resale.products.store') }}" class="flex items-center gap-1">
                                @csrf
                                <input type="hidden" name="article_id" value="{{ \App\Support\Sqid::encode(\App\Models\LexofficeArticle::class, $article->id) }}">
                                <select name="role" class="select select-xs select-bordered w-44" aria-label="{{ __('resale.products.override') }}">
                                    <option value="auto" @selected($article->resale_role === null)>{{ __('resale.products.role.auto') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($article->resale_role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <x-icon-btn icon="save" size="xs" tone="ghost" type="submit" :title="__('resale.products.save')" />
                            </form>
                        @else
                            {{ $article->resale_role?->label() ?? __('resale.products.role.auto') }}
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="inventory_2" :title="__('resale.products.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
