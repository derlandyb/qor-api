<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalyticsEventName;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AnalyticsEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $events = $request->input('events');
        if (! is_array($events)) {
            return response()->json(['message' => 'events must be an array'], 422);
        }

        $userId = $request->user('sanctum')?->id;
        $rows = [];
        $rejected = 0;

        foreach (array_slice($events, 0, 100) as $event) { // hard cap per batch, mirrors filters'/map's safety-limit convention
            if (! is_array($event)) {
                $rejected++;

                continue; // a malformed (non-object) row never aborts the batch or 500s
            }

            $validator = Validator::make($event, [
                'eventName' => ['required', 'string', Rule::in(AnalyticsEventName::values())],
                'properties' => ['sometimes', 'array'],
                'clientTimestamp' => ['required', 'date'],
            ]);

            if ($validator->fails()) {
                $rejected++;

                continue; // one bad event never aborts the batch or 500s
            }

            $valid = $validator->validated();
            $rows[] = [
                'event_name' => $valid['eventName'],
                'properties' => json_encode($valid['properties'] ?? []),
                'user_id' => $userId,
                'client_timestamp' => $valid['clientTimestamp'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('analytics_events')->insert($rows);
        }

        return response()->json(['accepted' => count($rows), 'rejected' => $rejected], 202);
    }
}
