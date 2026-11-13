{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _split_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Sammel-PDF aufteilen (Feature 208, MVP-1662). Startseiten umschalten verschiebt die Grenzen; Seite 1 beginnt immer. --}}
@php
    $signalLabels = [
        'page_one' => __('incoming_pdf.split.signal.page_one'),
        'continued' => __('incoming_pdf.split.signal.continued'),
        'new_number' => __('incoming_pdf.split.signal.new_number'),
        'same_number' => __('incoming_pdf.split.signal.same_number'),
        'new_sender' => __('incoming_pdf.split.signal.new_sender'),
        'after_total' => __('incoming_pdf.split.signal.after_total'),
        'new_letterhead' => __('incoming_pdf.split.signal.new_letterhead'),
    ];
    $selected = array_map(intval(...), (array) old('starts', $starts));
    $pageReasons = [];
    for ($page = 1; $page <= $pages; $page++) {
        $pageReasons[$page] = array_values(array_filter(array_map(
            static fn (mixed $signal): ?string => is_string($signal) ? ($signalLabels[$signal] ?? null) : null,
            (array) ($signals[(string) $page] ?? []),
        )));
    }
@endphp
<x-modal
    :title="__('incoming_pdf.split.title')"
    :eyebrow="$incoming->invoice_number ?? $incoming->seller_name ?? $incoming->sender_email"
    icon="call_split"
    size="wide"
    :action="$blocker === null ? route('finance.incoming-invoices.split', $incoming) : null"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('incoming_pdf.split.action.split')"
>
    @if ($blocker !== null)
        <div class="alert alert-warning alert-soft text-sm" role="status">
            <x-icon name="info" />
            <span>{{ __('incoming_pdf.split.blocked.' . $blocker) }}</span>
        </div>
    @else
        <p class="text-sm text-muted">{{ __('incoming_pdf.split.dialog.intro') }}</p>
        @unless ($images)
            <div class="alert alert-info alert-soft text-sm" role="status">
                <x-icon name="image_not_supported" />
                <span>{{ __('incoming_pdf.split.dialog.images_missing') }}</span>
            </div>
        @endunless
        <input type="hidden" name="starts[]" value="1">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @for ($page = 1; $page <= $pages; $page++)
                <figure class="flex flex-col gap-1">
                    @if ($images)
                        <img src="{{ route('finance.incoming-invoices.split.page', [$incoming, $page]) }}"
                             alt="{{ __('incoming_pdf.split.dialog.page', ['page' => $page]) }}"
                             loading="lazy" class="w-full rounded border border-base-300 bg-base-100">
                    @endif
                    <figcaption class="text-xs">
                        <span class="font-medium">{{ __('incoming_pdf.split.dialog.page', ['page' => $page]) }}</span>
                        @if ($pageReasons[$page] !== [])
                            <span class="text-muted">· {{ implode(', ', $pageReasons[$page]) }}</span>
                        @endif
                    </figcaption>
                    @if ($page === 1)
                        <span class="text-xs text-muted">{{ __('incoming_pdf.split.dialog.first_page') }}</span>
                    @else
                        <x-checkbox-field name="starts[]" :id="'split-start-' . $page" :value="(string) $page"
                                          :checked="in_array($page, $selected, true)" :toggle="false" :with-hidden="false"
                                          :label="__('incoming_pdf.split.dialog.starts_here')" />
                    @endif
                </figure>
            @endfor
        </div>
    @endif
</x-modal>
