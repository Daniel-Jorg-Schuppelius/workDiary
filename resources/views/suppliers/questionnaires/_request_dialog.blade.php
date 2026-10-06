{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _request_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Antworten einer Selbstauskunft und Prüfung (MVP-937). Erwartet: $request, $lines, $canManage --}}
<x-modal :title="$request->questionnaire?->name ?? __('supplier_questionnaire.title')" :eyebrow="$request->supplier?->name" icon="fact_check" tone="primary" :close-label="__('Schließen')">
    <p class="text-sm"><span class="wd-badge badge-ghost">{{ $request->status->label() }}</span>
        @if ($request->submitted_at) · {{ __('supplier_questionnaire.submitted_at', ['date' => $request->submitted_at->fdate()]) }}@endif
        @if ($request->valid_until) · {{ __('supplier_questionnaire.valid_until', ['date' => $request->valid_until->fdate()]) }}@endif
    </p>
    @if ($request->submitted_at)
        <dl class="mt-3 grid grid-cols-1 gap-2 text-sm md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            @foreach ($lines as $line)
                {{-- raw-markup-ok: Fragebogen: lange Fragen als Label, zwei gleich breite Spalten (max-content würde überlaufen) --}}
                <dt class="font-medium">{{ $line['label'] }}</dt>
                <dd class="whitespace-pre-line">{{ $line['value'] !== '' ? $line['value'] : '—' }}</dd>
            @endforeach
        </dl>
    @else
        <p class="mt-3 text-sm text-muted">{{ __('supplier_questionnaire.waiting', ['email' => $request->recipient_email, 'date' => $request->expires_at->fdate()]) }}</p>
    @endif
    @if ($request->note)<p class="mt-3 text-sm"><span class="font-medium">{{ __('supplier_questionnaire.field.note') }}:</span> {{ $request->note }}</p>@endif

    @if ($canManage && $request->status === \App\Enums\Supplier\SupplierQuestionnaireStatus::Submitted)
        <form method="POST" action="{{ route('supplier-questionnaires.requests.review', $request) }}" class="mt-4 flex flex-col gap-2">
            @csrf
            <x-textarea-field name="note" :label="__('supplier_questionnaire.field.note')" rows="2">{{ old('note') }}</x-textarea-field>
            <div class="flex justify-end gap-2">
                <x-button type="submit" tone="outline" name="decision" value="reject">{{ __('supplier_questionnaire.reject') }}</x-button>
                <x-button type="submit" name="decision" value="accept">{{ __('supplier_questionnaire.accept') }}</x-button>
            </div>
        </form>
    @endif
</x-modal>
