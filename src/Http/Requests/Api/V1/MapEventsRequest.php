<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use QOR\App\Domain\Event\MapBounds;
use QOR\App\Domain\Shared\Enum\City;

class MapEventsRequest extends FormRequest
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
        return [
            'north' => ['nullable', 'numeric', 'required_with:south,east,west'],
            'south' => ['nullable', 'numeric', 'required_with:north,east,west'],
            'east' => ['nullable', 'numeric', 'required_with:north,south,west'],
            'west' => ['nullable', 'numeric', 'required_with:north,south,east'],
            'city' => ['nullable', Rule::enum(City::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'north.numeric' => 'Limite norte inválido.',
            'north.required_with' => 'Informe os quatro limites do mapa (north, south, east, west).',
            'south.numeric' => 'Limite sul inválido.',
            'south.required_with' => 'Informe os quatro limites do mapa (north, south, east, west).',
            'east.numeric' => 'Limite leste inválido.',
            'east.required_with' => 'Informe os quatro limites do mapa (north, south, east, west).',
            'west.numeric' => 'Limite oeste inválido.',
            'west.required_with' => 'Informe os quatro limites do mapa (north, south, east, west).',
            'city.enum' => 'Cidade inválida.',
        ];
    }

    /**
     * MAPGEO-03/T7: exactly a bounding box (all 4 corners) or a city is
     * required — neither given is a 422, matching the Error Handling
     * Strategy's "invalid/missing bounding box and no city" row.
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $hasCity = $this->input('city') !== null;
            $hasFullBox = $this->input('north') !== null
                && $this->input('south') !== null
                && $this->input('east') !== null
                && $this->input('west') !== null;

            if (! $hasCity && ! $hasFullBox) {
                $validator->errors()->add(
                    'city',
                    'Informe uma cidade ou os quatro limites do mapa (north, south, east, west).',
                );
            }
        });
    }

    public function city(): ?City
    {
        /** @var string|null $city */
        $city = $this->validated('city');

        return $city !== null ? City::from($city) : null;
    }

    public function bounds(): ?MapBounds
    {
        if ($this->input('north') === null) {
            return null;
        }

        /** @var array{north: numeric-string|float, south: numeric-string|float, east: numeric-string|float, west: numeric-string|float} $validated */
        $validated = $this->validated();

        return new MapBounds(
            north: (float) $validated['north'],
            south: (float) $validated['south'],
            east: (float) $validated['east'],
            west: (float) $validated['west'],
        );
    }
}
