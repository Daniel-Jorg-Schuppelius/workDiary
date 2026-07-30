@extends('layouts.app')
@section('title', __('Zuordnungs-Inbox'))
@section('nav-title', __('Zuordnungs-Inbox'))

@php
    use App\Models\IntegrationInboxItem;

    $caseLabels = [
        IntegrationInboxItem::CASE_UNMATCHED => __('Nicht zugeordnet'),
        IntegrationInboxItem::CASE_CONFLICT  => __('Feld-Konflikt'),
        IntegrationInboxItem::CASE_AMBIGUOUS => __('Mehrdeutig'),
    ];
    $statusLabels = [
        IntegrationInboxItem::STATUS_OPEN              => __('Offen'),
        IntegrationInboxItem::STATUS_RESOLVED_LINKED   => __('Zugeordnet'),
        IntegrationInboxItem::STATUS_RESOLVED_CREATED  => __('Neu angelegt'),
        IntegrationInboxItem::STATUS_RESOLVED_LOCAL    => __('Lokal behalten'),
        IntegrationInboxItem::STATUS_RESOLVED_REMOTE   => __('Remote übernommen'),
        IntegrationInboxItem::STATUS_DISMISSED         => __('Verworfen'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Eingegangene Importe, die nicht automatisch zugeordnet werden konnten. Pro Eintrag entscheidest du: einem bestehenden Datensatz zuordnen, neu anlegen oder verwerfen — nichts wird blind angelegt.')">
    <x-slot:actions>
        <a href="{{ route('admin.integration.mappings.index') }}" class="btn btn-sm btn-outline">{{ __('Zuordnungen verwalten') }}</a>
        <form method="GET" action="{{ route('admin.integration.inbox') }}" class="flex flex-nowrap items-center gap-2">
            <select name="status" class="select select-sm select-bordered" data-autosubmit>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
                <option value="all" @selected($filters['status'] === 'all')>{{ __('Alle') }}</option>
            </select>
            <select name="case" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($filters['case'] === 'all')>{{ __('Alle Typen') }}</option>
                @foreach ($caseLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['case'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="plugin" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($filters['plugin'] === 'all')>{{ __('Alle Quellen') }}</option>
                @foreach ($plugins as $p)
                    <option value="{{ $p }}" @selected($filters['plugin'] === $p)>{{ $p }}</option>
                @endforeach
            </select>
            <select name="target" class="select select-sm select-bordered" data-autosubmit>
                <option value="all" @selected($filters['target'] === 'all')>{{ __('Alle Entitäten') }}</option>
                @foreach ($targets as $type => $label)
                    <option value="{{ $type }}" @selected($filters['target'] === $type)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>

    @php $customerOptions = $assignTargets[\App\Models\Customer::class] ?? (reset($assignTargets) ?: []); @endphp

    @if ($errors->any())
        <div class="alert alert-error mb-4 text-sm">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $groups->isEmpty())
        <div class="mb-4 space-y-4">
            <h3 class="text-sm font-semibold text-base-content/70">{{ __('Zeit-Import-Gruppen') }}</h3>
            @foreach ($groups as $g)
                @php $form = $g['form'] ?? 'customer_project'; @endphp
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="badge badge-sm badge-info">{{ $g['plugin_id'] }}</span>
                        @if ($form === 'asset')
                            <span class="font-semibold">{{ $g['alias'] ?: $g['remote_id'] }}</span>
                            <span class="text-sm text-base-content/60">· {{ $g['provider'] }}</span>
                        @elseif ($form === 'b2b_order')
                            <span class="font-semibold">{{ __('Bestellung') }} {{ $g['order_id'] }}</span>
                            <span class="text-sm text-base-content/60">· {{ $g['customer_name'] ?? $g['buyer_name'] }}</span>
                            <span class="badge badge-sm badge-outline">{{ $g['source'] }}</span>
                        @else
                            <span class="font-semibold">{{ $g['project_name'] ?: __('(ohne Projekt)') }}</span>
                            @if ($g['client_name'] ?? null)<span class="text-sm text-base-content/60">· {{ $g['client_name'] }}</span>@endif
                            @if ($g['workspace_name'] ?? null)<span class="badge badge-sm badge-outline" title="{{ __('Toggl-Workspace') }}">{{ $g['workspace_name'] }}</span>@endif
                        @endif
                        <span class="ml-auto text-xs text-base-content/50">
                            @if ($form === 'b2b_order')
                                {{ trans_choice(':count Position|:count Positionen', $g['count'], ['count' => $g['count']]) }}@if (($g['unmatched'] ?? 0) > 0) · <span class="text-warning">{{ __(':count ohne Artikel', ['count' => $g['unmatched']]) }}</span>@endif @if ($g['total'] ?? null) · {{ $g['total'] }}@endif
                            @else
                                {{ trans_choice(':count Eintrag|:count Einträge', $g['count'], ['count' => $g['count']]) }} · {{ $g['minutes'] }} {{ __('Min') }}
                            @endif
                        </span>
                    </div>

                    {{-- Vorschau der Einträge hinter der Gruppe: was genau wird hier gebucht? --}}
                    @if (! empty($g['entries']))
                        @php $tz = \App\Support\Tz::current(); @endphp
                        <details class="mb-3">
                            <summary class="cursor-pointer text-xs font-medium text-base-content/60">{{ __('Einträge anzeigen') }}</summary>
                            <div class="mt-2 overflow-x-auto">
                                <table class="table table-xs">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Datum') }}</th>
                                            <th>{{ __('Zeit') }}</th>
                                            <th class="text-right">{{ __('Min') }}</th>
                                            <th>{{ __('Beschreibung') }}</th>
                                            <th>{{ __('Benutzer') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($g['entries'] as $e)
                                            @php
                                                $entryStart = $e['started_at'] ? \Carbon\CarbonImmutable::parse($e['started_at'])->setTimezone($tz) : null;
                                                $entryEnd = $e['ended_at'] ? \Carbon\CarbonImmutable::parse($e['ended_at'])->setTimezone($tz) : null;
                                            @endphp
                                            <tr>
                                                <td class="whitespace-nowrap">{{ $entryStart?->format('d.m.Y') ?? '—' }}</td>
                                                <td class="whitespace-nowrap">{{ $entryStart?->format('H:i') }}–{{ $entryEnd?->format('H:i') }}</td>
                                                <td class="text-right">{{ $e['minutes'] }}</td>
                                                <td>{{ $e['description'] ?? '—' }}</td>
                                                <td class="text-xs text-base-content/60">{{ $e['user_email'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if (($g['entries_more'] ?? 0) > 0)
                                    <p class="mt-1 text-xs text-base-content/50">{{ __('… und :count weitere', ['count' => $g['entries_more']]) }}</p>
                                @endif
                            </div>
                        </details>
                    @endif

                    @php $dismissFormId = 'dismiss-group-' . $loop->index; @endphp
                    @if ($form === 'asset')
                        {{-- Fernwartung: unbekanntes Gerät an ein bestehendes Asset binden --}}
                        <form method="POST" action="{{ route('admin.integration.inbox.group.book') }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="plugin" value="{{ $g['plugin_id'] }}">
                            <input type="hidden" name="group_key" value="{{ $g['group_key'] }}">
                            <select name="asset" required class="select select-sm select-bordered">
                                <option value="">{{ __('… Gerät auswählen') }}</option>
                                @foreach (($assignTargets[\App\Models\Asset::class] ?? []) as $sqid => $label)
                                    <option value="{{ $sqid }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('An Gerät binden & buchen') }}</button>
                            <a href="{{ route('admin.remote-support.pending.index') }}" class="btn btn-sm btn-ghost">{{ __('Neues Gerät / Mehrkundengerät …') }}</a>
                            <button type="submit" form="{{ $dismissFormId }}" class="btn btn-sm btn-ghost">{{ __('Gruppe verwerfen') }}</button>
                        </form>
                    @elseif ($form === 'b2b_order')
                        {{-- openTRANS-Bestellung (Feature 099): Kunde bestätigen/wählen,
                             die Buchung erzeugt den Auftrag (DiaryEntry). --}}
                        <form method="POST" action="{{ route('admin.integration.inbox.group.book') }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="plugin" value="{{ $g['plugin_id'] }}">
                            <input type="hidden" name="group_key" value="{{ $g['group_key'] }}">
                            <select name="customer" class="select select-sm select-bordered" @if (! ($g['customer_sqid'] ?? null)) required @endif>
                                <option value="">{{ ($g['customer_name'] ?? null) ? __('Zugeordnet: :name', ['name' => $g['customer_name']]) : __('… Kunde auswählen') }}</option>
                                @foreach (($assignTargets[\App\Models\Customer::class] ?? []) as $sqid => $label)
                                    <option value="{{ $sqid }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Als Auftrag buchen') }}</button>
                            <button type="submit" form="{{ $dismissFormId }}" class="btn btn-sm btn-ghost">{{ __('Gruppe verwerfen') }}</button>
                        </form>
                    @else
                    <form method="POST" action="{{ route('admin.integration.inbox.group.book') }}" class="grid gap-3 {{ $form === 'customer_project' ? 'md:grid-cols-3' : 'md:grid-cols-2' }}"
                          @if ($form === 'customer_project') data-customer-filter @endif>
                        @csrf
                        <input type="hidden" name="plugin" value="{{ $g['plugin_id'] }}">
                        <input type="hidden" name="group_key" value="{{ $g['group_key'] }}">

                        @if ($form === 'customer_project')
                        {{-- Ohne Client (leerer Client-Name) ist der Eintrag typisch ein internes
                             Firmenprojekt → „Intern" als Default. Sonst: bestehender Kunde nur bei
                             Vorschlag vorgewählt, andernfalls „neu" (Feld ist vorbefüllt). --}}
                        @php
                            $noClient = trim((string) ($g['client_name'] ?? '')) === '';
                            $suggestedCustomer = $g['suggested_customer_sqid'] ?? null;
                        @endphp
                        <fieldset class="rounded-box border border-base-300 p-3">
                            <legend class="px-1 text-xs font-semibold">{{ __('Kunde') }}</legend>
                            <label class="label cursor-pointer justify-start gap-2 py-1" data-radio-activate>
                                <input type="radio" name="customer_mode" value="existing" class="radio radio-sm" @checked(!$noClient && $suggestedCustomer !== null)>
                                <select name="customer" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('… auswählen') }}</option>
                                    @foreach ($customerOptions as $sqid => $label)
                                        <option value="{{ $sqid }}" @selected($suggestedCustomer === $sqid)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 py-1" data-radio-activate>
                                <input type="radio" name="customer_mode" value="new" class="radio radio-sm" @checked(!$noClient && $suggestedCustomer === null)>
                                <input type="text" name="new_customer_name" class="input input-sm input-bordered w-full"
                                       placeholder="{{ __('neuer Kunde') }}" value="{{ $g['client_name'] ?? '' }}">
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="radio" name="customer_mode" value="internal" class="radio radio-sm" @checked($noClient)>
                                <span class="text-sm">{{ __('Intern (ohne Kunde)') }}</span>
                            </label>
                        </fieldset>

                        {{-- Endkunden-Ebene: der Import-Client ist ein Kunde des Kunden
                             (Fremdkunde) — Projekte hängen dann an der Firma und verweisen
                             auf ihren Endkunden. --}}
                        @php $suggestedForeign = $g['suggested_foreign_sqid'] ?? null; @endphp
                        <fieldset class="rounded-box border border-base-300 p-3">
                            <legend class="px-1 text-xs font-semibold">{{ __('Fremdkunde (Endkunde, optional)') }}</legend>
                            {{-- Wird per JS gefüllt, sobald der gewählte Kunde Endkunden hat. --}}
                            <p class="text-xs text-info" data-foreign-hint hidden
                               data-hint-template="{{ __('Endkunden vorhanden: :count') }}"></p>
                            <label class="label cursor-pointer justify-start gap-2 py-1">
                                <input type="radio" name="foreign_mode" value="none" class="radio radio-sm" @checked($suggestedForeign === null)>
                                <span class="text-sm">{{ __('Kein Fremdkunde') }}</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 py-1" data-radio-activate>
                                <input type="radio" name="foreign_mode" value="existing" class="radio radio-sm" @checked($suggestedForeign !== null)>
                                <select name="foreign_customer" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('… auswählen') }}</option>
                                    @foreach ($foreignCustomers as $fcGroup)
                                        <optgroup label="{{ $fcGroup['label'] }}" data-customer="{{ $fcGroup['customer_sqid'] }}">
                                            @foreach ($fcGroup['foreigns'] as $fc)
                                                <option value="{{ $fc['sqid'] }}" @selected($suggestedForeign === $fc['sqid'])>{{ $fc['name'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 py-1" data-radio-activate>
                                <input type="radio" name="foreign_mode" value="new" class="radio radio-sm">
                                <input type="text" name="new_foreign_customer_name" class="input input-sm input-bordered w-full"
                                       placeholder="{{ __('neuer Fremdkunde') }}" value="{{ $g['client_name'] ?? '' }}">
                            </label>
                        </fieldset>
                        @endif

                        {{-- Bestehendes Projekt nur bei Vorschlag vorgewählt — sonst „neu"
                             (Feld ist mit dem Import-Projektnamen vorbefüllt). --}}
                        @php $suggestedProject = $g['suggested_project_sqid'] ?? null; @endphp
                        <fieldset class="rounded-box border border-base-300 p-3 @if (($g['form'] ?? '') !== 'customer_project') md:col-span-2 @endif">
                            <legend class="px-1 text-xs font-semibold">{{ __('Projekt') }}</legend>
                            <label class="label cursor-pointer justify-start gap-2 py-1" data-radio-activate>
                                <input type="radio" name="project_mode" value="existing" class="radio radio-sm" @checked($suggestedProject !== null)>
                                <select name="project" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('… auswählen') }}</option>
                                    @foreach ($projects as $pGroup)
                                        <optgroup label="{{ $pGroup['label'] }}" data-customer="{{ $pGroup['customer_sqid'] }}">
                                            @foreach ($pGroup['projects'] as $p)
                                                <option value="{{ $p['sqid'] }}" data-foreign="{{ $p['foreign_sqid'] }}" @selected($suggestedProject === $p['sqid'])>{{ $p['name'] }}@if ($p['foreign_name'] !== null) — {{ $p['foreign_name'] }}@endif</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 py-1" data-radio-activate>
                                <input type="radio" name="project_mode" value="new" class="radio radio-sm" @checked($suggestedProject === null)>
                                <input type="text" name="new_project_name" class="input input-sm input-bordered w-full"
                                       placeholder="{{ __('neues Projekt') }}" value="{{ $g['project_name'] }}">
                            </label>
                        </fieldset>

                        <div class="md:col-span-full flex justify-end gap-2">
                            <button type="submit" form="{{ $dismissFormId }}" class="btn btn-sm btn-ghost">{{ __('Gruppe verwerfen') }}</button>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Gruppe buchen') }}</button>
                        </div>
                    </form>
                    @endif
                    {{-- Unsichtbares Verwerfen-Form; die Buttons in den Aktionszeilen
                         referenzieren es per form-Attribut. --}}
                    <form method="POST" action="{{ route('admin.integration.inbox.group.dismiss') }}" id="{{ $dismissFormId }}" class="hidden"
                          data-confirm-dialog data-confirm-message="{{ __('Diese Gruppe verwerfen?') }}">
                        @csrf
                        <input type="hidden" name="plugin" value="{{ $g['plugin_id'] }}">
                        <input type="hidden" name="group_key" value="{{ $g['group_key'] }}">
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    @if ($items->isEmpty())
        @if ($groups->isEmpty())
            <x-empty-state icon="inbox" :title="__('Keine Einträge im gewählten Filter.')" tone="success" framed />
        @endif
    @else
        <div class="space-y-4">
            @foreach ($items as $item)
                @php
                    $diff = $item->diff_fields ?? [];
                    $candidates = $item->candidate_ids ?? [];
                    $targetLabel = $targets[$item->target_type] ?? class_basename($item->target_type);
                @endphp
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        @php
                            $caseBadge = match ($item->case_type) {
                                IntegrationInboxItem::CASE_CONFLICT => 'badge-warning',
                                IntegrationInboxItem::CASE_AMBIGUOUS => 'badge-info',
                                default => 'badge-ghost',
                            };
                        @endphp
                        <span class="badge badge-sm {{ $caseBadge }}">{{ $caseLabels[$item->case_type] ?? $item->case_type }}</span>
                        <span class="badge badge-sm badge-outline">{{ $item->plugin_id }}</span>
                        <span class="badge badge-sm badge-outline">{{ $targetLabel }}</span>
                        @unless ($item->isOpen())
                            <span class="badge badge-sm badge-success">{{ $statusLabels[$item->status] ?? $item->status }}</span>
                        @endunless
                        <span class="ml-auto text-xs text-base-content/50">{{ optional($item->created_at)->format('d.m.Y H:i') }}</span>
                    </div>

                    <div class="mb-3">
                        <div class="font-semibold">{{ $item->display_title ?: __('(ohne Titel)') }}</div>
                        @if ($item->display_subtitle)
                            <div class="text-sm text-base-content/60">{{ $item->display_subtitle }}</div>
                        @endif
                    </div>

                    @if ($item->case_type === IntegrationInboxItem::CASE_CONFLICT && $diff !== [])
                        <div class="mb-3 grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <div class="mb-1 font-semibold">{{ __('Lokal') }}</div>
                                <pre class="whitespace-pre-wrap rounded bg-base-200/50 p-2">{{ json_encode(array_intersect_key($item->local_snapshot ?? [], array_flip($diff)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div>
                                <div class="mb-1 font-semibold">{{ __('Remote') }}</div>
                                <pre class="whitespace-pre-wrap rounded bg-base-200/50 p-2">{{ json_encode(array_intersect_key($item->mapped_snapshot ?? [], array_flip($diff)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    @endif

                    @if ($item->isOpen())
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if ($item->plugin_id === \App\Services\Mail\MailIntakeService::PLUGIN_ID)
                                {{-- E-Mail-Eingang (Feature 056): als Kommunikationsnotiz beim Kunden
                                     buchen. Bewusst kein „Neu anlegen" — eine Mail erzeugt keinen Kunden.
                                     Kunde leer = automatisch erkannter Absender-Kunde. --}}
                                <form method="POST" action="{{ route('admin.mail.inbox.book') }}" class="join">
                                    @csrf
                                    <input type="hidden" name="item" value="{{ $item->sqid }}">
                                    <select name="customer" class="join-item select select-sm select-bordered">
                                        <option value="">{{ __('mail.inbox.book_customer_placeholder') }}</option>
                                        @foreach (($assignTargets[\App\Models\Customer::class] ?? []) as $sqid => $label)
                                            <option value="{{ $sqid }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="join-item btn btn-sm btn-primary">{{ __('mail.inbox.book_action') }}</button>
                                </form>
                                {{-- Mail → Service-Ticket (MVP-343): Queue des Eingangspostfachs,
                                     Kunde leer = automatisch erkannter Absender-Kunde. --}}
                                @feature('module.helpdesk')
                                    <form method="POST" action="{{ route('admin.mail.inbox.book-ticket') }}">
                                        @csrf
                                        <input type="hidden" name="item" value="{{ $item->sqid }}">
                                        <button class="btn btn-sm btn-outline">{{ __('mail.inbox.book_ticket_action') }}</button>
                                    </form>
                                @endfeature
                                {{-- Anhang-Übernahme ins DMS (MVP-343): nur wenn beim Intake
                                     Anhänge persistiert wurden; idempotent je Message-ID+Index. --}}
                                @php
                                    $mailHasStoredAttachments = collect((array) (($item->remote_snapshot ?? [])['attachments'] ?? []))
                                        ->contains(fn ($a) => is_array($a) && ($a['stored'] ?? false) === true);
                                @endphp
                                @if ($mailHasStoredAttachments)
                                    <form method="POST" action="{{ route('admin.mail.inbox.import-dms') }}">
                                        @csrf
                                        <input type="hidden" name="item" value="{{ $item->sqid }}">
                                        <button class="btn btn-sm btn-outline">{{ __('mail.dms.action') }}</button>
                                    </form>
                                @endif
                            @elseif (in_array($item->plugin_id, [\App\Plugins\Webdav\WebdavPlugin::ID, \App\Plugins\Sharepoint\SharepointPlugin::ID], true) && $item->case_type === IntegrationInboxItem::CASE_CONFLICT)
                                {{-- Ablage-Spiegelkonflikt WebDAV/SharePoint (Feature 058 Rang 18 / MVP-330): Datei-Divergenz, kein Feld-Diff. --}}
                                <form method="POST" action="{{ route('admin.' . $item->plugin_id . '.conflict.overwrite', $item) }}"
                                      data-confirm-dialog data-confirm-message="{{ __($item->plugin_id . '.conflict.confirm.overwrite') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">{{ __($item->plugin_id . '.conflict.action.overwrite') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.' . $item->plugin_id . '.conflict.import', $item) }}"
                                      data-confirm-dialog data-confirm-message="{{ __($item->plugin_id . '.conflict.confirm.import') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline">{{ __($item->plugin_id . '.conflict.action.import') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.' . $item->plugin_id . '.conflict.detach', $item) }}"
                                      data-confirm-dialog data-confirm-message="{{ __($item->plugin_id . '.conflict.confirm.detach') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-ghost">{{ __($item->plugin_id . '.conflict.action.detach') }}</button>
                                </form>
                            @elseif ($item->case_type === IntegrationInboxItem::CASE_CONFLICT)
                                <form method="POST" action="{{ route('admin.integration.inbox.accept-remote', $item) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">{{ __('Remote übernehmen') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.integration.inbox.keep-local', $item) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline">{{ __('Lokal behalten') }}</button>
                                </form>
                            @else
                                @foreach ($candidates as $cand)
                                    <form method="POST" action="{{ route('admin.integration.inbox.assign', $item) }}">
                                        @csrf
                                        <input type="hidden" name="target" value="{{ $cand['sqid'] ?? '' }}">
                                        <button class="btn btn-sm btn-primary">{{ __('Zuordnen:') }} {{ $cand['label'] ?? '?' }}</button>
                                    </form>
                                @endforeach

                                @if (($assignTargets[$item->target_type] ?? []) !== [])
                                    <form method="POST" action="{{ route('admin.integration.inbox.assign', $item) }}" class="join">
                                        @csrf
                                        <select name="target" required class="join-item select select-sm select-bordered">
                                            <option value="">{{ __('… bestehendem zuordnen') }}</option>
                                            @foreach ($assignTargets[$item->target_type] as $sqid => $label)
                                                <option value="{{ $sqid }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="join-item btn btn-sm">{{ __('Zuordnen') }}</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.integration.inbox.create', $item) }}"
                                      data-confirm-dialog data-confirm-message="{{ __('Als neuen Datensatz anlegen?') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline">{{ __('Neu anlegen') }}</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.integration.inbox.dismiss', $item) }}">
                                @csrf
                                <button class="btn btn-sm btn-ghost">{{ __('Verwerfen') }}</button>
                            </form>
                        </div>
                    @else
                        <div class="text-right text-xs text-base-content/50">
                            {{ optional($item->resolved_at)->format('d.m.Y H:i') }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <x-pagination :paginator="$items" standing />
    @endif
</x-index-page>
@endsection
