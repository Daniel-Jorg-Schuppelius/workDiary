{{-- Variablen: $mode ('create'|'edit'), $definition (?ThemeDefinition) --}}
@php
    /** @var \App\Support\ThemeDefinition|null $definition */
    $isEdit = ($mode ?? 'create') === 'edit';
    $defArr = $definition?->toArray() ?? [];
    $defColors = (array) ($defArr['colors'] ?? []);

    $action = $isEdit ? route('admin.themes.update', $definition->key) : route('admin.themes.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $dialogUrl = ($isEdit ? route('admin.themes.edit', $definition->key) : route('admin.themes.create')) . '?dialog=1';

    $defaults = [
        'base-100' => '#ffffff', 'base-200' => '#f1f5f9', 'base-300' => '#e2e8f0',
        'primary' => '#2563eb', 'secondary' => '#475569', 'accent' => '#0d9488', 'neutral' => '#1e293b',
        'info' => '#0ea5e9', 'success' => '#16a34a', 'warning' => '#f59e0b', 'error' => '#dc2626',
    ];
    $colorValues = [];
    foreach ($defaults as $k => $v) {
        $colorValues[$k] = (string) old("colors.$k", $defColors[$k] ?? $v);
    }

    $requiredLabels = [
        'base-100' => __('Hintergrund'), 'base-200' => __('Fläche'), 'base-300' => __('Rahmen'),
        'primary' => __('Primär'), 'secondary' => __('Sekundär'), 'accent' => __('Akzent'),
        'neutral' => __('Neutral (Seitenleiste)'),
    ];
    $statusLabels = ['info' => __('Info'), 'success' => __('Erfolg'), 'warning' => __('Warnung'), 'error' => __('Fehler')];

    $geometry = (array) ($defArr['geometry'] ?? config('theme.geometry', []));
    $scheme = (string) old('scheme', $defArr['scheme'] ?? 'light');
@endphp

<x-modal
    :title="$isEdit ? __('Theme bearbeiten') : __('Neues Theme')"
    :eyebrow="__('Themes')"
    icon="format_paint"
    tone="primary"
    size="wide"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">

    {{-- Logik in Alpine.data("themePreview") (components.js) — Inline-Objekte
         mit Methoden kann der @alpinejs/csp-Parser nicht auswerten (Stufe 2). --}}
    <div x-data="themePreview"
         data-config="{{ json_encode(['scheme' => $scheme, 'colors' => $colorValues]) }}"
         class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- ── Eingaben ─────────────────────────────────────────────── --}}
        <div class="space-y-4">
            <x-form-group :legend="__('Stammdaten')" icon="badge" tone="primary" cols="2">
                <div class="fieldset">
                    <label class="fieldset-label" for="theme-key">{{ __('Schlüssel') }} *</label>
                    <input id="theme-key" type="text" name="key" required
                           pattern="[a-z0-9\-]{1,32}" maxlength="32"
                           value="{{ old('key', $defArr['key'] ?? '') }}"
                           class="input input-bordered w-full font-mono @error('key') input-error @enderror"
                           @if ($isEdit) readonly @endif>
                    <p class="text-xs opacity-60 mt-1">{{ __('Kleinbuchstaben, Ziffern, Bindestrich. Nicht änderbar nach dem Anlegen.') }}</p>
                    @error('key')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>
                <x-input-field name="label" :label="__('Name')" required maxlength="60"
                               :value="old('label', $defArr['label'] ?? '')" />
                <div class="fieldset md:col-span-2">
                    <label class="fieldset-label" for="theme-scheme">{{ __('Grundmodus') }}</label>
                    <select id="theme-scheme" name="scheme" x-model="scheme" class="select select-bordered w-full">
                        <option value="light">{{ __('Hell') }}</option>
                        <option value="dark">{{ __('Dunkel') }}</option>
                    </select>
                </div>
            </x-form-group>

            <x-form-group :legend="__('Farben')" icon="palette" tone="info" cols="1">
                <p class="text-xs opacity-60">{{ __('Textfarben (…-content) werden automatisch aus dem Kontrast abgeleitet.') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($requiredLabels + $statusLabels as $key => $label)
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="colors['{{ $key }}']" class="wd-color-swatch shrink-0">
                            <input type="text" name="colors[{{ $key }}]" x-model="colors['{{ $key }}']"
                                   class="input input-bordered input-sm w-24 font-mono @error('colors.'.$key) input-error @enderror">
                            <span class="text-xs opacity-80">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
                @error('colors.neutral-content')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
            </x-form-group>

            <x-form-group :legend="__('Geometrie')" icon="rounded_corner" tone="ghost" cols="2">
                @foreach (['radius-box' => __('Radius Box'), 'radius-field' => __('Radius Feld'), 'radius-selector' => __('Radius Selektor'), 'border' => __('Rahmenbreite')] as $gk => $glabel)
                    <div class="fieldset">
                        <label class="fieldset-label">{{ $glabel }}</label>
                        <input type="text" name="geometry[{{ $gk }}]"
                               value="{{ old('geometry.'.$gk, $geometry[$gk] ?? '') }}"
                               placeholder="z. B. 0.5rem"
                               class="input input-bordered input-sm w-full font-mono @error('geometry.'.$gk) input-error @enderror">
                    </div>
                @endforeach
            </x-form-group>
        </div>

        {{-- ── Live-Vorschau ────────────────────────────────────────── --}}
        <div class="lg:sticky lg:top-2 self-start">
            <p class="text-xs uppercase tracking-wide opacity-60 mb-2">{{ __('Vorschau') }}</p>
            <div :style="previewStyle()" class="rounded-box border border-base-300 p-4 space-y-3"
                 style="background:var(--color-base-100);color:var(--color-base-content)">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">{{ __('Beispiel-Karte') }}</span>
                    <span class="text-xs opacity-70">{{ __('Auftragsbuch') }}</span>
                </div>
                <div class="rounded-box p-3" style="background:var(--color-base-200)">
                    <p class="text-sm">{{ __('Inhalt auf einer Fläche.') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="px-3 py-1 rounded-field text-sm" style="background:var(--color-primary);color:var(--color-primary-content)">{{ __('Primär') }}</span>
                    <span class="px-3 py-1 rounded-field text-sm" style="background:var(--color-secondary);color:var(--color-secondary-content)">{{ __('Sekundär') }}</span>
                    <span class="px-3 py-1 rounded-field text-sm" style="background:var(--color-accent);color:var(--color-accent-content)">{{ __('Akzent') }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach (['info' => __('Info'), 'success' => __('OK'), 'warning' => __('Warn'), 'error' => __('Fehler')] as $sk => $sl)
                        <span class="px-2 py-0.5 rounded text-xs" style="background:var(--color-{{ $sk }});color:var(--color-{{ $sk }}-content)">{{ $sl }}</span>
                    @endforeach
                </div>
                {{-- Seitenleisten-Panel (Neutral) — bildet die .wd-badge-Optik nach --}}
                <div class="rounded-box p-3 mt-1" style="background:var(--color-neutral);color:var(--color-neutral-content)">
                    <span class="text-sm font-medium">{{ __('Seitenleiste / Panel') }}</span>
                    <p class="text-xs opacity-80">{{ __('Navigation und schwebende Panels nutzen diese Fläche.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <x-validation-errors class="mt-3" />
</x-modal>
