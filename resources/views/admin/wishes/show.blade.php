{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Weitergegebener Wunsch in der Plattformsicht (Feature 206, MVP-1659/1677). Ohne Personenbezug: weder Ursprungstext noch Namen.
     Variablen: $wish, $members (gebündelte), $votes (Wunsch-ID → Stimmen), $bundleVotes, $organizations (Wunsch-ID → Name),
     $leads (mögliche führende Wünsche), $lead (führender Wunsch oder null), $vendorEnabled, $vendorText, $preview (Mail-Vorschau oder null). --}}

@extends('layouts.app')

@section('title', __('wishes.platform.show_title', ['kind' => $wish->kind->label()]))
@section('nav-title', __('wishes.platform.title'))

@section('content')
@php
    $platformStatus = $wish->platform_status ?? \App\Enums\Wishes\AppWishStatus::New;
    $selectedStatus = (string) old('platform_status', $platformStatus->value);
@endphp
<x-index-page :subtitle="__('wishes.platform.show_subtitle')" back-route="admin.wishes.index" :back-label="__('wishes.platform.back')">
    <x-slot:badges>
        <x-status-badge :tone="$platformStatus->tone()">{{ $platformStatus->label() }}</x-status-badge>
        @if ($wish->isSentToVendor())
            <x-status-badge tone="info" icon="forward_to_inbox">{{ __('wishes.vendor.sent_at', ['date' => $wish->vendor_sent_at?->fdatetime()]) }}</x-status-badge>
        @endif
    </x-slot:badges>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('wishes.platform.section.wish')" icon="lightbulb">
            <x-detail-grid layout="cells" :cols="1" small-labels>
                <x-detail-grid.row :label="__('wishes.field.kind')">{{ $wish->kind->label() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('wishes.field.forwarded_body')" class="whitespace-pre-line">{{ $wish->forwarded_body }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('wishes.field.page')"><span class="font-mono text-xs">{{ $wish->route_name ?? '—' }}</span></x-detail-grid.row>
                <x-detail-grid.row :label="__('wishes.field.app_version')">{{ $wish->app_version ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('wishes.platform.organization')">{{ $organizations[(int) $wish->id] ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('wishes.field.votes')">
                    {{ $votes[(int) $wish->id] ?? 0 }}
                    @if ($members->isNotEmpty())
                        <span class="text-muted">· {{ __('wishes.platform.bundle_votes', ['count' => $bundleVotes]) }}</span>
                    @endif
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('wishes.field.forwarded_at')">{{ $wish->forwarded_at?->fdatetime() }}</x-detail-grid.row>
            </x-detail-grid>
            <p class="mt-3 text-xs text-muted">{{ __('wishes.platform.privacy_note') }}</p>
        </x-card>

        <x-card :title="__('wishes.platform.section.answer')" icon="reply">
            @if ($lead !== null)
                <div role="note" class="alert alert-info text-sm">
                    <x-icon name="link" />
                    <span>{{ __('wishes.platform.follows_lead') }} <a href="{{ route('admin.wishes.show', $lead) }}" class="link">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($lead->forwarded_body, 80) }}</a></span>
                </div>
                @if ($wish->platform_answer !== null)
                    <p class="mt-3 text-sm whitespace-pre-line">{{ $wish->platform_answer }}</p>
                @endif
            @else
                <form method="POST" action="{{ route('admin.wishes.update', $wish) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <x-select-field name="platform_status" id="pw-status" :label="__('wishes.platform.status')">
                        <option value="{{ $platformStatus->value }}" @selected($selectedStatus === $platformStatus->value)>{{ $platformStatus->label() }}</option>
                        @foreach ($platformStatus->allowedTransitions() as $status)
                            <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-select-field>
                    <x-textarea-field name="platform_answer" id="pw-answer" :label="__('wishes.platform.answer')" rows="4"
                                      :value="old('platform_answer', $wish->platform_answer)" :hint="__('wishes.platform.answer_hint')" />
                    <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('wishes.dialog.save') }}</x-button>
                </form>
            @endif
        </x-card>

        <x-card :title="__('wishes.platform.section.bundle')" icon="stacks">
            @if ($members->isNotEmpty())
                <ul class="divide-y divide-base-200 text-sm">
                    @foreach ($members as $member)
                        <li class="py-1.5">
                            <a href="{{ route('admin.wishes.show', $member) }}" class="link link-hover">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($member->forwarded_body, 120) }}</a>
                            <span class="text-xs text-muted">· {{ $organizations[(int) $member->id] ?? '—' }} · {{ trans_choice('wishes.platform.votes_count', $votes[(int) $member->id] ?? 0, ['count' => $votes[(int) $member->id] ?? 0]) }}</span>
                        </li>
                    @endforeach
                </ul>
            @elseif ($lead !== null)
                <x-action-form :action="route('admin.wishes.bundle', $wish)" method="PUT">
                    <input type="hidden" name="lead" value="">
                    <x-button type="submit" tone="ghost" size="sm" icon="link_off">{{ __('wishes.platform.unbundle') }}</x-button>
                </x-action-form>
            @elseif ($leads->isEmpty())
                <x-empty-state compact icon="stacks" :title="__('wishes.platform.no_leads')" />
            @else
                <form method="POST" action="{{ route('admin.wishes.bundle', $wish) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <x-select-field name="lead" id="pw-lead" :label="__('wishes.platform.bundle_with')" required>
                        <option value="">{{ __('wishes.platform.choose_lead') }}</option>
                        @foreach ($leads as $candidate)
                            <option value="{{ $candidate->sqid }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($candidate->forwarded_body, 80) }}</option>
                        @endforeach
                    </x-select-field>
                    <p class="text-xs text-muted">{{ __('wishes.platform.bundle_hint') }}</p>
                    <x-button type="submit" tone="primary" size="sm" icon="link">{{ __('wishes.platform.bundle') }}</x-button>
                </form>
            @endif
        </x-card>

        {{-- Herstellerkanal (MVP-1677): nur mit Opt-in des Betreibers; Vorschau genau dessen, was hinausgeht. --}}
        <x-card :title="__('wishes.vendor.title')" icon="forward_to_inbox">
            @if (! $vendorEnabled && ! $wish->isSentToVendor())
                <p class="text-sm text-muted">{{ __('wishes.vendor.disabled_hint') }}</p>
            @elseif ($wish->isSentToVendor())
                <x-detail-grid layout="cells" :cols="1" small-labels>
                    <x-detail-grid.row :label="__('wishes.vendor.sent_text')" class="whitespace-pre-line">{{ $wish->vendor_body }}</x-detail-grid.row>
                </x-detail-grid>
                <form method="POST" action="{{ route('admin.wishes.vendor-answer', $wish) }}" class="mt-3 space-y-3">
                    @csrf
                    @method('PUT')
                    <x-textarea-field name="vendor_answer" id="pw-vendor-answer" :label="__('wishes.vendor.answer')" rows="4"
                                      :value="old('vendor_answer', $wish->vendor_answer)" :hint="__('wishes.vendor.answer_hint')" />
                    <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('wishes.dialog.save') }}</x-button>
                </form>
            @elseif ($wish->platform_bundle_id !== null)
                <p class="text-sm text-muted">{{ __('wishes.vendor.lead_only') }}</p>
            @else
                <form method="POST" action="{{ route('admin.wishes.vendor-text', $wish) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <x-textarea-field name="vendor_body" id="pw-vendor-body" :label="__('wishes.vendor.text')" rows="4"
                                      :value="old('vendor_body', $vendorText)" :hint="__('wishes.vendor.text_hint')" />
                    <x-button type="submit" tone="ghost" size="sm" icon="refresh">{{ __('wishes.vendor.update_preview') }}</x-button>
                </form>
                @if ($preview !== null)
                    <div class="mt-4" data-vendor-preview>
                        <h4 class="mb-2 text-sm font-semibold">{{ __('wishes.vendor.preview') }}</h4>
                        <x-detail-grid layout="cells" :cols="1" small-labels>
                            <x-detail-grid.row :label="__('wishes.vendor.to')"><span class="font-mono text-xs">{{ $preview['to'] }}</span></x-detail-grid.row>
                            <x-detail-grid.row :label="__('wishes.vendor.reply_to')"><span class="font-mono text-xs">{{ $preview['reply_to'] }}</span></x-detail-grid.row>
                            <x-detail-grid.row :label="__('wishes.vendor.subject')">{{ $preview['subject'] }}</x-detail-grid.row>
                        </x-detail-grid>
                        <pre class="mt-2 max-h-80 overflow-auto rounded-box bg-base-200 p-3 text-xs leading-snug whitespace-pre-wrap">{{ $preview['body'] }}</pre>
                        <x-action-form :action="route('admin.wishes.vendor.send', $wish)" class="mt-3"
                                       :confirm="__('wishes.vendor.send_confirm', ['to' => $preview['to']])"
                                       :confirm-label="__('wishes.vendor.send')">
                            <input type="hidden" name="preview_hash" value="{{ $preview['hash'] }}">
                            <x-button type="submit" tone="primary" size="sm" icon="send">{{ __('wishes.vendor.send') }}</x-button>
                        </x-action-form>
                    </div>
                @endif
            @endif
        </x-card>
    </div>

    <x-validation-errors />
</x-index-page>
@endsection
