{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _terminal_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Stempelterminal registrieren (Feature 061) — die Ingest-URL
     mit Gerätetoken erscheint nach dem Anlegen einmalig auf der Seite. --}}
@php
    /** @var \Illuminate\Support\Collection $sites */
@endphp
<x-modal
    :title="__('terminal.action.register')"
    :eyebrow="__('terminal.terminals_heading')"
    icon="point_of_sale"
    tone="primary"
    :action="route('admin.terminals.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('terminal.action.register')">

    <x-form-group :label="__('terminal.field.name')" name="name">
        <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('terminal.field.name_placeholder') }}" class="input input-bordered w-full" required>
    </x-form-group>
    <x-form-group :label="__('terminal.field.site')" name="site">
        <select name="site" class="select select-bordered w-full">
            <option value="">{{ __('terminal.field.no_site') }}</option>
            @foreach ($sites as $site)
                <option value="{{ $site['sqid'] }}">{{ $site['name'] }}</option>
            @endforeach
        </select>
    </x-form-group>
</x-modal>
