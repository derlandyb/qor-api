<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Http\JsonResponse;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\UseCase\ActivateGenre;
use QOR\App\Domain\Event\UseCase\CreateGenre;
use QOR\App\Domain\Event\UseCase\DeactivateGenre;
use QOR\App\Domain\Event\UseCase\ListAllGenres;
use QOR\App\Domain\Event\UseCase\UpdateGenre;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\CreateGenreRequest;
use QOR\App\Http\Requests\Api\AdminV1\UpdateGenreRequest;

class GenreController extends Controller
{
    public function __construct(
        private readonly ListAllGenres $listAllGenres,
        private readonly CreateGenre $createGenre,
        private readonly UpdateGenre $updateGenre,
        private readonly ActivateGenre $activateGenre,
        private readonly DeactivateGenre $deactivateGenre,
    ) {
    }

    public function index(): JsonResponse
    {
        $genres = $this->listAllGenres->execute();

        return response()->json([
            'data' => array_map(fn (Genre $genre) => $this->genreToArray($genre), $genres),
        ]);
    }

    public function store(CreateGenreRequest $request): JsonResponse
    {
        /** @var string $name */
        $name = $request->validated('name');

        $genre = $this->createGenre->execute($name);

        return response()->json(['data' => $this->genreToArray($genre)], 201);
    }

    public function update(UpdateGenreRequest $request, int $id): JsonResponse
    {
        /** @var string $name */
        $name = $request->validated('name');

        $genre = $this->updateGenre->execute($id, $name);

        return response()->json(['data' => $this->genreToArray($genre)]);
    }

    public function activate(int $id): JsonResponse
    {
        $genre = $this->activateGenre->execute($id);

        return response()->json(['data' => $this->genreToArray($genre)]);
    }

    public function deactivate(int $id): JsonResponse
    {
        $genre = $this->deactivateGenre->execute($id);

        return response()->json(['data' => $this->genreToArray($genre)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function genreToArray(Genre $genre): array
    {
        return [
            'id' => $genre->id,
            'name' => $genre->name,
            'slug' => $genre->slug,
            'is_active' => $genre->isActive,
        ];
    }
}
