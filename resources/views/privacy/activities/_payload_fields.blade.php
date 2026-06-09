{{-- Strukturierte Art.-30-Felder (als payload-JSON gespeichert). $payload optional vorbelegen. --}}
@php $p = $payload ?? []; @endphp
<div class="grid md:grid-cols-2 gap-3">
    <div>
        <label class="label" for="data_categories">{{ __('Datenkategorien') }}</label>
        <textarea id="data_categories" name="data_categories" rows="2" class="textarea textarea-bordered w-full">{{ old('data_categories', $p['data_categories'] ?? '') }}</textarea>
    </div>
    <div>
        <label class="label" for="legal_basis">{{ __('Rechtsgrundlagen') }}</label>
        <textarea id="legal_basis" name="legal_basis" rows="2" class="textarea textarea-bordered w-full">{{ old('legal_basis', $p['legal_basis'] ?? '') }}</textarea>
    </div>
    <div>
        <label class="label" for="recipients">{{ __('Empfänger') }}</label>
        <textarea id="recipients" name="recipients" rows="2" class="textarea textarea-bordered w-full">{{ old('recipients', $p['recipients'] ?? '') }}</textarea>
    </div>
    <div>
        <label class="label" for="transfers">{{ __('Drittlandtransfers') }}</label>
        <textarea id="transfers" name="transfers" rows="2" class="textarea textarea-bordered w-full">{{ old('transfers', $p['transfers'] ?? '') }}</textarea>
    </div>
    <div>
        <label class="label" for="retention">{{ __('Aufbewahrung / Löschung') }}</label>
        <textarea id="retention" name="retention" rows="2" class="textarea textarea-bordered w-full">{{ old('retention', $p['retention'] ?? '') }}</textarea>
    </div>
    <div>
        <label class="label" for="tom">{{ __('TOM (techn./org. Maßnahmen)') }}</label>
        <textarea id="tom" name="tom" rows="2" class="textarea textarea-bordered w-full">{{ old('tom', $p['tom'] ?? '') }}</textarea>
    </div>
</div>
