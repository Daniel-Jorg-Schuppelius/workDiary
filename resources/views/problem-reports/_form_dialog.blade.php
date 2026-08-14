{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: „Problem melden" (Feature 041, MVP-053) — Einstieg aus
     Hilfe-Sidebar und Supportmenü (per data-entry-modal-trigger in den
     Dialog-Host geladen). Die Vollseiten-Variante (aus standalone
     Fehlerseiten) rendert dieselben Felder in problem-reports.create. --}}
@php
    /** @var array<string, mixed> $context */
    /** @var string $diagnosticsMode */
    /** @var array<string, mixed>|null $diagnosticsPreview */
@endphp
<x-modal
    :title="__('problemreport.title.create')"
    :eyebrow="__('problemreport.title.eyebrow')"
    icon="flag"
    tone="warning"
    :action="route('problem-reports.store')"
    method="POST"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']"
    :submit-label="__('problemreport.action.submit')"
>
    @include('problem-reports._fields', [
        'context' => $context,
        'diagnosticsMode' => $diagnosticsMode,
        'diagnosticsPreview' => $diagnosticsPreview,
    ])
</x-modal>
