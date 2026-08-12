<?php

return [
    // Substrings matched case-insensitively against the inbound User-Agent header
    // to decide whether ShareController serves crawler-visible OG HTML or redirects
    // a human straight to the web app. See CrawlerUserAgentDetector.
    'crawler_user_agents' => [
        'whatsapp', 'facebookexternalhit', 'twitterbot', 'telegrambot',
        'slackbot', 'discordbot', 'linkedinbot', 'googlebot', 'bingbot',
        'pinterest', 'skypeuripreview', 'vkshare', 'redditbot',
    ],

    // Canonical web app origin a human visitor is redirected to from
    // GET /compartilhar/eventos/{id} — see ShareController@resolveEvent.
    'web_app_url' => env('WEB_APP_URL', 'https://qualorock.com.br'),
];
