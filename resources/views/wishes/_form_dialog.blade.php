{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wunsch äußern oder ändern (Feature 206, MVP-1658). Variablen: $wish (AppWish|null), $routeName (Seite, von der der Dialog kam), $remaining (heute noch möglich, null beim Ändern). --}}
@php
    $isEdit = $wish !== null;
    $action = $isEdit ? route('wishes.update', $wish) : route('wishes.store');
    $dialogUrl = $isEdit ? route('wishes.edit', $wish) . '?dialog=1' : route('wishes.create', array_filter(['route' => $routeName, 'dialog' => 1]));
    $selectedKind = (string) old('kind', $wish?->kind->value ?? \App\Enums\Wishes\AppWishKind::Wish->value);
@endphp
<x-modal
    :title="$isEdit ? __('wishes.dialog.edit_title') : __('wishes.dialog.create_title')"
    :eyebrow="__('wishes.title')"
    icon="lightbulb"
    tone="primary"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('wishes.dialog.save') : __('wishes.dialog.submit')">

    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    <input type="hidden" name="route_name" value="{{ old('route_name', $routeName) }}">

    <div role="note" class="alert alert-info mb-3 text-sm">
        <x-icon name="info" />
        <span>{{ __('wishes.dialog.hint') }}</span>
    </div>

    <x-form-group :legend="__('wishes.dialog.legend')" icon="lightbulb" tone="primary">
        <x-select-field name="kind" id="wish-kind" :label="__('wishes.field.kind')" required>
            @foreach (\App\Enums\Wishes\AppWishKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected($selectedKind === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="body" id="wish-body" :label="__('wishes.field.body')" rows="5" required
                          :value="old('body', $wish?->body)" :hint="__('wishes.dialog.body_hint')" />
        <p class="text-xs text-muted">
            {{ __('wishes.field.page') }}: <span class="font-mono">{{ $routeName !== '' ? $routeName : '—' }}</span>
            @if ($remaining !== null)
                · {{ __('wishes.dialog.remaining', ['count' => $remaining]) }}
            @endif
        </p>
    </x-form-group>

    @unless ($isEdit)
        <p class="mt-2 text-sm"><a href="{{ route('wishes.index') }}" class="link link-primary">{{ __('wishes.dialog.all_wishes') }}</a></p>
    @endunless

    <x-validation-errors />
</x-modal>
