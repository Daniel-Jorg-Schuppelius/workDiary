{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _insert_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Bibliotheksschritte in den Entwurf einfügen (MVP-896). Die
     Kopien stehen am Ende der Schrittliste. --}}
<x-modal :title="__('procedure.library.insert')" :eyebrow="$template->name" icon="library_add" tone="primary"
         :action="route('procedures.library.insert', $template)" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="__('procedure.library.insert')">
    <p class="text-sm text-warning">{{ __('procedure.library.insert_hint') }}</p>
    @if ($steps->isEmpty())
        <p class="text-sm text-muted">{{ __('procedure.library.empty') }}</p>
    @else
        <div class="max-h-80 space-y-1 overflow-y-auto">
            @foreach ($steps as $step)
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="library_steps[]" value="{{ $step->sqid }}" class="checkbox checkbox-sm mt-0.5">
                    <span><span class="font-medium">{{ $step->label }}</span> <span class="text-xs text-muted">· {{ $step->step_kind->label() }} · <code>{{ $step->code }}</code></span></span>
                </label>
            @endforeach
        </div>
    @endif
</x-modal>
