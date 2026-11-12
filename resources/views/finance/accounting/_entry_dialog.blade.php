{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Handbuchung und Entwurf bearbeiten (Feature 125, MVP-672/1556/1558):
  mehrere Zeilen mit Konto, Soll oder Haben, Steuerkennzeichen und
  Kostenstelle. Eine Zeile mit Steuerkennzeichen trägt den Nettobetrag; die
  Steuerzeile entsteht beim Speichern. Leere Zeilen werden ignoriert.
--}}
<x-modal
    :title="$entry ? __('accounting.entry_form.title_edit') : __('accounting.ledger.action.add_entry')"
    :icon="$entry ? 'edit' : 'post_add'"
    size="wide"
    :action="$entry ? route('finance.accounting.journal.update', $entry) : route('finance.accounting.journal.store')"
    :method="$entry ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
>
    <div class="grid gap-3 sm:grid-cols-4">
        <x-input-field name="booked_on" type="date" required
                       :label="__('accounting.ledger.column.booked_on')"
                       :value="old('booked_on', $entry?->booked_on?->toDateString() ?? now()->toDateString())" />
        <x-input-field name="document_on" type="date"
                       :label="__('accounting.ledger.column.document_on')"
                       :value="old('document_on', $entry?->document_on?->toDateString() ?? '')" />
        <x-input-field name="document_reference" type="text" maxlength="64" span="2"
                       :label="__('accounting.ledger.column.document_reference')"
                       :value="old('document_reference', $entry?->document_reference ?? '')" />
    </div>

    <x-input-field name="memo" type="text" required maxlength="191"
                   :label="__('accounting.ledger.column.memo')"
                   :value="old('memo', $entry?->memo ?? '')" />

    @include('finance.accounting._entry_rows')

    <x-validation-errors />

    @if (! $entry && $canPost)
        <x-checkbox-field name="post" :label="__('accounting.ledger.field.post_now')"
                          :hint="__('accounting.ledger.hint.post_now')"
                          :checked="(bool) old('post', false)" />
    @endif
</x-modal>
