{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Komponente hinzufügen oder ersetzen (Feature 118, MVP-607).

  Die Variable heisst $part, nicht $component: Innerhalb einer Blade-Komponente
  ist $component an die AnonymousComponent gebunden und überschreibt eine
  gleichnamige View-Variable — der Fehler faellt erst zur Laufzeit auf.
--}}
<x-modal
    :title="$part === null ? __('asset.components.action.add') : __('asset.components.action.replace')"
    :eyebrow="$asset->name"
    icon="build"
    :action="$part === null ? route('assets.components.store', $asset) : route('assets.components.replace', [$asset, $part])"
    method="POST"
    :submit-label="__('Speichern')"
>
    @if ($part !== null)
        <p class="text-sm text-base-content/70">{{ __('asset.components.replace_hint', ['name' => $part->displayName()]) }}</p>
    @endif

    <div>
        <label class="label" for="comp-article"><span class="label-text">{{ __('asset.components.column.article') }}</span></label>
        <select id="comp-article" name="article_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($articles as $article)
                <option value="{{ $article->sqid }}" @selected(old('article_id') === $article->sqid)>{{ $article->name }}@if ($article->number) ({{ $article->number }})@endif</option>
            @endforeach
        </select>
    </div>

    <x-input-field name="label" type="text" maxlength="191"
                   :label="__('asset.components.column.label')"
                   :value="old('label', '')"
                   :hint="__('asset.components.label_hint')" />

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="quantity" type="number" step="0.001" min="0.001"
                       :label="__('asset.components.column.quantity')"
                       :value="old('quantity', '1')" />
        <x-input-field name="unit" type="text" maxlength="32"
                       :label="__('asset.components.column.unit')"
                       :value="old('unit', '')" />
        <x-input-field name="position" type="text" maxlength="120"
                       :label="__('asset.components.column.position')"
                       :value="old('position', '')" />
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="serial_no" type="text" maxlength="120"
                       :label="__('asset.components.column.serial_no')"
                       :value="old('serial_no', '')" />
        <x-input-field name="installed_on" type="date"
                       :label="__('asset.components.column.installed_on')"
                       :value="old('installed_on', now()->toDateString())" />
        <x-input-field name="replace_interval_months" type="number" min="1" max="600"
                       :label="__('asset.components.column.interval')"
                       :value="old('replace_interval_months', '')"
                       :hint="__('asset.components.interval_hint')" />
    </div>

    <x-input-field name="note" type="text" maxlength="500"
                   :label="__('asset.components.column.note')"
                   :value="old('note', '')" />
</x-modal>
