{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vertragsvorlagen (MVP-893): entstehen am Vertrag („Als Vorlage speichern"). --}}

@extends('layouts.app')

@section('title', __('contract.template.title'))
@section('nav-title', __('contract.template.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('contract.template.subtitle')">
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('contract.template.name') }}</th>
                    <th>{{ __('Vertragsart') }}</th>
                    <th>{{ __('contract.template.obligations') }}</th>
                    <th>{{ __('contract.template.active') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                <tr class="hover">
                    <td class="font-medium">{{ $template->name }}</td>
                    <td>{{ $template->kind->label() }}</td>
                    <td class="tabular-nums">{{ count($template->obligations ?? []) }}</td>
                    <td><x-status-badge :tone="$template->is_active ? 'success' : 'ghost'" size="sm">{{ $template->is_active ? __('Ja') : __('Nein') }}</x-status-badge></td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit" tone="outline" size="xs" data-entry-modal-trigger :href="route('contracts.templates.edit', $template)" :label="__('contract.template.edit')" />
                            <x-action-form :action="route('contracts.templates.destroy', $template)" method="DELETE"
                                           :confirm="__('contract.template.confirm_delete', ['name' => $template->name])" confirm-icon="delete" confirm-tone="error">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('contract.template.delete')" />
                            </x-action-form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('contract.template.empty_title')" :message="__('contract.template.empty')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$templates" standing />
    </x-index-page>
@endsection
