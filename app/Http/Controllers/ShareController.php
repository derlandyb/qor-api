<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\CrawlerUserAgentDetector;
use App\Services\EventShareMetaBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ShareController extends Controller
{
    public function resolveEvent(Request $request, string $id): Response|RedirectResponse
    {
        $webAppUrl = rtrim(config('sharing.web_app_url'), '/')."/eventos/{$id}";
        $isCrawler = CrawlerUserAgentDetector::isCrawler((string) $request->userAgent());

        $event = Event::query()
            ->with('venue')
            // Same publicly-resolvable set as EventController@show (event-details) — SHARE-003/005.
            ->whereIn('status', [EventStatus::Published, EventStatus::Cancelled, EventStatus::Finished])
            ->whereKey($id)
            ->first();

        if ($event === null) {
            // Never hard-deleted per PRD §36 — a truly unknown id is a data-integrity edge case, not the
            // expected path. Humans still land on the real app (whose own NotFoundPage.tsx handles it);
            // crawlers get a generic, app-level (not event-specific) OG fallback rather than a raw error.
            return $isCrawler
                ? response()->view('share.event-not-found', [], 404)
                : redirect()->away($webAppUrl);
        }

        if (! $isCrawler) {
            return redirect()->away($webAppUrl); // SHARE-003: no login, no intermediate landing page for humans.
        }

        $meta = EventShareMetaBuilder::build($event);

        return response()->view('share.event', ['meta' => $meta, 'canonicalUrl' => $webAppUrl]);
    }
}
