<?php

namespace QOR\App\Domain\Event;

use InvalidArgumentException;

/**
 * A geographic bounding box, used by the Mapa Interativo geo query
 * (MAPGEO-03) — not a persisted entity.
 */
final class MapBounds
{
    public function __construct(
        public readonly float $north,
        public readonly float $south,
        public readonly float $east,
        public readonly float $west,
    ) {
        if ($this->north <= $this->south) {
            throw new InvalidArgumentException('O limite norte deve ser maior que o limite sul.');
        }

        if ($this->east <= $this->west) {
            throw new InvalidArgumentException('O limite leste deve ser maior que o limite oeste.');
        }
    }
}
