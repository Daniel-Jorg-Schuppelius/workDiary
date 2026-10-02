{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _subject_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Chat am Projekt bzw. Auftrag (MVP-1061). Erwartet: $subjectType (project|diary), $subject; optional $class.
     Dateien sehen nur Kanalmitglieder — wie beim Anhang selbst (AttachmentPolicy). --}}
@feature('module.chat')
    @can('viewAny', \App\Models\Chat\Channel::class)
        @php
            $subjectChannels = app(\App\Services\Chat\SubjectChannelService::class);
            $subjectChannel = $subjectChannels->channelFor($subject);
            $isChannelMember = $subjectChannel !== null && $subjectChannel->members()->whereKey(auth()->id())->exists();
            $chatFiles = $isChannelMember ? $subjectChannels->attachments($subject, 20) : collect();
        @endphp
        <x-card :title="__('chat.subject.title')" icon="forum" :class="$class ?? ''">
            <x-slot:actions>
                <x-action-form :action="route('chat.subject.open', [$subjectType, $subject->sqid])">
                    <x-icon-btn icon="forum" size="sm" tone="primary" type="submit" show-label>{{ $subjectChannel !== null ? __('chat.subject.open') : __('chat.subject.start') }}</x-icon-btn>
                </x-action-form>
            </x-slot:actions>
            @if ($chatFiles->isNotEmpty())
                <p class="mb-2 text-xs font-semibold text-muted">{{ __('chat.subject.files') }}</p>
                <ul class="space-y-1 text-sm">
                    @foreach ($chatFiles as $chatFile)
                        <li class="flex items-center gap-2">
                            <x-icon name="attach_file" class="text-muted" />
                            <a class="link min-w-0 flex-1 truncate" href="{{ \App\Http\Controllers\Attachments\AttachmentController::downloadUrl($chatFile) }}">{{ $chatFile->original_name }}</a>
                            <span class="text-xs text-muted">{{ $chatFile->created_at?->fdate() }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-muted">{{ $subjectChannel !== null && $isChannelMember ? __('chat.subject.no_files') : __('chat.subject.hint') }}</p>
            @endif
        </x-card>
    @endcan
@endfeature
