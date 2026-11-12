{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Reklamation anlegen/bearbeiten (Feature 072, MVP-247/248/1577).
     Erwartet: $claim (ClaimCase|null), $values (Vorbelegung: Kunde, Verantwortung, betroffene Objekte),
     Auswahllisten $customers, $users, $diaryEntries, $projects, $tickets, $protocols, $assets,
     $invoices, $suppliers, $articles, $lots, $purchaseOrders. --}}
@php
    $isEdit = $claim !== null;
    // Nach einem Validierungsfehler trägt old() die dekodierte Id, sonst gilt die Vorbelegung.
    $current = fn (string $field): string => (string) old($field, $values[$field] ?? '');
    $picked = fn (string $field, \Illuminate\Database\Eloquent\Model $model): bool => in_array($current($field), [(string) $model->getKey(), (string) $model->sqid], true);
    $customerSqid = fn (?int $id): string => $id !== null ? (string) \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $id) : '';
@endphp
<x-modal
    :title="$isEdit ? __('claims.form.title_edit') : __('Neue Reklamation')"
    :eyebrow="__('Reklamation')"
    icon="assignment_return"
    tone="primary"
    :badge="$claim?->number"
    :action="$isEdit ? route('claims.update', $claim) : route('claims.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Fall anlegen')"
>
    <x-form-group :legend="__('Eingang')" icon="assignment_return" tone="primary" cols="2">
        <x-input-field name="title" :label="__('Titel')" :value="old('title', $claim?->title)" required span="2" />
        <x-select-field name="source" :label="__('Eingangskanal')" required>
            @foreach (\App\Enums\Claims\ClaimSource::cases() as $s)
                <option value="{{ $s->value }}" @selected(old('source', $claim?->source->value ?? 'manual') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="customer_id" :label="__('Kunde (optional)')"
                        data-prefill-from="invoice_id" data-prefill-key="customer">
            <option value="">{{ __('ohne Kundenbezug (interner Mangel)') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected($picked('customer_id', $c))>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="priority" :label="__('Priorität')" required>
            @foreach (\App\Models\Claims\ClaimCase::PRIORITIES as $p)
                <option value="{{ $p }}" @selected(old('priority', $claim?->priority ?? 'normal') === $p)>{{ __("values.$p") }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="severity" :label="__('Schweregrad')" required>
            @foreach (\App\Models\Claims\ClaimCase::SEVERITIES as $sev)
                <option value="{{ $sev }}" @selected(old('severity', $claim?->severity ?? 'minor') === $sev)>{{ __("values.$sev") }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('Beschreibung des Mangels')" rows="3" span="2">{{ old('description', $claim?->description) }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('claims.form.group_links')" :description="__('claims.form.hint_links')" icon="link" tone="primary" cols="2">
        <x-select-field name="invoice_id" :label="__('Rechnung')" data-depends-on="customer_id">
            <option value="">—</option>
            @foreach ($invoices as $invoice)
                <option value="{{ $invoice->sqid }}" data-parent="{{ $customerSqid($invoice->customer_id) }}" data-prefill-customer="{{ $customerSqid($invoice->customer_id) }}"
                        @selected($picked('invoice_id', $invoice))>{{ $invoice->number }}@if ($invoice->issued_on !== null) · {{ $invoice->issued_on->fdate() }}@endif</option>
            @endforeach
        </x-select-field>
        <x-select-field name="diary_entry_id" :label="__('Auftrag')" data-depends-on="customer_id">
            <option value="">—</option>
            @foreach ($diaryEntries as $entry)
                <option value="{{ $entry->sqid }}" data-parent="{{ $customerSqid($entry->customer_id) }}" @selected($picked('diary_entry_id', $entry))>#{{ $entry->id }} {{ $entry->title }}</option>
            @endforeach
        </x-select-field>
        <x-project-select :label="__('Projekt')" :projects="$projects"
                          :selected="\App\Support\Sqid::encode(\App\Models\Project\Project::class, $current('project_id'))"
                          data-depends-on="customer_id" :data-parent="true" />
        <x-select-field name="service_ticket_id" :label="__('Service-Ticket')" data-depends-on="customer_id">
            <option value="">—</option>
            @foreach ($tickets as $ticket)
                <option value="{{ $ticket->sqid }}" data-parent="{{ $customerSqid($ticket->customer_id) }}" @selected($picked('service_ticket_id', $ticket))>{{ $ticket->ticket_no }} — {{ \Illuminate\Support\Str::limit((string) $ticket->title, 60) }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="asset_id" :label="__('Asset')" data-depends-on="customer_id">
            <option value="">—</option>
            @foreach ($assets as $asset)
                <option value="{{ $asset->sqid }}" data-parent="{{ $customerSqid($asset->customer_id) }}" data-prefill-serial="{{ $asset->serial_no }}"
                        @selected($picked('asset_id', $asset))>{{ $asset->name }}@if ($asset->serial_no) · {{ $asset->serial_no }}@endif</option>
            @endforeach
        </x-select-field>
        <x-input-field name="serial_no" :label="__('Seriennummer (optional)')" :value="old('serial_no', $values['serial_no'] ?? null)"
                       :hint="__('claims.form.hint_serial')" maxlength="255"
                       data-prefill-from="asset_id" data-prefill-key="serial" />
        <x-select-field name="protocol_id" :label="__('validation.attributes.protocol_id')">
            <option value="">—</option>
            @foreach ($protocols as $protocol)
                <option value="{{ $protocol->sqid }}" @selected($picked('protocol_id', $protocol))>{{ \Illuminate\Support\Str::limit((string) $protocol->title, 70) }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="supplier_id" :label="__('Lieferant')" data-prefill-from="purchase_order_id" data-prefill-key="supplier">
            <option value="">—</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->sqid }}" @selected($picked('supplier_id', $supplier))>{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        @if ($articles->isNotEmpty())
            <x-select-field name="article_id" :label="__('Artikel')">
                <option value="">—</option>
                @foreach ($articles as $article)
                    <option value="{{ $article->sqid }}" @selected($picked('article_id', $article))>{{ $article->number ? $article->number . ' · ' : '' }}{{ $article->name }}</option>
                @endforeach
            </x-select-field>
        @endif
        @if ($lots->isNotEmpty())
            {{-- Charge (MVP-1626): nur bei chargengeführten Artikeln, gefiltert nach dem gewählten Artikel. --}}
            <x-select-field name="stock_lot_id" :label="__('validation.attributes.stock_lot_id')" data-depends-on="article_id">
                <option value="">—</option>
                @foreach ($lots as $lot)
                    <option value="{{ $lot->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Article\Article::class, (int) $lot->variant?->article_id) }}"
                            @selected($picked('stock_lot_id', $lot))>{{ $lot->lot_no }}@if ($lot->variant?->article) · {{ $lot->variant->article->name }}@endif</option>
                @endforeach
            </x-select-field>
        @endif
        @if ($purchaseOrders->isNotEmpty())
            <x-select-field name="purchase_order_id" :label="__('validation.attributes.purchase_order_id')">
                <option value="">—</option>
                @foreach ($purchaseOrders as $order)
                    <option value="{{ $order->sqid }}" data-prefill-supplier="{{ \App\Support\Sqid::encode(\App\Models\Supplier\Supplier::class, $order->supplier_id) }}"
                            @selected($picked('purchase_order_id', $order))>{{ $order->number }}</option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>

    <x-form-group :legend="__('Fristen & Verantwortung')" icon="schedule" tone="primary" cols="2">
        <x-input-field name="due_at" type="date" :label="__('Bearbeitungsfrist (optional)')" :value="old('due_at', $claim?->due_at?->format('Y-m-d'))" />
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
            <option value="">{{ __('-- später zuweisen --') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected($picked('responsible_user_id', $u))>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_b2b" value="0">
            <input type="checkbox" id="claim-is-b2b" name="is_b2b" value="1" class="checkbox checkbox-sm" @checked(old('is_b2b', $claim?->is_b2b))>
            <label for="claim-is-b2b" class="text-sm">{{ __('B2B-Fall (Rügepflicht § 377 HGB)') }}</label>
        </div>
        <x-input-field name="complaint_notice_at" type="date" :label="__('Rügedatum (B2B)')" :value="old('complaint_notice_at', $claim?->complaint_notice_at?->format('Y-m-d'))" />
        <x-input-field name="reporter_name" :label="__('Melder (Name)')" :value="old('reporter_name', $claim?->reporter_name)" />
        <x-input-field name="reporter_email" type="email" :label="__('Melder (E-Mail)')" :value="old('reporter_email', $claim?->reporter_email)" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
