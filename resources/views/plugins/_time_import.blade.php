{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _time_import.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Importseite einer Zeiterfassung (CSV, API, Rückübertragung). Parameter:
  $routePrefix (…import-csv, …import-api, …export-api), $texts (title,
  subtitle, csv_hint, api_title, api_hint, api_missing, export_title,
  export_hint, export_confirm, export_action, optional webhook_title und
  webhook_hint), optional $webhookUrl, dazu vom Controller $apiConfigured,
  $exportEnabled, $inboxOpenCount.
--}}
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ $texts['title'] }}</x-slot:title>
        <x-slot:subtitle>{{ $texts['subtitle'] }}</x-slot:subtitle>
    </x-page-toolbar>

    <x-validation-errors first />

    <x-card>
        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('CSV hochladen') }}</h2>
        <p class="mb-3 text-sm text-muted">
            {{ $texts['csv_hint'] }}
        </p>
        <form method="POST" action="{{ route($routePrefix . '.import-csv') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
            @csrf
            <input type="file" name="csv" accept=".csv,.txt" class="file-input file-input-bordered file-input-sm" required>
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Importieren') }}</x-icon-btn>
        </form>
    </x-card>

    <x-card>
        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ $texts['api_title'] }}</h2>
        @if ($apiConfigured)
            <p class="mb-3 text-sm text-muted">
                {{ $texts['api_hint'] }}
            </p>
            <form method="POST" action="{{ route($routePrefix . '.import-api') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-date-range :from="old('from')" :to="old('to')" />
                <x-icon-btn icon="cloud_download" tone="primary" size="sm" type="submit" show-label>{{ __('Von API importieren') }}</x-icon-btn>
            </form>
        @else
            <div role="alert" class="alert alert-warning text-sm">{{ $texts['api_missing'] }}</div>
        @endif
    </x-card>

    @if ($apiConfigured && $exportEnabled)
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ $texts['export_title'] }}</h2>
            <p class="mb-3 text-sm text-muted">
                {{ $texts['export_hint'] }}
            </p>
            <form method="POST" action="{{ route($routePrefix . '.export-api') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <x-date-range :from="old('from')" :to="old('to')" />
                <x-icon-btn icon="cloud_upload" tone="primary" size="sm" type="submit" show-label
                            data-confirm-dialog
                            data-confirm-message="{{ $texts['export_confirm'] }}">{{ $texts['export_action'] }}</x-icon-btn>
            </form>
        </x-card>
    @endif

    @isset($webhookUrl)
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ $texts['webhook_title'] }}</h2>
            <p class="mb-2 text-sm text-muted">{{ $texts['webhook_hint'] }}</p>
            <code class="select-all break-all text-sm">{{ $webhookUrl }}</code>
        </x-card>
    @endisset

    <x-card>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Zuordnungs-Inbox') }}</h2>
                <p class="text-sm text-muted">{{ __('Offene, noch nicht zugeordnete Import-Gruppen: :n', ['n' => $inboxOpenCount]) }}</p>
            </div>
            <x-icon-btn icon="inbox" tone="outline" size="sm" :href="route('admin.integration.inbox')" show-label>{{ __('Zur Inbox') }}</x-icon-btn>
        </div>
    </x-card>
</x-page-shell>
