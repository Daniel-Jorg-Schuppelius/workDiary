{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Managementbewertungen (Feature 046, Inkrement C): Liste (Nr, Datum,
  Scope, Status, Freigeber), Modal-CRUD für Entwürfe, Freigeben-Aktion
  mit Bestätigung (setzt Person + Zeitpunkt) und Read-Only-Anzeige
  freigegebener Protokolle (Unveränderlichkeit serverseitig im
  AuditService erzwungen).
  Variablen: $reviews, $canManage
--}}

@extends('layouts.app')

@section('title', __('isms.title.reviews'))
@section('nav-title', __('isms.title.reviews'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.reviews')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.reviews.create')"
                            show-label>{{ __('isms.action.create_review') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        {{-- 046-Freigaberegel als sichtbarer Hinweis. --}}
        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="grading" />
            <span>{{ __('isms.review.approval_rule') }}</span>
        </div>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.review_no') }}</th>
                    <th>{{ __('isms.field.held_on') }}</th>
                    <th>{{ __('isms.field.scope') }}</th>
                    <th>{{ __('isms.field.participants') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th>{{ __('isms.field.approved_by') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($reviews as $review)
                @php /** @var \App\Models\Isms\IsmsManagementReview $review */ @endphp
                <tr class="hover" id="isms-review-{{ $review->id }}">
                    <td class="font-mono text-sm">{{ $review->displayNo() }}</td>
                    <td>{{ $review->held_on->format('d.m.Y') }}</td>
                    <td class="text-base-content/70">{{ optional($review->scope)->name ?? '—' }}</td>
                    <td class="max-w-64 truncate text-base-content/70" title="{{ $review->participants }}">{{ $review->participants }}</td>
                    <td><x-status-badge :tone="$review->status->tone()">{{ $review->status->label() }}</x-status-badge></td>
                    <td class="text-base-content/70">
                        @if ($review->isApproved())
                            {{ optional($review->approvedBy)->name ?? '—' }}
                            <span class="block text-xs text-muted">{{ $review->approved_at?->format('d.m.Y H:i') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('view', $review)
                                <x-icon-btn icon="visibility" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.reviews.show', $review)"
                                            :label="__('isms.action.show_review')" />
                            @endcan
                            @if (! $review->isApproved())
                                @can('update', $review)
                                    <x-icon-btn icon="edit" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('isms.reviews.edit', $review)"
                                                :label="__('isms.action.edit')" />
                                @endcan
                                @can('approve', $review)
                                    <x-action-form :action="route('isms.reviews.approve', $review)"
                                          data-confirm-title="{{ __('isms.action.approve_review') }}"
                                          :confirm="__('isms.confirm_approve_review')"
                                          confirm-icon="grading"
                                          confirm-tone="primary"
                                          :confirm-label="__('isms.action.approve_review')">
                                        <x-icon-btn icon="grading" tone="primary" size="xs" type="submit"
                                                    :label="__('isms.action.approve_review')" />
                                    </x-action-form>
                                @endcan
                                @can('delete', $review)
                                    <x-action-form :action="route('isms.reviews.destroy', $review)" method="DELETE"
                                          data-confirm-title="{{ __('isms.action.delete') }}"
                                          :confirm="__('isms.confirm_delete_review')"
                                          confirm-icon="delete"
                                          confirm-tone="error"
                                          :confirm-label="__('isms.action.delete')">
                                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                    :label="__('isms.action.delete')" />
                                    </x-action-form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7"
                               :title="__('isms.empty_reviews_title')"
                               :message="__('isms.empty_reviews')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$reviews" standing />
    </x-index-page>
@endsection
