<?php

return [
    'local_search_mode' => env('LOCAL_SEARCH_MODE', 'fulltext'),
    'rating_column' => env('RATING_COLUMN', 'tmdb_vote_average'),
    'demo_reset_lists' => env('RESET_HOMEPAGE_LISTS', false),
];
