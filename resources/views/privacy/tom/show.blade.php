@extends('layouts.app')
@section('title', $measure->name)
@section('nav-title', $measure->name)
@section('content')
    <x-index-page :subtitle="__('Maßnahme versionieren, zuordnen und auf Wirksamkeit prüfen.')">
        <x-slot:actions>
            <x-status-badge tone="ghost" size="sm">{{ $measure->category->label() }} · {{ $measure->implementation_status->label() }}</x-status-badge>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('dataprotection.tom.index')"
                        show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        @if ($measure->currentVersion)
            <x-card class="text-sm space-y-1">
                <p><span class="font-semibold">{{ __('Gültige Version') }}:</span> v{{ $measure->currentVersion->version_no }}</p>
                <p class="whitespace-pre-line">{{ data_get($measure->currentVersion->payload, 'description') }}</p>
            </x-card>
        @endif

        {{-- Versionen --}}
        <x-card padding="p-0">
            <div class="border-b border-base-300 px-4 py-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Versionen') }}</h2>
            </div>
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Version') }}</x-table.th>
                        <x-table.th>{{ __('Notiz') }}</x-table.th>
                        <x-table.th>{{ __('Freigabe') }}</x-table.th>
                        <x-table.th></x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($versions as $v)
                    <tr>
                        <td>v{{ $v->version_no }}</td>
                        <td class="text-sm">{{ $v->note ?? '—' }}</td>
                        <td class="text-sm">{{ $v->approved_at?->format('d.m.Y') ?? __('Entwurf') }}</td>
                        <td>
                            @can('update', $measure)
                                @unless ($v->approved_at)
                                    <form method="post" action="{{ route('dataprotection.tom.approve', $measure) }}">@csrf <input type="hidden" name="version_id" value="{{ $v->id }}"><x-icon-btn icon="check" tone="primary" size="xs" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn></form>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        @can('update', $measure)
            <x-card class="space-y-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Neue Version') }}</h2>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-open-dialog="dlg-tom-version" show-label>{{ __('Version speichern') }}</x-icon-btn>
                <x-modal :embedded="false" id="dlg-tom-version" :title="__('Neue Version')"
                         icon="add" tone="primary"
                         :action="route('dataprotection.tom.version', $measure)" method="POST"
                         :submit-label="__('Version speichern')">
                    <x-form-group :legend="__('Neue Version')" icon="add" tone="primary" cols="1">
                        <x-input-field name="description" :label="__('Beschreibung')">
                            <textarea id="description" name="description" rows="2" class="textarea textarea-bordered w-full">{{ data_get($measure->currentVersion?->payload, 'description') }}</textarea>
                        </x-input-field>
                        <x-input-field name="addressed_risks" :label="__('Adressierte Risiken')">
                            <textarea id="addressed_risks" name="addressed_risks" rows="2" class="textarea textarea-bordered w-full">{{ data_get($measure->currentVersion?->payload, 'addressed_risks') }}</textarea>
                        </x-input-field>
                        <x-input-field name="note" :label="__('Änderungsnotiz')" />
                    </x-form-group>
                </x-modal>
            </x-card>
        @endcan

        {{-- Zuordnung zu Verarbeitungstätigkeiten --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Zugeordnete Verarbeitungstätigkeiten') }}</h2>
            @php $tomAssignments = $measure->assignments->whereNotNull('activity_id'); @endphp
            @if ($tomAssignments->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">link</span>' :title="__('Keine Zuordnung.')" compact />
            @else
                <ul class="text-sm space-y-1">
                    @foreach ($tomAssignments as $as)
                        <li>• {{ $as->activity?->name ?? '—' }}</li>
                    @endforeach
                </ul>
            @endif
            @can('update', $measure)
                <form method="post" action="{{ route('dataprotection.tom.assign', $measure) }}" class="flex gap-2 pt-2">
                    @csrf
                    <select name="activity_id" class="select select-sm select-bordered flex-1">
                        @foreach ($activities as $a)<option value="{{ $a->sqid }}">{{ $a->name }}</option>@endforeach
                    </select>
                    <button class="btn btn-sm">{{ __('Zuordnen') }}</button>
                </form>
            @endcan
        </x-card>

        {{-- Wirksamkeitsprüfungen --}}
        <x-card class="space-y-2">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Wirksamkeitsprüfungen') }}</h2>
            @if ($measure->reviews->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">fact_check</span>' :title="__('Noch keine Prüfung.')" compact />
            @else
                <ul class="text-sm space-y-1">
                    @foreach ($measure->reviews as $r)
                        <li class="rounded-box border border-base-300 px-3 py-2">
                            {{ $r->reviewed_at?->format('d.m.Y') }} — <span class="font-semibold">{{ $r->result->label() }}</span>
                            @if ($r->deviation) · {{ $r->deviation }} @endif
                            @if ($r->due_at) <span class="text-base-content/60">({{ __('Folgemaßnahme bis') }} {{ $r->due_at->format('d.m.Y') }})</span> @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @can('update', $measure)
                <div class="pt-2">
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-open-dialog="dlg-tom-review" show-label>{{ __('Prüfung dokumentieren') }}</x-icon-btn>
                </div>
                <x-modal :embedded="false" id="dlg-tom-review" :title="__('Wirksamkeitsprüfung erfassen')"
                         icon="fact_check" tone="primary"
                         :action="route('dataprotection.tom.review', $measure)" method="POST"
                         :submit-label="__('Prüfung dokumentieren')">
                    <x-form-group :legend="__('Wirksamkeitsprüfung')" icon="fact_check" tone="primary" cols="2">
                        <x-input-field name="result" :label="__('Ergebnis')">
                            <select id="result" name="result" class="select select-bordered w-full">
                                @foreach ($results as $res)<option value="{{ $res->value }}">{{ $res->label() }}</option>@endforeach
                            </select>
                        </x-input-field>
                        <x-input-field name="due_at" type="date" :label="__('Folgemaßnahme fällig')" />
                        <x-input-field name="deviation" :label="__('Abweichung / Folgemaßnahme')" span="2">
                            <textarea id="deviation" name="deviation" rows="2" class="textarea textarea-bordered w-full"></textarea>
                        </x-input-field>
                    </x-form-group>
                </x-modal>
            @endcan
        </x-card>

        {{-- Nachweisanhänge (Nachtrag 043b): Zertifikate/Auditberichte mit Gültig-bis. --}}
        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Nachweise') }}</h2>
            @if ($measure->attachments->isEmpty())
                <x-empty-state icon="verified" :title="__('Noch keine Nachweise hinterlegt.')" compact />
            @else
                <ul class="mt-2 space-y-1">
                    @foreach ($measure->attachments as $attachment)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 px-3 py-2 text-sm">
                            <a class="link" href="{{ route('dataprotection.attachment.download', $attachment) }}">{{ $attachment->filename }}</a>
                            <span class="inline-flex items-center gap-2">
                                @if ($attachment->valid_until)
                                    @if ($attachment->valid_until->isPast())
                                        <x-status-badge tone="error" size="xs">{{ __('abgelaufen am :date', ['date' => $attachment->valid_until->format('d.m.Y')]) }}</x-status-badge>
                                    @else
                                        <x-status-badge tone="info" size="xs">{{ __('gültig bis :date', ['date' => $attachment->valid_until->format('d.m.Y')]) }}</x-status-badge>
                                    @endif
                                @endif
                                @can('update', $measure)
                                    <form method="post" action="{{ route('dataprotection.attachment.destroy', $attachment) }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit" :label="__('Entfernen')" />
                                    </form>
                                @endcan
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @can('update', $measure)
                <form method="post" action="{{ route('dataprotection.tom.attachment.store', $measure) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="file" name="file" class="file-input file-input-bordered file-input-sm" required>
                    <label class="fieldset">
                        <span class="fieldset-label text-xs">{{ __('Gültig bis (optional)') }}</span>
                        <input type="date" name="valid_until" class="input input-bordered input-sm">
                    </label>
                    <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Nachweis hochladen') }}</x-icon-btn>
                </form>
            @endcan
        </x-card>
    </x-index-page>
@endsection
