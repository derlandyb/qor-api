<?php

use Illuminate\Support\Facades\Route;

it('given app debug is enabled when an unexpected exception is thrown then the JSON response has no debug detail', function () {
    config(['app.debug' => true]);
    Route::get('/test-error-500', fn () => throw new RuntimeException(
        'Connection could not be established with host "mailhog:1025": getaddrinfo failed.'
    ));

    $this->getJson('/test-error-500')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Ocorreu um erro inesperado.']);
});

it('given app debug is enabled when a deliberate 403 abort is thrown then its message is preserved without debug detail', function () {
    config(['app.debug' => true]);
    Route::get('/test-error-403', fn () => abort(403, 'Você não tem permissão para executar esta ação.'));

    $this->getJson('/test-error-403')
        ->assertStatus(403)
        ->assertExactJson(['message' => 'Você não tem permissão para executar esta ação.']);
});

it('given a validation failure when the request expects JSON then field errors are returned unchanged', function () {
    config(['app.debug' => true]);

    $this->postJson('/api/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
