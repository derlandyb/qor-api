<?php

namespace App\Services;

final class CrawlerUserAgentDetector
{
    public static function isCrawler(string $userAgent): bool
    {
        $ua = mb_strtolower($userAgent);

        foreach (config('sharing.crawler_user_agents') as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }
}
