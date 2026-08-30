{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : certificate.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Öffentliche Zertifikatsprüfung (Feature 149, MVP-740). Datensparsam:
  genug zum Abgleich, keine Personenauskunft.
--}}
@extends('layouts.guest')
@section('title', __('learning.verify.title'))
@section('content')
    <div class="mx-auto max-w-lg p-6">
        @if ($certificate === null)
            <div class="alert alert-error">
                <x-icon name="error" />
                <span>{{ __('learning.verify.unknown') }}</span>
            </div>
        @else
            @if ($certificate->isRevoked())
                <div class="alert alert-error mb-4">
                    <x-icon name="block" />
                    <span>{{ __('learning.verify.revoked') }}</span>
                </div>
            @elseif ($certificate->isExpired())
                <div class="alert alert-warning mb-4">
                    <x-icon name="schedule" />
                    <span>{{ __('learning.verify.expired') }}</span>
                </div>
            @else
                <div class="alert alert-success mb-4">
                    <x-icon name="verified" />
                    <span>{{ __('learning.verify.valid') }}</span>
                </div>
            @endif

            <x-card>
                <x-detail-grid>
                    <x-detail-grid.row :label="__('learning.field.course')" :value="$certificate->course?->title ?? '–'" />
                    <x-detail-grid.row :label="__('learning.verify.holder')" :value="$holder" />
                    <x-detail-grid.row :label="__('learning.verify.issued_on')" :value="$certificate->issued_on?->translatedFormat('d.m.Y')" />
                    <x-detail-grid.row :label="__('learning.verify.valid_until')" :value="$certificate->valid_until?->translatedFormat('d.m.Y') ?? __('learning.verify.unlimited')" />
                    <x-detail-grid.row :label="__('learning.verify.issuer')" :value="$certificate->organization?->name ?? '–'" />
                    <x-detail-grid.row :label="__('learning.verify.number')" :value="$certificate->number" />
                </x-detail-grid>
            </x-card>
        @endif
    </div>
@endsection
