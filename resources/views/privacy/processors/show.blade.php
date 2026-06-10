@extends('layouts.app')
@section('title', $processor->name)
@section('nav-title', $processor->name)
@section('content')
    <x-index-page :subtitle="__('Stammdaten des Dienstleisters und zugeordnete Auftragsverarbeitungsverträge.')">
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Stammdaten') }}
                <x-status-badge tone="ghost" size="sm" class="ml-2">{{ $processor->role->label() }}</x-status-badge>
            </h2>
            <div class="text-sm space-y-1 mt-2">
                <p><span class="font-semibold">{{ __('Kontakt') }}:</span> {{ $processor->contact ?? '—' }}</p>
                <p><span class="font-semibold">{{ __('Verarbeitungsort') }}:</span> {{ $processor->location ?? '—' }} {{ $processor->third_country ? '('.__('Drittland').')' : '' }}</p>
                @if ($processor->notes)<p class="whitespace-pre-line">{{ $processor->notes }}</p>@endif
            </div>
        </x-card>

        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Auftragsverarbeitungsverträge') }}</h2>
            <ul class="space-y-1 mt-2">
                @forelse ($processor->agreements as $a)
                    <li class="flex items-center justify-between rounded-box border border-base-300 px-3 py-2">
                        <a class="link" href="{{ route('dataprotection.agreements.show', $a) }}">{{ $a->title }} (v{{ $a->version }})</a>
                        <x-status-badge tone="ghost" size="sm">{{ $a->status->label() }}</x-status-badge>
                    </li>
                @empty
                    <li class="text-sm text-base-content/60">{{ __('Noch kein AVV.') }}</li>
                @endforelse
            </ul>
        </x-card>

        @can('create', \App\Models\Privacy\ProcessingAgreement::class)
            <div>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            onclick="document.getElementById('dlg-agreement').showModal()" show-label>{{ __('Neuen AVV anlegen') }}</x-icon-btn>
            </div>
            <x-modal :embedded="false" id="dlg-agreement" :title="__('Neuen AVV anlegen')"
                     icon="handshake" tone="primary"
                     :action="route('dataprotection.agreements.store')" method="POST"
                     enctype="multipart/form-data" :submit-label="__('AVV anlegen')">
                <input type="hidden" name="processor_id" value="{{ $processor->id }}">
                <x-form-group :legend="__('Auftragsverarbeitungsvertrag')" icon="handshake" tone="primary" cols="2">
                    <x-input-field name="title" :label="__('Titel')" required />
                    <x-input-field name="version" :label="__('Version')" value="1.0" />
                    <x-input-field type="date" name="valid_from" :label="__('Gültig ab')" />
                    <x-input-field type="date" name="valid_until" :label="__('Gültig bis')" />
                    <x-input-field name="data_categories" :label="__('Betroffene Datenkategorien')" span="2">
                        <textarea id="data_categories" name="data_categories" rows="2" class="textarea textarea-bordered w-full"></textarea>
                    </x-input-field>
                    <x-input-field type="file" name="document" :label="__('Dokument')" span="2" accept=".pdf,.doc,.docx" />
                </x-form-group>
            </x-modal>
        @endcan
    </x-index-page>
@endsection
