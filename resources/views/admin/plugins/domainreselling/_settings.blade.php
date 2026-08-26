{{--
  Created on   : Tue Aug 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $plugin, $setting, $schema (siehe _form_dialog.blade.php) --}}
<div role="note" class="alert alert-info">
    <x-icon name="key" />
    <div>
        {{ __('domain.settings.connection_note') }}
        <a href="{{ route('admin.domain-provider.index') }}" class="link">{{ __('domain.settings.connection_note_link') }}</a>
    </div>
</div>

@foreach ($schema as $field)
    @include('admin.plugins._field', ['field' => $field, 'setting' => $setting])
@endforeach
