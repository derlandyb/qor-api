<?php

namespace QOR\App\Domain\Event;

use InvalidArgumentException;

final class Coordinates
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if ($this->latitude < -90.0 || $this->latitude > 90.0) {
            throw new InvalidArgumentException('Latitude inválida: deve estar entre -90 e 90.');
        }

        if ($this->longitude < -180.0 || $this->longitude > 180.0) {
            throw new InvalidArgumentException('Longitude inválida: deve estar entre -180 e 180.');
        }
    }
}
