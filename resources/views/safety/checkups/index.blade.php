{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorsorge-Register (ArbMedVV, Feature 132): Person/Art/Anlass/Datum/
  Fälligkeit/Bescheinigung — keine Gesundheitsdaten.
--}}
@extends('layouts.app')
@section('title', __('safety.register.title.checkups'))
@section('nav-title', __('safety.register.title.checkups'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('safety.register.subtitle.checkups')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('safety.checkups.create')"
                        show-label>{{ __('safety.register.action.create_checkup') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('safety.checkups.index')" :reset="route('safety.checkups.index')">
        <x-filter-field :label="__('safety.register.field.kind')" for="flt-kind">
            <select id="flt-kind" name="kind" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('safety.register.filter.all') }}</option>
                @foreach (\App\Enums\Safety\MedicalCheckupKind::cases() as $k)
                    <option value="{{ $k->value }}" @selected($kind === $k->value)>{{ $k->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('safety.register.kpi.checkup_due')" for="flt-due-count">
            <span id="flt-due-count" class="badge {{ $dueCount > 0 ? 'badge-error' : 'badge-ghost' }} badge-sm">{{ $dueCount }}</span>
        </x-filter-field>
        <x-filter-field :label="__('safety.register.filter.due_only')" for="flt-due" class="order-40">
            <input id="flt-due" type="checkbox" name="due" value="1" class="toggle toggle-sm" data-autosubmit @checked($onlyDue)>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('safety.register.field.user') }}</th>
                <th>{{ __('safety.register.field.kind') }}</th>
                <th>{{ __('safety.register.field.occasion') }}</th>
                <th>{{ __('safety.register.field.performed_on') }}</th>
                <th>{{ __('safety.register.field.next_due_on') }}</th>
                <th>{{ __('safety.register.field.certificate_on_file') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($checkups as $checkup)
            <tr class="hover">
                <td class="font-medium">{{ $checkup->user?->name ?? '–' }}</td>
                <td><x-status-badge :tone="$checkup->kind->tone()" size="sm" outline>{{ $checkup->kind->label() }}</x-status-badge></td>
                <td class="text-sm text-base-content/70">{{ $checkup->occasion ?? '–' }}</td>
                <td class="text-sm">{{ $checkup->performed_on->format('d.m.Y') }}</td>
                <td class="text-sm {{ $checkup->isDueOverdue() ? 'text-error font-semibold' : 'text-base-content/70' }}">{{ $checkup->next_due_on?->format('d.m.Y') ?? '–' }}</td>
                <td>
                    <x-status-badge :tone="$checkup->certificate_on_file ? 'success' : 'warning'" size="sm">
                        {{ $checkup->certificate_on_file ? __('Ja') : __('Nein') }}
                    </x-status-badge>
                </td>
                <td class="text-right">
                    @if ($canManage)
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('safety.checkups.edit', $checkup)"
                                        :label="__('safety.register.action.edit')" />
                            <x-action-form :action="route('safety.checkups.destroy', $checkup)" method="DELETE"
                                           :confirm="__('safety.register.confirm.delete_checkup')"
                                           confirm-icon="delete" confirm-tone="error"
                                           :confirm-label="__('safety.register.action.delete')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('safety.register.action.delete')" />
                            </x-action-form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="medical_services" :colspan="7" :title="__('safety.register.empty.checkups')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$checkups" standing />
</x-index-page>
@endsection
