{{-- Versand-Dialog: Multi-Recipient (To/CC/BCC), Template-Auswahl, Freitext, Queue-Dispatch --}}
<x-modal
    :title="__('Rechnung :nr per E-Mail senden', ['nr' => $invoice->number])"
    :eyebrow="$invoice->documentLabel()"
    icon="mail"
    tone="primary"
    :action="route('invoices.send', $invoice)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Senden')"
>
    <div class="space-y-4">

        <div class="text-sm text-base-content/70">
            {{ __('Die Rechnung wird als PDF angehängt. Drafts werden beim Versand automatisch auf "gestellt" gesetzt.') }}
        </div>

        {{-- Template-Auswahl --}}
        <label class="form-control w-full">
            <span class="label label-text">{{ __('Vorlage') }}</span>
            <select name="template_id" class="select select-bordered w-full" required>
                @foreach ($templates as $tpl)
                    <option value="{{ $tpl->sqid }}" @selected($tpl->id === $defaultTemplateId)>
                        {{ $tpl->name }}@if ($tpl->is_default) ({{ __('Standard') }})@endif
                    </option>
                @endforeach
            </select>
        </label>

        {{-- An (To) --}}
        <label class="form-control w-full">
            <span class="label label-text">{{ __('An (To, mehrere mit Komma)') }} <span class="text-error">*</span></span>
            <input type="text" name="to[]" value="{{ $defaultTo }}" required
                   class="input input-bordered w-full"
                   data-multi-email
                   placeholder="empfaenger@firma.de">
            <span class="label label-text-alt">{{ __('Mehrere Empfänger durch Komma trennen.') }}</span>
        </label>

        {{-- CC --}}
        <label class="form-control w-full">
            <span class="label label-text">{{ __('CC (optional)') }}</span>
            <input type="text" name="cc[]" class="input input-bordered w-full" data-multi-email
                   placeholder="cc@firma.de">
        </label>

        {{-- BCC --}}
        <label class="form-control w-full">
            <span class="label label-text">{{ __('BCC (optional)') }}</span>
            <input type="text" name="bcc[]" class="input input-bordered w-full" data-multi-email
                   placeholder="bcc@firma.de">
        </label>

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="bcc_sender" value="1" checked class="checkbox checkbox-sm">
            <span class="label-text">{{ __('Kopie an Absender (:from)', ['from' => config('mail.from.address')]) }}</span>
        </label>

        {{-- Freitext --}}
        <label class="form-control w-full">
            <span class="label label-text">{{ __('Individueller Begleittext (optional)') }}</span>
            <textarea name="custom_text" rows="3" class="textarea textarea-bordered w-full"
                      placeholder="{{ __('Wird als Platzhalter custom_text im Template eingesetzt.') }}"></textarea>
        </label>

        <details class="text-xs">
            <summary class="cursor-pointer text-base-content/60">{{ __('Verfügbare Variablen') }}</summary>
            <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                @foreach ($variables as $key => $label)
                    <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> – {{ $label }}</li>
                @endforeach
            </ul>
        </details>

    </div>

    {{-- Kleines Inline-Script: splittet komma-getrennte Eingaben in mehrere [] Felder --}}
    <script>
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (! form.matches('form[data-entry-form]')) return;
        form.querySelectorAll('input[data-multi-email]').forEach(function (inp) {
            const raw = (inp.value || '').trim();
            if (! raw) { inp.remove(); return; }
            const parts = raw.split(/[,;\s]+/).filter(Boolean);
            if (parts.length <= 1) { inp.value = parts[0] || ''; return; }
            const name = inp.getAttribute('name');
            inp.value = parts[0];
            for (let i = 1; i < parts.length; i++) {
                const h = document.createElement('input');
                h.type = 'hidden';
                h.name = name;
                h.value = parts[i];
                inp.parentNode.appendChild(h);
            }
        });
    }, true);
    </script>
</x-modal>
