{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : field-form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Langes Formular aus einem Feldschema (Feature 203, MVP-1649–1651): gliedert
  an Abschnittsfeldern (FieldType::Section) in Karten mit Nummer, Titel und
  „x von y beantwortet“, setzt vor jede Frage einen Punkt offen/beantwortet
  und zeigt ab FieldLayout::NAVIGATION_MIN_SECTIONS Abschnitten eine
  Inhaltsleiste (schmal als Reihe oben, breit links mitlaufend). Bis
  FieldLayout::SINGLE_CARD_MAX_QUESTIONS Fragen bleibt es eine Karte.
  Die Zählung kommt vom Server (FieldValues::answeredBySection) und wird im
  Browser nachgeführt (Alpine „fieldForm“, resources/js/lib/field-form.js).

  Die Eingaben rendert <x-field-input> — die Typverzweigung bleibt dort.
  Mit `autosave` speichert der Browser jedes Feld beim Verlassen über den
  Vertrag AutosavesFieldDocument (Endpunkt field-documents.autosave):
    ['url' => Feld-URL mit __FIELD__, 'version' => int]                  — Träger existiert
    ['start_url' => …, 'start_fields' => [Eingabenamen]]                  — Träger entsteht beim ersten Speichern
  Das umgebende Formular trägt dann die Felder `draft` und `draft_version`.
--}}
@props([
    'schema',                 // \App\Services\Fields\FieldSchema
    'values' => null,         // \App\Services\Fields\FieldValues|null
    'prefix' => 'values',
    'title' => null,          // Titel des ersten Abschnitts, wenn das Schema nicht mit einem Abschnitt beginnt
    'notes' => false,         // Ergänzungsfelder anbieten (der Träger speichert notes[<key>])
    'autosave' => null,       // siehe Kopf
    'talk' => false,          // Knopf „Gesprächsmodus“ (große Tippflächen)
    'headingLevel' => 3,
    'idPrefix' => null,
])

@php
    $layout = \App\Services\Fields\FieldLayout::class;
    $values = $values instanceof \App\Services\Fields\FieldValues ? $values : new \App\Services\Fields\FieldValues;
    // Nach einem gescheiterten Absenden ohne JS stehen die Eingaben in old().
    $oldInput = old($prefix);
    if (is_array($oldInput)) {
        $values = $values->with(\App\Services\Fields\FieldValues::normalize($schema, $oldInput)->toArray());
    }
    $idBase = $idPrefix ?? ('fld-' . (preg_replace('/[^A-Za-z0-9_-]/', '', $prefix) ?: 'x'));
    $notesName = $prefix === 'values' || $prefix === '' ? 'notes' : $prefix . '_notes';
    $sections = $schema->sections();
    $cards = $layout::usesCards($schema);
    $nav = $layout::showsNavigation($schema);
    $progress = collect($values->answeredBySection($schema))->keyBy('key');
    $headingTag = 'h' . max(2, min(6, (int) $headingLevel));
    $sectionTitle = fn (array $section): string => $section['field']?->label ?? (string) ($title ?? __('fields.form.general'));
    $progressLabel = fn (array $p): string => (string) __('fields.form.progress', ['answered' => $p['answered'], 'total' => $p['total']]);

    // Alpine-Konfiguration als data-Attribute (CSP-Build).
    $conditions = [];
    foreach ($schema as $field) {
        if ($field->hasCondition()) {
            $conditions[$field->key] = $field->visibleIf;
        }
    }
    $sectionMap = [];
    foreach ($sections as $section) {
        $keys = [];
        foreach ($section['fields'] as $field) {
            if ($field->type->hasValue()) {
                $keys[] = $field->key;
            }
        }
        $sectionMap[$cards ? $section['key'] : '_all'] = array_merge($sectionMap[$cards ? $section['key'] : '_all'] ?? [], $keys);
    }
    $allProgress = ['answered' => (int) $progress->sum('answered'), 'total' => (int) $progress->sum('total')];
    // Karten: je Abschnitt eine; sonst eine Karte mit allen Feldern (Abschnitte als Zwischenüberschrift).
    $groups = [];
    if ($cards) {
        foreach ($sections as $i => $section) {
            $groups[] = [
                'key' => $section['key'],
                'id' => $idBase . '-sec-' . $section['key'],
                'number' => $i + 1,
                'title' => $sectionTitle($section),
                'help' => $section['field']?->help,
                'fields' => $section['fields'],
                'progress' => $progress[$section['key']] ?? ['answered' => 0, 'total' => 0],
            ];
        }
    } else {
        $groups[] = [
            'key' => '_all',
            'id' => $idBase . '-sec-all',
            'number' => null,
            'title' => (string) ($title ?? __('fields.form.general')),
            'help' => null,
            'fields' => $schema->all(),
            'progress' => $allProgress,
        ];
    }
    $texts = [
        'progress' => (string) __('fields.form.progress', ['answered' => ':answered', 'total' => ':total']),
        'hint' => (string) __('fields.autosave.hint'),
        'errors' => (string) __('fields.form.errors_count', ['count' => ':count']),
        'saving' => (string) __('fields.autosave.saving'),
        'saved' => (string) __('fields.autosave.saved'),
        'retrying' => (string) __('fields.autosave.retrying'),
        'stalled' => (string) __('fields.autosave.stalled'),
        'conflict' => (string) __('fields.autosave.conflict'),
        'closed' => (string) __('fields.autosave.closed'),
        'invalid' => (string) __('fields.autosave.invalid'),
    ];

    // Fehler vom Server (Absenden ohne JS): kurz oben, mit Sprung zum Feld.
    $serverErrors = [];
    foreach ($schema as $field) {
        foreach ([$prefix . '.' . $field->key, 'files.' . $field->key, $notesName . '.' . $field->key] as $errorKey) {
            if ($errors->has($errorKey)) {
                $serverErrors[$field->key] = ['label' => $field->label, 'message' => $errors->first($errorKey)];
                break;
            }
        }
    }
@endphp

<div {{ $attributes->class(['wd-field-form', 'wd-field-form--nav' => $nav]) }}
     x-data="fieldForm"
     data-prefix="{{ $prefix }}"
     data-notes-prefix="{{ $notesName }}"
     data-id-base="{{ $idBase }}"
     data-conditions="{{ json_encode($conditions) }}"
     data-initial="{{ json_encode($values->scalars($schema)) }}"
     data-sections="{{ json_encode($sectionMap) }}"
     data-booleans="{{ json_encode($schema->booleanKeys()) }}"
     data-texts="{{ json_encode($texts) }}"
     @if ($autosave !== null) data-autosave="{{ json_encode($autosave) }}" @endif
     :class="rootClass"
     @input.capture="track($event)" @change.capture="changed($event)">

    @if ($serverErrors !== [])
        <div class="alert alert-error mb-3 flex-col items-start gap-1 text-sm" role="alert" tabindex="-1" data-field-form-summary>
            <p class="font-semibold">{{ __('fields.form.errors_title') }}</p>
            <ul class="list-disc ps-5">
                @foreach ($serverErrors as $errorField => $error)
                    <li><a class="link" href="#{{ $idBase }}-{{ $errorField }}">{{ $error['label'] }}: {{ $error['message'] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif
    {{-- Fehler aus dem Browser (Absenden im Dialog, Speichern je Feld). --}}
    <div class="alert alert-error mb-3 text-sm" role="alert" x-show="errorCount" x-cloak>
        <span x-text="errorSummary"></span>
        <x-button type="button" tone="ghost" size="xs" @click="focusFirstError()">{{ __('fields.form.errors_jump') }}</x-button>
    </div>

    @if ($autosave !== null || $talk)
        <div class="wd-field-form__bar">
            @if ($autosave !== null)
                <p class="wd-field-form__status text-xs text-muted" role="status" aria-live="polite">
                    <x-icon name="cloud_done" class="wd-field-form__status-icon" />
                    <span x-text="statusText">{{ __('fields.autosave.hint') }}</span>
                </p>
            @endif
            @if ($talk)
                <x-button type="button" tone="ghost" size="sm" icon="record_voice_over" class="ms-auto"
                          x-bind:aria-pressed="talkPressed" @click="toggleTalk()">{{ __('fields.form.talk_mode') }}</x-button>
            @endif
        </div>
    @endif
    @if ($autosave !== null)
        <div class="alert alert-warning mb-3 flex-col items-start gap-2 text-sm" role="alert" x-show="conflict" x-cloak>
            <p x-text="conflictText"></p>
            <div class="flex flex-wrap gap-2">
                <x-button type="button" tone="primary" size="xs" icon="save" x-show="canOverride" @click="overrideConflict()">{{ __('fields.autosave.keep_mine') }}</x-button>
                <x-button type="button" tone="ghost" size="xs" icon="refresh" @click="reloadDocument()">{{ __('fields.autosave.reload') }}</x-button>
            </div>
        </div>
    @endif

    <div class="wd-field-form__layout">
        @if ($nav)
            <nav class="wd-field-nav" aria-label="{{ __('fields.form.contents') }}">
                <ol class="wd-field-nav__list">
                    @foreach ($sections as $i => $section)
                        @php $p = $progress[$section['key']] ?? ['answered' => 0, 'total' => 0]; @endphp
                        <li @if ($section['field']?->hasCondition()) x-show="visible('{{ $section['key'] }}')" x-cloak @endif>
                            <a href="#{{ $idBase }}-sec-{{ $section['key'] }}" @click.prevent="jump('{{ $idBase }}-sec-{{ $section['key'] }}')"
                               @class(['wd-field-nav__link', 'wd-field-nav__link--done' => $p['total'] > 0 && $p['answered'] === $p['total']])
                               :class="navClass('{{ $section['key'] }}')">
                                <span class="wd-field-nav__num" aria-hidden="true">{{ $i + 1 }}</span>
                                <span class="wd-field-nav__title">{{ $sectionTitle($section) }}</span>
                                <span class="wd-field-nav__count">
                                    <span class="sr-only">{{ __('fields.form.section_state') }}</span>
                                    <span x-text="sectionShort('{{ $section['key'] }}')">{{ $p['answered'] }}/{{ $p['total'] }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <div class="wd-field-form__cards">
            @foreach ($groups as $group)
                <x-card as="section" padding="p-4" class="wd-field-card" id="{{ $group['id'] }}" tabindex="-1"
                        aria-labelledby="{{ $group['id'] }}-title" x-show="visible('{{ $group['key'] }}')">
                    <header class="wd-field-card__head">
                        @if ($group['number'] !== null)
                            <span class="wd-field-card__num" aria-hidden="true">{{ $group['number'] }}</span>
                        @endif
                        <{{ $headingTag }} id="{{ $group['id'] }}-title" class="wd-field-card__title">{{ $group['title'] }}</{{ $headingTag }}>
                        <span class="wd-field-card__count" x-text="sectionText('{{ $group['key'] }}')">{{ $progressLabel($group['progress']) }}</span>
                    </header>
                    @if ($group['help'] !== null)
                        <p class="mb-3 text-xs text-muted">{{ $group['help'] }}</p>
                    @endif
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($group['fields'] as $field)
                            <div @class(['wd-field', 'sm:col-span-2' => $layout::fullWidth($field)]) data-field="{{ $field->key }}"
                                 @if ($field->hasCondition()) x-show="visible('{{ $field->key }}')" x-cloak @endif>
                                <x-field-input :field="$field" :value="$values->get($field->key)" :prefix="$prefix" :wide="false"
                                               :marker="$field->type->hasValue() ? $values->isAnswered($field) : null"
                                               :notes="$notes" :note="$values->note($field->key)" :id-prefix="$idBase" />
                                <p class="text-error text-sm" id="{{ $idBase }}-{{ $field->key }}-cerror"
                                   x-show="hasError('{{ $field->key }}')" x-text="errorText('{{ $field->key }}')" x-cloak></p>
                                @if ($autosave !== null && $field->type->storesAttachment())
                                    <p class="text-xs text-muted">{{ __('fields.autosave.attachment_on_submit') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>
    </div>
</div>
