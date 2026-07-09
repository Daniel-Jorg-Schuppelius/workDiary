@include('errors._page', [
    'code' => 404,
    'icon' => 'search_off',
    'tone' => 'primary',
    'title' => __('errors.404.title'),
    'message' => __('errors.404.message'),
])
