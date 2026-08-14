{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

{{-- Bestellseite (Feature 065, MVP-154): 032-Formular aus der Vorlage des
     Katalogeintrags; Upload-/Signatur-Felder werden im Portal ausgelassen. --}}

@section('content')
    <div class="mb-4">
        <a class="link link-hover text-sm" href="{{ route('customer.catalog.index') }}">← {{ __('Zurück zum Servicekatalog') }}</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $item->name }}</h1>
        @if ($item->description)
            <p class="mt-1 text-base-content/70">{{ $item->description }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('customer.catalog.order', $item) }}"
          class="rounded-box border border-base-300 bg-base-100 p-4 space-y-3">
        @csrf

        @forelse ($fields as $field)
            @php
                $key = (string) $field['key'];
                $name = "values[{$key}]";
                $errKey = "values.{$key}";
                $required = (bool) ($field['required'] ?? false);
                $old = old("values.{$key}");
            @endphp
            @switch($field['type'] ?? '')
                @case(\App\Enums\Form\FormFieldType::Textarea->value)
                    <label class="form-control">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <textarea name="{{ $name }}" rows="3" maxlength="10000" @required($required)
                                  class="textarea textarea-bordered w-full @error($errKey) textarea-error @enderror">{{ $old }}</textarea>
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Number->value)
                    <label class="form-control">
                        <span class="label-text">
                            {{ $field['label'] }}@if(filled($field['unit'] ?? null)) ({{ $field['unit'] }})@endif @if($required)*@endif
                        </span>
                        <input type="number" step="any" name="{{ $name }}" @required($required)
                               class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ $old }}">
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Date->value)
                    <label class="form-control">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <input type="date" name="{{ $name }}" @required($required)
                               class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ $old }}">
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Select->value)
                    <label class="form-control">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <select name="{{ $name }}" @required($required)
                                class="select select-bordered w-full @error($errKey) select-error @enderror">
                            <option value="">—</option>
                            @foreach ((array) ($field['options'] ?? []) as $option)
                                <option value="{{ $option }}" @selected($old === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    @break
                @case(\App\Enums\Form\FormFieldType::Checkbox->value)
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" class="checkbox"
                               @checked((bool) $old) @required($required)>
                        <span>{{ $field['label'] }} @if($required)*@endif</span>
                    </label>
                    @break
                @default
                    <label class="form-control">
                        <span class="label-text">{{ $field['label'] }} @if($required)*@endif</span>
                        <input type="text" name="{{ $name }}" maxlength="500" @required($required)
                               class="input input-bordered w-full @error($errKey) input-error @enderror" value="{{ $old }}">
                    </label>
            @endswitch
            @if (filled($field['help'] ?? null))
                <p class="-mt-2 text-xs text-base-content/60">{{ $field['help'] }}</p>
            @endif
            @error($errKey)
                <p class="-mt-2 text-error text-sm">{{ $message }}</p>
            @enderror
        @empty
            <p class="text-sm text-base-content/60">{{ __('Für diese Leistung sind keine weiteren Angaben nötig.') }}</p>
        @endforelse

        <button type="submit" class="btn btn-primary">{{ __('Bestellung absenden') }}</button>
    </form>
@endsection
