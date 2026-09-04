{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abo anlegen/bearbeiten (Feature 152, MVP-758). Halterwahl: Kunde,
  Fremdkunde (Endkunde eines Partners), eigener Bestand oder noch offen.
  Sqids nach außen; die Perioden plant der Controller nach dem Speichern.
--}}
@php
    $editing = $subscription !== null;
    $holderDefault = $editing
        ? ($subscription->is_own_holding ? 'own' : ($subscription->foreign_customer_id !== null ? 'foreign' : ($subscription->customer_id !== null ? 'customer' : 'none')))
        : (string) ($prefill['holder'] ?? 'none');
    $holder = (string) old('holder', $holderDefault);
    $customerSqid = (string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $editing ? $subscription->customer_id : ($prefill['customer_id'] ?? null)));
    $foreignSqid = (string) old('foreign_customer_id', \App\Support\Sqid::encode(\App\Models\ForeignCustomer::class, $editing ? $subscription->foreign_customer_id : ($prefill['foreign_customer_id'] ?? null)));
    $articleSqid = (string) old('article_id', \App\Support\Sqid::encode(\App\Models\Article::class, $editing ? $subscription->article_id : null));
    $lexArticleSqid = (string) old('lexoffice_article_id', \App\Support\Sqid::encode(\App\Models\LexofficeArticle::class, $editing ? $subscription->lexoffice_article_id : null));
    $value = static fn(string $field, mixed $default = null): mixed => old($field, $editing ? ($subscription->{$field} ?? $default) : $default);
    $enumValue = static fn(string $field, string $default): string => (string) old($field, $editing ? $subscription->{$field}->value : $default);
@endphp
<x-modal
    :title="$editing ? __('resale.dialog.title_edit') : __('resale.dialog.title_new')"
    icon="subscriptions"
    tone="primary"
    size="lg"
    :action="$editing ? route('finance.resale.update', $subscription->sqid) : route('finance.resale.store')"
    :method="$editing ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$editing ? __('resale.dialog.submit_edit') : __('resale.dialog.submit_new')"
>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <x-input-field name="label" :label="__('resale.field.label')" :value="$value('label')" required span="4" />
        <x-select-field name="kind" :label="__('resale.field.kind')" span="2">
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected($enumValue('kind', 'license') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>

        <x-select-field name="provider" :label="__('resale.field.provider')" span="2">
            @foreach ($providers as $provider)
                <option value="{{ $provider->value }}" @selected($enumValue('provider', 'manual') === $provider->value)>{{ $provider->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="external_id" :label="__('resale.field.external_id')" :value="$value('external_id')" span="2" />
        <x-input-field name="external_order_id" :label="__('resale.field.external_order_id')" :value="$value('external_order_id')" span="2" />

        <x-select-field name="holder" :label="__('resale.field.holder')" span="2" :hint="__('resale.dialog.holder_hint')">
            <option value="none" @selected($holder === 'none')>{{ __('resale.holder.unassigned') }}</option>
            <option value="customer" @selected($holder === 'customer')>{{ __('resale.holder.customer') }}</option>
            <option value="foreign" @selected($holder === 'foreign')>{{ __('resale.holder.foreign') }}</option>
            <option value="own" @selected($holder === 'own')>{{ __('resale.holder.own') }}</option>
        </x-select-field>
        <x-select-field name="customer_id" :label="__('resale.holder.customer')" span="2">
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected($customerSqid === $customer->sqid)>{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="foreign_customer_id" :label="__('resale.holder.foreign')" span="2">
            <option value="">—</option>
            @foreach ($foreignCustomers as $foreign)
                <option value="{{ $foreign->sqid }}" @selected($foreignSqid === $foreign->sqid)>{{ $foreign->name }} ({{ $foreign->customer?->name }})</option>
            @endforeach
        </x-select-field>

        @if ($articles->isNotEmpty())
            <x-select-field name="article_id" :label="__('resale.field.article')" span="3" :hint="__('resale.dialog.article_hint')">
                <option value="">{{ __('resale.dialog.no_article') }}</option>
                @foreach ($articles as $article)
                    <option value="{{ $article->sqid }}" @selected($articleSqid === $article->sqid)>{{ $article->number ? $article->number . ' · ' : '' }}{{ $article->name }}</option>
                @endforeach
            </x-select-field>
        @endif
        <x-select-field name="lexoffice_article_id" :label="__('resale.field.lexoffice_article')" :span="$articles->isNotEmpty() ? 3 : 4" :hint="__('resale.dialog.lexoffice_article_hint')">
            <option value="">{{ __('resale.dialog.no_article') }}</option>
            @foreach ($lexofficeArticles as $article)
                @php $lexSqid = \App\Support\Sqid::encode(\App\Models\LexofficeArticle::class, $article->id); @endphp
                <option value="{{ $lexSqid }}" @selected($lexArticleSqid === $lexSqid)>{{ $article->article_number ? $article->article_number . ' · ' : '' }}{{ $article->name }}{{ $article->net_unit_price ? ' — ' . $article->net_unit_price->withScale(2)->format() . ($article->unit_name ? '/' . $article->unit_name : '') : '' }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="quantity" type="number" :label="__('resale.field.quantity')" :value="$value('quantity', 1)" required :span="$articles->isNotEmpty() ? 6 : 2" />

        <div class="md:col-span-4">
            <x-date-range layout="split" from-name="starts_on" to-name="ends_on" form-control size=""
                          :from-label="__('resale.field.starts_on')"
                          :to-label="__('resale.field.ends_on')"
                          :from-required="true"
                          :from="old('starts_on', $editing ? $subscription->starts_on->toDateString() : now()->toDateString())"
                          :to="old('ends_on', $editing ? ($subscription->ends_on?->toDateString() ?? '') : '')" />
            <p class="text-xs text-muted mt-1">{{ __('resale.dialog.ends_on_hint') }}</p>
        </div>
        <x-input-field name="term_months" type="number" :label="__('resale.field.term_months')" :value="$value('term_months', 12)" required span="2" />

        <x-select-field name="interval" :label="__('resale.field.interval')" span="2">
            @foreach ($intervals as $interval)
                <option value="{{ $interval->value }}" @selected($enumValue('interval', 'yearly') === $interval->value)>{{ $interval->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="renewal" :label="__('resale.field.renewal')" span="2">
            @foreach ($renewals as $renewal)
                <option value="{{ $renewal->value }}" @selected($enumValue('renewal', 'auto') === $renewal->value)>{{ $renewal->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="status" :label="__('resale.field.status')" span="2">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($enumValue('status', 'active') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-select-field>

        <x-input-field name="purchase_unit_price" type="number" step="0.01" min="0" :label="__('resale.field.purchase_unit_price')" :value="old('purchase_unit_price', $editing ? $subscription->purchase_unit_price?->withScale(2)->getAmount() : null)" span="3" :hint="__('resale.dialog.price_hint')" />
        <x-input-field name="sale_unit_price" type="number" step="0.01" min="0" :label="__('resale.field.sale_unit_price')" :value="old('sale_unit_price', $editing ? $subscription->sale_unit_price?->withScale(2)->getAmount() : null)" span="3" />

        <x-textarea-field name="notes" :label="__('resale.field.notes')" :value="$value('notes')" span="6" rows="2" />
    </div>
</x-modal>
