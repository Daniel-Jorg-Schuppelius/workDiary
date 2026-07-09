@include('errors._page', [
    'code' => 500,
    'icon' => 'error',
    'tone' => 'error',
    'title' => __('errors.500.title'),
    'message' => __('errors.500.message'),
])
