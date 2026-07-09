@include('errors._page', [
    'code' => 403,
    'icon' => 'lock',
    'tone' => 'warning',
    'title' => __('errors.403.title'),
    'message' => ($exception ?? null) && $exception->getMessage() !== '' && $exception->getMessage() !== 'This action is unauthorized.'
        ? $exception->getMessage()
        : __('errors.403.message'),
])
