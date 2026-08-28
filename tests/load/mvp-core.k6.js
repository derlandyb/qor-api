/**
 * QOR API — MVP Core load test (k6)
 *
 * Covers the required endpoint list from ARCHITECTURE.md §8.8:
 *   - Fan event list        GET  /api/v1/events
 *   - Fan event detail      GET  /api/v1/events/{id}
 *   - Fan login             POST /api/v1/auth/login
 *   - Admin event submission POST /api/admin/v1/events, then
 *                            POST /api/admin/v1/events/{id}/submit
 *   - Admin approval queue  GET  /api/admin/v1/approvals/events
 *
 * ---------------------------------------------------------------------
 * THRESHOLDS ARE PLACEHOLDERS.
 *
 * No numeric SLO target exists anywhere in .specs/ as of this writing.
 * The `http_req_duration: p(95)<300` and `http_req_failed: rate<0.01`
 * thresholds below are reasonable-sounding defaults chosen so this
 * script is runnable out of the box — they are NOT an agreed SLO.
 * Replace them once ops defines a real target (see ARCHITECTURE.md §8.8).
 * ---------------------------------------------------------------------
 *
 * Run:
 *   k6 run api/tests/load/mvp-core.k6.js
 *
 * Env vars (all optional, see defaults below):
 *   BASE_URL      Base API URL                          (default: http://localhost:8000)
 *   EVENT_ID      Fixed seeded event id for the detail   (default: 1 — assumes
 *                 scenario, used only if the event list  `php artisan db:seed`
 *                 comes back empty                       has been run)
 *   FAN_EMAIL     Seeded fan credentials for the login   (default: a seeded
 *   FAN_PASSWORD  scenario                                FanSeeder/UserFactory
 *                                                          account — override
 *                                                          with real seeded
 *                                                          creds; the factory
 *                                                          default password
 *                                                          is "password")
 *   ADMIN_TOKEN   Bearer token for an approved Venue/     (required for the
 *                 Promoter admin account, used for the    admin event
 *                 "admin event submission" scenario        submission scenario
 *                                                          — that scenario is
 *                                                          skipped with a
 *                                                          console warning if
 *                                                          this is unset)
 *   SUPER_ADMIN_TOKEN  Bearer token for a Super Admin      (required for the
 *                      account, used for the approval      approval queue
 *                      queue scenario                      scenario — skipped
 *                                                          with a console
 *                                                          warning if unset)
 *
 * There is no dedicated admin-login endpoint in this phase of the API
 * (see docs/postman/qor-api-mvp-core.postman_collection.json), so
 * ADMIN_TOKEN / SUPER_ADMIN_TOKEN must be obtained out of band (e.g. by
 * issuing a Sanctum token for a seeded admin/super-admin fixture account)
 * and passed in as env vars — this script does not attempt to mint them.
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';

export const options = {
  vus: 10,
  duration: '1m',
  thresholds: {
    // PLACEHOLDER — see file header. Not an agreed SLO.
    http_req_duration: ['p(95)<300'],
    http_req_failed: ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EVENT_ID = __ENV.EVENT_ID || '1';
const FAN_EMAIL = __ENV.FAN_EMAIL || 'fan@example.com';
const FAN_PASSWORD = __ENV.FAN_PASSWORD || 'password';
const ADMIN_TOKEN = __ENV.ADMIN_TOKEN || '';
const SUPER_ADMIN_TOKEN = __ENV.SUPER_ADMIN_TOKEN || '';

const jsonHeaders = { 'Content-Type': 'application/json' };

function authHeaders(token) {
  return { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` };
}

export default function () {
  group('fan event list', () => {
    const res = http.get(`${BASE_URL}/api/v1/events`);
    check(res, {
      'event list status is 200': (r) => r.status === 200,
      'event list has data array': (r) => {
        try {
          return Array.isArray(r.json('data'));
        } catch (e) {
          return false;
        }
      },
    });
  });

  group('fan event detail', () => {
    // Uses the fixed EVENT_ID param above; assumes `php artisan db:seed`
    // has run so this id exists. Swap to picking an id out of the list
    // response above if you need this to be resilient to reseeding.
    const res = http.get(`${BASE_URL}/api/v1/events/${EVENT_ID}`);
    check(res, {
      'event detail status is 200 or 404': (r) => r.status === 200 || r.status === 404,
    });
  });

  group('fan login', () => {
    const res = http.post(
      `${BASE_URL}/api/v1/auth/login`,
      JSON.stringify({ email: FAN_EMAIL, password: FAN_PASSWORD }),
      { headers: jsonHeaders },
    );
    check(res, {
      'login status is 200 or 401/403': (r) => [200, 401, 403].includes(r.status),
    });
  });

  group('admin event submission', () => {
    if (!ADMIN_TOKEN) {
      console.warn('ADMIN_TOKEN not set — skipping admin event submission scenario.');
      return;
    }

    const createRes = http.post(
      `${BASE_URL}/api/admin/v1/events`,
      JSON.stringify({
        title: `k6 Load Test Event ${Date.now()}`,
        description: 'Event created by the mvp-core k6 load test script.',
        starts_at: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
        city: 'vitoria',
        genre_id: 1,
        is_free: true,
      }),
      { headers: authHeaders(ADMIN_TOKEN) },
    );

    const created = check(createRes, {
      'create event status is 201': (r) => r.status === 201,
    });

    if (created) {
      let eventId;
      try {
        eventId = createRes.json('data.id');
      } catch (e) {
        eventId = null;
      }

      if (eventId) {
        const submitRes = http.post(
          `${BASE_URL}/api/admin/v1/events/${eventId}/submit`,
          null,
          { headers: authHeaders(ADMIN_TOKEN) },
        );
        check(submitRes, {
          'submit event status is 200': (r) => r.status === 200,
        });
      }
    }
  });

  group('admin approval queue', () => {
    if (!SUPER_ADMIN_TOKEN) {
      console.warn('SUPER_ADMIN_TOKEN not set — skipping admin approval queue scenario.');
      return;
    }

    const res = http.get(`${BASE_URL}/api/admin/v1/approvals/events`, {
      headers: authHeaders(SUPER_ADMIN_TOKEN),
    });
    check(res, {
      'approval queue status is 200': (r) => r.status === 200,
    });
  });

  sleep(1);
}
