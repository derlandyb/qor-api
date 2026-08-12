<?php

use App\Services\CrawlerUserAgentDetector;

test('given a known link-preview crawler user agent when classified then it is detected as a crawler', function (string $userAgent) {
    expect(CrawlerUserAgentDetector::isCrawler($userAgent))->toBeTrue();
})->with([
    'whatsapp' => 'WhatsApp/2.23.20.0 A',
    'facebook' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'twitter' => 'Twitterbot/1.0',
    'telegram' => 'TelegramBot (like TwitterBot)',
    'slack' => 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
    'discord' => 'Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)',
    'linkedin' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0)',
    'googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'pinterest' => 'Pinterest/0.2 (+https://www.pinterest.com/bot.html)',
    'skype' => 'SkypeUriPreview Preview/0.5',
    'vk' => 'vkShare; +http://vk.com/dev/Share',
    'reddit' => 'Redditbot/1.0',
]);

test('given a case-varied crawler user agent when classified then matching is case-insensitive', function () {
    expect(CrawlerUserAgentDetector::isCrawler('WHATSAPP/2.23.20.0'))->toBeTrue();
    expect(CrawlerUserAgentDetector::isCrawler('whatsapp/2.23.20.0'))->toBeTrue();
});

test('given a realistic real-browser user agent when classified then it is not a crawler', function (string $userAgent) {
    expect(CrawlerUserAgentDetector::isCrawler($userAgent))->toBeFalse();
})->with([
    'chrome desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'safari desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
    'firefox desktop' => 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
    'chrome android' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
    'safari ios' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
]);

test('given an empty user agent when classified then it is not a crawler', function () {
    expect(CrawlerUserAgentDetector::isCrawler(''))->toBeFalse();
});
