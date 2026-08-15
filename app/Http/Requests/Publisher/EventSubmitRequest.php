<?php

namespace App\Http\Requests\Publisher;

use App\Enums\AgeRating;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Backs both POST /api/publisher/events and PATCH /api/publisher/events/{event}.
 * `action=draft` requires only `title` (PUBLISH-008 — partial saves allowed indefinitely);
 * `action=submit` requires the full field set (PUBLISH-001 AC1).
 */
final class EventSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $submit = $this->input('action') === 'submit';

        /** @var User $user */
        $user = $this->user();
        // A Venue-profile account's venueId is locked server-side (see Publisher\EventController),
        // so it's never a client-required field for that account — only for Promoter accounts.
        // Keyed off promoterProfile/venueProfile presence, matching the manage-events Gate and
        // EventPolicy — User.account_type is never actually populated anywhere in this codebase.
        $venueIdRequired = $submit && $user->venueProfile === null;

        return [
            'action' => ['required', 'string', 'in:draft,submit'],
            'title' => ['required', 'string', 'max:255'],
            'description' => [$submit ? 'required' : 'nullable', 'string', 'max:5000'],
            // Required-on-submit is relaxed by withValidator() below when the route-bound Event
            // already has a stored cover image — a browser file input can't be pre-filled, so
            // re-uploading on every submit would be broken UX.
            'coverImage' => [$submit ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'startDateTime' => [$submit ? 'required' : 'nullable', 'date'],
            'endDateTime' => ['nullable', 'date', 'after:startDateTime'],
            'venueId' => [$venueIdRequired ? 'required' : 'nullable', 'integer', 'exists:venues,id'],
            'priceMin' => ['nullable', 'numeric', 'min:0'],
            'priceMax' => ['nullable', 'numeric', 'min:0'],
            'isFree' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'size:3'],
            'ageRating' => ['nullable', Rule::in(array_column(AgeRating::cases(), 'value'))],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['string'],
            'artistIds' => ['nullable', 'array'],
            'artistIds.*' => ['integer', 'exists:artists,id'],
            // No URL validation — reuses event-feed's no-crawl stance (TICKET-001).
            'ticketUrl' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('action') !== 'submit' || $this->hasFile('coverImage')) {
                return;
            }

            /** @var Event|null $event */
            $event = $this->route('event');

            if ($event !== null && $event->cover_image_path !== null) {
                // Already-stored cover from an earlier draft/edit satisfies the submit requirement.
                $validator->errors()->forget('coverImage');
            }
        });
    }
}
