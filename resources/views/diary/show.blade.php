{{--
  Created on   : Wed Apr 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', \CommonToolkit\Helper\Data\StringHelper::truncate($diary->content, 60) . ' — WorkDiary')
@section('nav-title', __('Eintrag') . ' #' . $diary->id)

@section('content')
    <x-page-shell>
        <div class="flex items-center justify-end gap-2">
            <x-icon-btn icon="folder_open" tone="outline" size="sm" show-label
                        :href="route('diary.case-file', $diary)">{{ __('timeline.action.case_file') }}</x-icon-btn>
            <x-help-button topic="diary-entries.edit" :label="__('Hilfe zu Auftrag')" />
            <x-help-button topic="protocols.create" :label="__('Hilfe zu Protokoll')" />
            <x-help-button topic="procedures.run" :label="__('Hilfe zu Prozedur')" />
        </div>
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
            <div class="h-full overflow-auto p-6 md:p-8 space-y-6">
                @include('diary._show_body', ['isDialog' => false])
                @include('diary._dispatch_panel')
                @include('external-participants._panel', ['subject' => $diary, 'externalType' => 'diary'])
                @includeWhen(isset($procedureRuns), 'diary._procedure_panel')
                @include('diary._timeline_panel')
                @include('diary._service_tickets_panel')
                <div class="text-center">
                    <x-icon-btn icon="arrow_back" size="sm" :href="route('diary.index')" show-label>{{ __('Zurück zur Liste') }}</x-icon-btn>
                </div>
            </div>
        </div>
    </x-page-shell>
@endsection
