@extends('layouts.app')
@section('title', truncate($diary->content, 60) . ' — WorkDiary')
@section('nav-title', __('Eintrag') . ' #' . $diary->id)

@section('content')
    <x-page-shell>
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
            <div class="h-full overflow-auto p-6 md:p-8 space-y-6">
                @include('diary._show_body', ['isDialog' => false])
                <div class="text-center">
                    <x-icon-btn icon="arrow_back" size="sm" :href="route('diary.index')" show-label>{{ __('Zurück zur Liste') }}</x-icon-btn>
                </div>
            </div>
        </div>
    </x-page-shell>
@endsection
