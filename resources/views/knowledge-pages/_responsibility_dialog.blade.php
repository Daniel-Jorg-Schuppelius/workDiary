{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _responsibility_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verantwortung, Prüfintervall, Gültigkeit, Notfallhandbuch und Einordnung
  einer Wissensseite (Feature 191, MVP-1665/1668). Variablen: $page,
  $members, $spaces (mit Rubriken), $parents.
--}}
@php
    $ownerSqid = $page->owner_user_id !== null ? \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $page->owner_user_id) : '';
@endphp
<x-modal
    :title="__('knowledge_pages.action.responsibility')"
    :eyebrow="$page->title"
    icon="manage_accounts"
    tone="primary"
    :action="route('knowledge.pages.responsibility.update', $page)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('knowledge_pages.action.save')">

    <x-form-group :legend="__('knowledge_pages.form.responsibility')" icon="manage_accounts" tone="primary" cols="2">
        <x-select-field name="owner" :label="__('knowledge_pages.field.owner')" span="2" :hint="__('knowledge_pages.form.owner_hint')">
            <option value="">—</option>
            @foreach ($members as $member)
                @php($memberSqid = \App\Support\Sqid::encode(\App\Models\Platform\User::class, (int) $member->id))
                <option value="{{ $memberSqid }}" @selected((string) old('owner', $ownerSqid) === $memberSqid)>{{ $member->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="review_interval_months" type="number" min="1" max="60" :label="__('knowledge_pages.field.review_interval_months')"
                       :value="old('review_interval_months', $page->review_interval_months)" :hint="__('knowledge_pages.form.review_hint')" />
        <x-input-field name="valid_until" type="date" :label="__('knowledge_pages.field.valid_until')"
                       :value="old('valid_until', $page->valid_until?->toDateString())" :hint="__('knowledge_pages.form.valid_hint')" />
        <x-checkbox-field name="is_emergency" :label="__('knowledge_pages.field.is_emergency')" :hint="__('knowledge_pages.form.emergency_hint')" :checked="(bool) old('is_emergency', $page->is_emergency)" span="2" />
    </x-form-group>

    <x-form-group :legend="__('knowledge_pages.form.placement')" icon="account_tree" tone="ghost" cols="2">
        <x-select-field name="space" :label="__('knowledge_pages.field.space')" required>
            @foreach ($spaces as $optionSpace)
                <option value="{{ $optionSpace->sqid }}" @selected(old('space', $page->space?->sqid) === $optionSpace->sqid)>{{ $optionSpace->title }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="section" :label="__('knowledge_pages.field.section')">
            <option value="">{{ __('knowledge_pages.form.no_section') }}</option>
            @foreach ($spaces as $optionSpace)
                @foreach ($optionSpace->sections as $optionSection)
                    <option value="{{ $optionSection->sqid }}" @selected(old('section', $page->section?->sqid) === $optionSection->sqid)>{{ $optionSpace->title }} › {{ $optionSection->title }}</option>
                @endforeach
            @endforeach
        </x-select-field>
        <x-select-field name="parent" :label="__('knowledge_pages.field.parent')" span="2" :hint="__('knowledge_pages.form.parent_hint', ['max' => \App\Models\Knowledge\KnowledgePage::MAX_DEPTH])">
            <option value="">{{ __('knowledge_pages.form.no_parent') }}</option>
            @foreach ($parents as $optionParent)
                <option value="{{ $optionParent->sqid }}" @selected(old('parent', $page->parent_id !== null ? \App\Support\Sqid::encode(\App\Models\Knowledge\KnowledgePage::class, (int) $page->parent_id) : '') === $optionParent->sqid)>{{ $optionParent->title }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
</x-modal>
