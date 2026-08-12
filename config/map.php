<?php

return [
    // Hard safety cap on GET /api/events/map's unpaginated result set — see MapController@index.
    'max_markers' => env('MAP_MAX_MARKERS', 500),
];
