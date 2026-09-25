{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Schrittbibliothek (MVP-896): Schritte einmal pflegen, im Designer als Kopie einfügen. --}}

@extends('layouts.app')

@section('title', __('procedure.library.title'))
@section('nav-title', __('procedure.library.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('procedure.library.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('procedures.library.create')" show-label>{{ __('procedure.library.add') }}</x-icon-btn>
        </x-slot:actions>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procedure.library.code') }}</th>
                    <th>{{ __('procedure.library.label') }}</th>
                    <th>{{ __('procedure.library.type') }}</th>
                    <th>{{ __('procedure.library.required') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($steps as $step)
                <tr class="hover">
                    <td class="font-mono text-sm">{{ $step->code }}</td>
                    <td class="font-medium">{{ $step->label }}</td>
                    <td>{{ $step->step_kind->label() }}</td>
                    <td><x-status-badge :tone="$step->is_required ? 'info' : 'ghost'" size="sm">{{ $step->is_required ? __('Ja') : __('Nein') }}</x-status-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('procedures.library.edit', $step)" :label="__('procedure.library.edit')" />
                            <x-action-form :action="route('procedures.library.destroy', $step)" method="DELETE"
                                           :confirm="__('procedure.library.confirm_delete', ['label' => $step->label])" confirm-icon="delete" confirm-tone="error">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('procedure.library.delete')" />
                            </x-action-form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('procedure.library.empty_title')" :message="__('procedure.library.empty')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$steps" standing />
    </x-index-page>
@endsection
