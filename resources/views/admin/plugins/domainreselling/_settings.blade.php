{{-- Variablen: $plugin, $setting, $schema (siehe _form_dialog.blade.php) --}}
<div role="note" class="alert alert-info">
    <span class="material-symbols-outlined" aria-hidden="true">key</span>
    <div>
        {{ __('domain.settings.connection_note') }}
        <a href="{{ route('admin.domain-provider.index') }}" class="link">{{ __('domain.settings.connection_note_link') }}</a>
    </div>
</div>

@foreach ($schema as $field)
    @include('admin.plugins._field', ['field' => $field, 'setting' => $setting])
@endforeach
