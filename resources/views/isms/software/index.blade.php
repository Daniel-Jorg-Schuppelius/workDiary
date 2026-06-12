{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Organisationsbezogenes Softwareinventar (Feature 044, MVP 1, Ebene 1):
  Filter, Tabelle mit Aufklapp-Detail (Installationen inkl. Zeilen-CRUD,
  analog Risiken), Modal-CRUD und EOL-Warn-Badges.
--}}

@extends('layouts.app')

@section('title', __('isms.title.software'))
@section('nav-title', __('isms.title.software'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.software')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.software.create')"
                            show-label>{{ __('isms.action.create_software') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        @if ($eolReachedCount > 0)
            <div class="alert alert-error bg-error/10 border-error/30 text-sm" role="note">
                <x-icon name="event_busy" />
                <span>{{ trans_choice('isms.software.eol_reached_notice', $eolReachedCount, ['count' => $eolReachedCount]) }}</span>
            </div>
        @endif

        <x-filter-bar :action="route('isms.software.index')"
                      :reset="$hasActiveFilters ? route('isms.software.index') : null">
            <x-filter-field :label="__('isms.field.category')" for="isms-sw-category" class="min-w-40">
                <select id="isms-sw-category" name="category" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\SoftwareCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected($filters['category'] === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.field.support_status')" for="isms-sw-status" class="min-w-40">
                <select id="isms-sw-status" name="support_status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('isms.filter.all') }}</option>
                    @foreach (\App\Enums\Isms\SupportStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['support_status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('isms.filter.search')" for="isms-sw-q" class="min-w-48">
                <input id="isms-sw-q" type="search" name="q" value="{{ $filters['q'] }}"
                       class="input input-sm input-bordered w-full"
                       placeholder="{{ __('isms.filter.search_software') }}">
            </x-filter-field>
        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.name') }}</th>
                    <th>{{ __('isms.field.vendor') }}</th>
                    <th>{{ __('isms.field.product_version') }}</th>
                    <th>{{ __('isms.field.category') }}</th>
                    <th>{{ __('isms.field.support_status') }}</th>
                    <th>{{ __('isms.field.eol_on') }}</th>
                    <th class="text-center">{{ __('isms.field.installations') }}</th>
                    <th>{{ __('isms.field.owner') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($products as $product)
                <tr class="hover" id="isms-software-{{ $product->id }}">
                    <td>
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $product->name }}</summary>
                            <div class="mt-2 space-y-1 text-xs text-base-content/70">
                                @if ($product->notes)
                                    <p>{{ $product->notes }}</p>
                                @endif
                                <p class="font-semibold">{{ __('isms.field.installations') }}:</p>
                                @forelse ($product->installations as $installation)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-status-badge tone="ghost" outline>{{ $installation->installed_version ?? '—' }}</x-status-badge>
                                        <span>{{ $installation->asset_ref ?? '—' }}</span>
                                        @if ($installation->location)
                                            <span class="text-base-content/50">({{ $installation->location }})</span>
                                        @endif
                                        @can('update', $installation)
                                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                                        data-entry-modal-trigger
                                                        :href="route('isms.software.installations.edit', $installation)"
                                                        :label="__('isms.action.edit')" />
                                        @endcan
                                        @can('delete', $installation)
                                            <form method="POST" action="{{ route('isms.software.installations.destroy', $installation) }}"
                                                  data-confirm-dialog
                                                  data-confirm-title="{{ __('isms.action.delete') }}"
                                                  data-confirm-message="{{ __('isms.confirm_delete_installation') }}"
                                                  data-confirm-icon="delete"
                                                  data-confirm-tone="error"
                                                  data-confirm-label="{{ __('isms.action.delete') }}">
                                                @csrf @method('DELETE')
                                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                            :label="__('isms.action.delete')" />
                                            </form>
                                        @endcan
                                    </div>
                                @empty
                                    <p>{{ __('isms.empty_installations') }}</p>
                                @endforelse
                                @if ($canManage)
                                    <x-icon-btn icon="add" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('isms.software.installations.create', $product)"
                                                show-label>{{ __('isms.action.create_installation') }}</x-icon-btn>
                                @endif
                            </div>
                        </details>
                    </td>
                    <td class="text-base-content/70">{{ $product->vendor ?? '—' }}</td>
                    <td class="font-mono text-sm">{{ $product->product_version ?? '—' }}</td>
                    <td>
                        @if ($product->category !== null)
                            <x-status-badge tone="ghost" outline>{{ $product->category->label() }}</x-status-badge>
                        @else
                            —
                        @endif
                    </td>
                    <td><x-status-badge :tone="$product->support_status->tone()">{{ $product->support_status->label() }}</x-status-badge></td>
                    <td>
                        @if ($product->eol_on !== null)
                            <span class="{{ $product->eolReached() ? 'text-error font-semibold' : ($product->eolSoon() ? 'text-warning font-semibold' : 'text-base-content/70') }}">
                                {{ $product->eol_on->format('d.m.Y') }}
                            </span>
                            @if ($product->eolReached())
                                <x-status-badge tone="error" outline>{{ __('isms.software.eol_reached') }}</x-status-badge>
                            @elseif ($product->eolSoon())
                                <x-status-badge tone="warning" outline>{{ __('isms.software.eol_soon') }}</x-status-badge>
                            @endif
                        @else
                            <span class="text-base-content/50">—</span>
                        @endif
                    </td>
                    <td class="text-center text-base-content/70">{{ $product->installations_count }}</td>
                    <td class="text-base-content/70">{{ optional($product->owner)->name ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('update', $product)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.software.edit', $product)"
                                            :label="__('isms.action.edit')" />
                            @endcan
                            @can('delete', $product)
                                <form method="POST" action="{{ route('isms.software.destroy', $product) }}"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('isms.action.delete') }}"
                                      data-confirm-message="{{ __('isms.confirm_delete_software') }}"
                                      data-confirm-icon="delete"
                                      data-confirm-tone="error"
                                      data-confirm-label="{{ __('isms.action.delete') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                :label="__('isms.action.delete')" />
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9"
                               :title="__('isms.empty_software_title')"
                               :message="$hasActiveFilters ? __('isms.empty_filtered') : __('isms.empty_software')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$products" />
    </x-index-page>
@endsection
