{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _intake_handover_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übernahme Druck (MVP-1076): Konditionen aus dem angenommenen Angebot, Produktionsdatei ausdrücklich wählen. --}}
<x-form-group :legend="__('print.section.order')" icon="print" tone="primary" cols="2">
    <x-select-field name="article_id" :label="__('print.field.article')" required span="2">
        <option value="">…</option>
        @foreach ($articles as $article)
            <option value="{{ $article->sqid }}" @selected((string) old('article_id', $defaultArticle) === $article->sqid)>{{ $article->name }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="target_qty" type="number" step="any" min="0.0001" :label="__('print.field.quantity')" :value="old('target_qty', $defaultQuantity)" required />
    <x-input-field name="unit" :label="__('print.field.unit')" :value="old('unit', $defaultUnit)" required />
    <x-input-field name="due_at" type="date" :label="__('print.field.due_at')" :value="old('due_at', $defaultDue)" />
    <x-select-field name="output_kind" :label="__('print.field.output_kind')" required>
        @foreach (\App\Enums\Print\PrintOutputKind::cases() as $kind)
            <option value="{{ $kind->value }}" @selected(old('output_kind', $defaultOutput) === $kind->value)>{{ $kind->label() }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="production_file" :label="__('print.intake.production_file')" span="2" :hint="__('print.intake.production_file_hint')">
        <option value="">{{ __('print.intake.production_file_later') }}</option>
        @foreach ($files as $file)
            <option value="{{ $file->sqid }}" @selected((string) old('production_file') === $file->sqid)>{{ $file->original_name }} · {{ $file->created_at?->fdatetime() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="files_retain_until" type="date" :label="__('print.field.files_retain_until')" :value="old('files_retain_until')" span="2" :hint="__('print.hint.retention')" />
</x-form-group>
