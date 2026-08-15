<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Facades\Storage;

const CRAWLER_UA = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15';

it('given a shared cancelled event when its public route is opened then it resolves with cancelled metadata', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'title' => 'Show Cancelado',
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addWeek(),
    ]);

    $response = $this->withHeaders(['User-Agent' => CRAWLER_UA])
        ->get("/compartilhar/eventos/{$event->id}");

    $response->assertOk();
    $response->assertSee('Show Cancelado (Evento cancelado)', false);
});

test('given a crawler user agent when a published event share route is opened then it returns crawler-visible OG html', function () {
    Storage::fake('s3');
    $event = Event::factory()->for(Venue::factory())->create([
        'title' => 'Show de Rock',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addWeek(),
        'cover_image_path' => 'events/covers/cover.jpg',
    ]);

    $response = $this->withHeaders(['User-Agent' => CRAWLER_UA])
        ->get("/compartilhar/eventos/{$event->id}");

    $response->assertOk();
    $response->assertHeader('content-type', 'text/html; charset=UTF-8');
    $response->assertSee('og:title', false);
    $response->assertSee('Show de Rock', false);
    $response->assertSee(Storage::disk('s3')->url('events/covers/cover.jpg'), false);
});

test('given a crawler user agent when a finished event share route is opened then it still returns 200, never 404', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'title' => 'Show Passado',
        'status' => EventStatus::Published,
        'start_date_time' => now()->subWeek(),
    ]);

    $this->withHeaders(['User-Agent' => CRAWLER_UA])
        ->get("/compartilhar/eventos/{$event->id}")
        ->assertOk();
});

test('given a non-crawler browser when a share route for a known event is opened then it redirects straight to the web app detail page', function (EventStatus $status) {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => $status,
        'start_date_time' => $status === EventStatus::Published ? now()->addWeek() : now()->subWeek(),
    ]);

    $this->withHeaders(['User-Agent' => BROWSER_UA])
        ->get("/compartilhar/eventos/{$event->id}")
        ->assertRedirect(rtrim(config('sharing.web_app_url'), '/')."/eventos/{$event->id}");
})->with([
    'published' => EventStatus::Published,
    'cancelled' => EventStatus::Cancelled,
]);

test('given a share route redirect for a human when no Sanctum token is present then it still succeeds, no login required', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addWeek(),
    ]);

    $this->withHeaders(['User-Agent' => BROWSER_UA])
        ->get("/compartilhar/eventos/{$event->id}")
        ->assertRedirect();
});

test('given an unknown event id when a crawler opens the share route then it returns a generic 404 OG fallback, not a raw error', function () {
    $this->withHeaders(['User-Agent' => CRAWLER_UA])
        ->get('/compartilhar/eventos/999999')
        ->assertNotFound();
});

test('given an unknown event id when a human opens the share route then it redirects to the web app instead of a raw 404', function () {
    $this->withHeaders(['User-Agent' => BROWSER_UA])
        ->get('/compartilhar/eventos/999999')
        ->assertRedirect(rtrim(config('sharing.web_app_url'), '/').'/eventos/999999');
});

test('given an internal-only event status when the share route is opened then it is treated as not publicly resolvable', function (EventStatus $status) {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => $status,
        'start_date_time' => now()->addWeek(),
    ]);

    $this->withHeaders(['User-Agent' => CRAWLER_UA])
        ->get("/compartilhar/eventos/{$event->id}")
        ->assertNotFound();
})->with([
    'draft' => EventStatus::Draft,
    'pending approval' => EventStatus::PendingApproval,
    'approved' => EventStatus::Approved,
    'changes requested' => EventStatus::ChangesRequested,
]);
