{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _revision.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Eine Vertragsfassung mit Dateien, Parteien, Links, Nachweisen und Aktionen.
  Erwartet: $contract, $revision, $canSign, $canReview, $supersedable, $expanded.
--}}
@php
    use App\Enums\Contract\{SignatureParty, SigningRevisionStatus};
    $st = $revision->status;
    $sq = $revision->sqid;
@endphp
<details class="rounded-box border border-base-300 bg-base-100" @if ($expanded) open @endif>
    <summary class="flex cursor-pointer flex-wrap items-center gap-2 px-4 py-3 text-sm">
        <span class="font-semibold">{{ $revision->label() }}</span>
        <x-status-badge size="sm" :tone="$st->tone()" :label="$st->label()" />
        @if ($revision->isReleasedToCustomer())
            <span class="badge badge-outline badge-sm">{{ __('contract-signing.revision.portal_released') }}</span>
        @endif
        <span class="text-muted">
            @if ($revision->completed_at)
                {{ __('contract-signing.revision.completed_at', ['at' => $revision->completed_at->fdatetime()]) }}
            @elseif ($revision->prepared_at)
                {{ __('contract-signing.revision.prepared_at', ['at' => $revision->prepared_at->fdatetime()]) }}
            @else
                {{ __('contract-signing.revision.created_at', ['at' => $revision->created_at?->fdatetime()]) }}
            @endif
        </span>
        @if ($revision->effective_on)
            <span class="text-muted">· {{ __('contract-signing.revision.effective_on', ['date' => $revision->effective_on->fdate()]) }}</span>
        @endif
    </summary>

    <div class="space-y-4 border-t border-base-300 px-4 py-3 text-sm">
        @if ($revision->predecessor || $revision->supersededBy || $revision->withdrawal_reason)
            <div class="text-xs text-muted">
                @if ($revision->predecessor)
                    {{ __('contract-signing.revision.replaces', ['no' => $revision->predecessor->revision_no]) }}
                @endif
                @if ($revision->supersededBy)
                    {{ __('contract-signing.revision.replaced_by', ['no' => $revision->supersededBy->revision_no, 'at' => $revision->superseded_at?->fdatetime()]) }}
                @endif
                @if ($revision->withdrawal_reason)
                    {{ __('contract-signing.revision.withdrawn_reason', ['reason' => $revision->withdrawal_reason]) }}
                @endif
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <h4 class="mb-1 font-medium">{{ __('contract-signing.revision.files') }}</h4>
                <ul class="divide-y divide-base-300 rounded-box border border-base-300">
                    @foreach ($revision->manifestItems as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <span class="min-w-0">
                                <span class="badge badge-ghost badge-xs">{{ $item->roleLabel() }}</span>
                                <span class="font-medium">{{ $item->original_name }}</span>
                                <span class="block font-mono text-[11px] text-muted" title="SHA-256">{{ $item->sha256 }}</span>
                            </span>
                            <x-icon-btn icon="download" tone="outline" size="xs"
                                        :href="route('contracts.signing.file', ['revision' => $revision, 'item' => $item->sort])"
                                        :label="__('contract-signing.action.download_file')" />
                        </li>
                    @endforeach
                </ul>
                @if ($revision->manifest_hash)
                    <p class="mt-1 font-mono text-[11px] text-muted" title="{{ __('contract-signing.revision.manifest_hash') }}">{{ __('contract-signing.revision.manifest_hash') }}: {{ $revision->manifest_hash }}</p>
                @endif
            </div>
            <div>
                <h4 class="mb-1 font-medium">{{ __('contract-signing.revision.declaration') }}</h4>
                <p class="whitespace-pre-line rounded-box bg-base-200 p-3 text-xs">{{ $revision->declaration_text }}</p>
                @if ($revision->controller_party)
                    <p class="mt-2 text-xs text-muted">{{ __('contract-signing.revision.controller', ['party' => $revision->controller_party->label()]) }}</p>
                @endif
                @if ($revision->review_on)
                    <p class="mt-1 text-xs text-muted">{{ __('contract-signing.revision.review_on', ['date' => $revision->review_on->fdate()]) }}</p>
                @endif
            </div>
        </div>

        <div>
            <h4 class="mb-1 font-medium">{{ __('contract-signing.revision.parties') }}</h4>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('contract-signing.field.party') }}</th>
                        <th>{{ __('contract-signing.field.signer') }}</th>
                        <th>{{ __('contract-signing.field.status') }}</th>
                        <th>{{ __('contract-signing.field.link') }}</th>
                        <th class="text-right">{{ __('contract-signing.field.actions') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($revision->requests as $req)
                    @php
                        $link = $req->activeLink();
                        $pending = $req->pendingEvidence();
                        $mayAct = $canSign && $st->acceptsSignatures() && $req->status->acceptsSubmission();
                    @endphp
                    <tr>
                        <td class="align-top">
                            {{ $req->party->label() }}
                            @unless ($req->required)
                                <span class="block text-xs text-muted">{{ __('contract-signing.request.waived', ['reason' => $req->waiver_reason]) }}</span>
                            @endunless
                        </td>
                        <td class="align-top">
                            <span class="font-medium">{{ $req->signer_name }}</span>
                            @if ($req->signer_function)<span class="block text-xs text-muted">{{ $req->signer_function }}</span>@endif
                            @if ($req->signer_email)<span class="block text-xs text-muted">{{ $req->signer_email }}</span>@endif
                        </td>
                        <td class="align-top">
                            <x-status-badge size="sm" :tone="$req->status->tone()" :label="$req->status->label()" />
                            @if ($req->fulfilled_at)
                                <span class="block text-xs text-muted">{{ $req->fulfilled_at->fdatetime() }}</span>
                            @endif
                        </td>
                        <td class="align-top text-xs">
                            @if ($link)
                                <span class="badge badge-info badge-outline badge-xs">{{ __('contract-signing.link.state.' . $link->stateKey()) }}</span>
                                <span class="block text-muted">{{ __('contract-signing.link.expires', ['at' => $link->expires_at->fdatetime()]) }}</span>
                                @if ($link->sent_at)
                                    <span class="block text-muted">{{ __('contract-signing.link.sent', ['to' => $link->sent_to, 'at' => $link->sent_at->fdatetime()]) }}</span>
                                @elseif ($link->send_error)
                                    <span class="block text-error">{{ __('contract-signing.link.send_failed', ['to' => $link->sent_to]) }}</span>
                                @endif
                                @if ($link->opened_at)
                                    <span class="block text-muted">{{ __('contract-signing.link.opened', ['at' => $link->opened_at->fdatetime()]) }}</span>
                                @endif
                                @if ($canSign)
                                    <form method="POST" action="{{ route('contracts.signing.links.revoke', $link) }}" class="mt-1"
                                          data-confirm-dialog data-confirm-message="{{ __('contract-signing.confirm.revoke_link') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('contract-signing.action.revoke_link') }}</button>
                                    </form>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="align-top text-right">
                            @if ($mayAct)
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if ($req->party === SignatureParty::Customer)
                                        <form method="POST" action="{{ route('contracts.signing.requests.link', $req) }}">@csrf
                                            <button type="submit" class="btn btn-xs">{{ __('contract-signing.action.issue_link') }}</button>
                                        </form>
                                        @if ($req->signer_email)
                                            <form method="POST" action="{{ route('contracts.signing.requests.send', $req) }}">@csrf
                                                <button type="submit" class="btn btn-xs btn-primary">{{ __('contract-signing.action.send_link') }}</button>
                                            </form>
                                        @endif
                                    @else
                                        <x-icon-btn icon="draw" tone="primary" size="xs" data-entry-modal-trigger
                                                    :href="route('contracts.signing.requests.countersign', $req)"
                                                    show-label>{{ __('contract-signing.action.countersign') }}</x-icon-btn>
                                    @endif
                                    <x-icon-btn icon="upload_file" tone="outline" size="xs" data-entry-modal-trigger
                                                :href="route('contracts.signing.requests.upload', $req)"
                                                show-label>{{ __('contract-signing.action.upload_evidence') }}</x-icon-btn>
                                </div>
                            @endif
                            @if ($pending && $canReview)
                                <form method="POST" action="{{ route('contracts.signing.evidences.review', $pending) }}" class="mt-2 grid gap-2 rounded-box border border-info/40 p-2 text-left">
                                    @csrf
                                    <span class="text-xs font-medium">{{ __('contract-signing.review.heading', ['name' => $pending->signer_name]) }}</span>
                                    <label class="label cursor-pointer justify-start gap-2 py-0">
                                        <input type="radio" name="review_decision" value="accept" class="radio radio-xs" required>
                                        <span class="label-text text-xs">{{ __('contract-signing.review.accept') }}</span>
                                    </label>
                                    <label class="label cursor-pointer justify-start gap-2 py-0">
                                        <input type="radio" name="review_decision" value="reject" class="radio radio-xs">
                                        <span class="label-text text-xs">{{ __('contract-signing.review.reject') }}</span>
                                    </label>
                                    <x-textarea-field name="review_note" id="review-note-{{ $pending->sqid }}" :label="__('contract-signing.field.review_note')" rows="2" />
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('contract-signing.action.decide') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </div>

        @if ($revision->requests->flatMap->evidences->isNotEmpty())
            <div>
                <h4 class="mb-1 font-medium">{{ __('contract-signing.revision.evidences') }}</h4>
                <ul class="divide-y divide-base-300 rounded-box border border-base-300 text-xs">
                    @foreach ($revision->requests as $req)
                        @foreach ($req->evidences as $ev)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                <span class="min-w-0">
                                    <span class="font-medium">{{ $req->party->label() }}</span> · {{ $ev->method->label() }} ·
                                    {{ $ev->signer_name }}@if ($ev->signer_function) ({{ $ev->signer_function }})@endif ·
                                    {{ $ev->signed_at->fdatetime() }}
                                    @if ($ev->stated_signed_on)
                                        · {{ __('contract-signing.evidence.stated_signed_on', ['date' => $ev->stated_signed_on->fdate()]) }}
                                    @endif
                                    <span class="block text-muted">
                                        {{ __('contract-signing.evidence.via.' . $ev->submitted_via) }}
                                        @if ($ev->recorder) · {{ __('contract-signing.evidence.recorded_by', ['name' => $ev->recorder->name]) }} @endif
                                        @if ($ev->review_status)
                                            · <x-status-badge size="xs" :tone="$ev->review_status->tone()" :label="$ev->review_status->label()" />
                                            @if ($ev->reviewer) {{ __('contract-signing.evidence.reviewed_by', ['name' => $ev->reviewer->name, 'at' => $ev->reviewed_at?->fdatetime()]) }} @endif
                                            @if ($ev->review_note) — {{ $ev->review_note }} @endif
                                        @endif
                                    </span>
                                    @if ($ev->file_hash)
                                        <span class="block font-mono text-[11px] text-muted" title="SHA-256">{{ $ev->file_hash }}</span>
                                    @endif
                                </span>
                                @if ($ev->hasFile())
                                    <x-icon-btn icon="download" tone="outline" size="xs"
                                                :href="route('contracts.signing.evidences.file', $ev)"
                                                :label="__('contract-signing.action.download_evidence')" />
                                @endif
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-start gap-2 border-t border-base-300 pt-3">
            @if ($st === SigningRevisionStatus::Draft && $canSign)
                <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger
                            :href="route('contracts.signing.edit', $revision)" show-label>{{ __('contract-signing.action.edit_revision') }}</x-icon-btn>
                <form method="POST" action="{{ route('contracts.signing.prepare', $revision) }}">@csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('contract-signing.action.prepare') }}</button>
                </form>
            @endif
            @if ($st->isOpen() && $canSign)
                <details class="inline-block text-left">
                    <summary class="btn btn-sm btn-ghost text-error">{{ __('contract-signing.action.withdraw') }}</summary>
                    <form method="POST" action="{{ route('contracts.signing.withdraw', $revision) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
                        @csrf
                        <x-input-field name="reason" id="withdraw-reason-{{ $sq }}" :label="__('contract-signing.field.withdrawal_reason')" required />
                        <button type="submit" class="btn btn-sm btn-error">{{ __('contract-signing.action.withdraw') }}</button>
                    </form>
                </details>
            @endif
            @if ($st->isSigned())
                <x-icon-btn icon="verified" tone="outline" size="sm" :href="route('contracts.signing.certificate', $revision)" show-label>{{ __('contract-signing.action.certificate') }}</x-icon-btn>
                <x-icon-btn icon="folder_zip" tone="outline" size="sm" :href="route('contracts.signing.package', $revision)" show-label>{{ __('contract-signing.action.package') }}</x-icon-btn>
            @endif
            @if ($st === SigningRevisionStatus::Signed && $canSign)
                <details class="inline-block text-left">
                    <summary class="btn btn-sm btn-ghost">{{ __('contract-signing.action.download_link') }}</summary>
                    <form method="POST" action="{{ route('contracts.signing.download-link', $revision) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
                        @csrf
                        <x-input-field name="signer_email" id="download-email-{{ $sq }}" type="email" :label="__('contract-signing.field.download_recipient')" :value="$revision->customerRequest?->signer_email" :hint="__('contract-signing.hint.download_link')" />
                        <button type="submit" class="btn btn-sm">{{ __('contract-signing.action.download_link_go') }}</button>
                    </form>
                </details>
                @if ($revision->customer_visible_at)
                    <form method="POST" action="{{ route('contracts.signing.portal-revoke', $revision) }}">@csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('contract-signing.action.portal_revoke') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('contracts.signing.portal-release', $revision) }}">@csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('contract-signing.action.portal_release') }}</button>
                    </form>
                @endif
                @if ($supersedable->isNotEmpty() && $revision->predecessor_id === null)
                    <details class="inline-block text-left">
                        <summary class="btn btn-sm btn-ghost">{{ __('contract-signing.action.supersede') }}</summary>
                        <form method="POST" action="{{ route('contracts.signing.supersede', $revision) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3"
                              data-confirm-dialog data-confirm-message="{{ __('contract-signing.confirm.supersede') }}">
                            @csrf
                            <x-select-field name="predecessor_id" id="predecessor-{{ $sq }}" :label="__('contract-signing.field.predecessor')" required>
                                @foreach ($supersedable as $candidate)
                                    <option value="{{ $candidate->sqid }}">{{ $candidate->label() }} — {{ $candidate->completed_at?->fdate() }}</option>
                                @endforeach
                            </x-select-field>
                            <x-input-field name="effective_on" id="effective-on-{{ $sq }}" type="date" :label="__('contract-signing.field.effective_on')" :value="now()->toDateString()" required />
                            <button type="submit" class="btn btn-sm">{{ __('contract-signing.action.supersede') }}</button>
                        </form>
                    </details>
                @endif
            @endif
        </div>
    </div>
</details>
