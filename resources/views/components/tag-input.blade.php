@props([
    'name' => 'tag_ids',
    'newName' => 'new_tags',
    'available' => [],
    'selected' => [],
    'allowCreate' => true,
    'placeholder' => null,
    'id' => null,
])

@php
    $selectId = $id ?? $name;
    $createId = $selectId.'-new';
    $selectedIds = collect($selected)->map(fn ($v) => (string) (is_object($v) ? $v->id : $v))->all();
    $placeholderText = $placeholder ?? __('Tags auswählen…');
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-2']) }}>
    <select id="{{ $selectId }}"
            name="{{ $name }}[]"
            multiple
            class="select select-bordered min-h-[2.75rem] w-full @error($name) select-error @enderror">
        @foreach ($available as $tag)
            @php
                $tagId = (string) (is_array($tag) ? ($tag['id'] ?? null) : ($tag->id ?? null));
                $tagName = is_array($tag) ? ($tag['name'] ?? '') : ($tag->name ?? '');
                $isSelected = in_array($tagId, collect(old($name, $selectedIds))->map(fn ($v) => (string) $v)->all(), true);
            @endphp
            <option value="{{ $tagId }}" @selected($isSelected)>{{ $tagName }}</option>
        @endforeach
    </select>
    <p class="text-xs text-base-content/60">{{ $placeholderText }}</p>

    @if ($allowCreate)
        <input id="{{ $createId }}"
               type="text"
               name="{{ $newName }}"
               value="{{ old($newName) }}"
               placeholder="{{ __('Neue Tags (Komma, Semikolon oder Zeilenumbruch)') }}"
               class="input input-bordered w-full @error($newName) input-error @enderror"
               autocomplete="off">
    @endif

    @error($name)
        <p class="text-xs text-error">{{ $message }}</p>
    @enderror
    @error($newName)
        <p class="text-xs text-error">{{ $message }}</p>
    @enderror
</div>
