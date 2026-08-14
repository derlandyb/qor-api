<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Never let a JSON API response leak framework debug detail (exception class, file, line,
     * stack trace) or a raw internal exception message (e.g. a mail-transport error), regardless
     * of APP_DEBUG. Deliberately-thrown app exceptions with an intentional pt-BR message (e.g.
     * `abort_if(..., 409, 'Este evento já foi cancelado.')`) are preserved unchanged.
     */
    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);

        if (! $request->expectsJson() || ! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        $status = $response->getStatusCode();

        $hasDebugDetail = array_key_exists('exception', $data)
            || array_key_exists('trace', $data)
            || array_key_exists('file', $data)
            || array_key_exists('line', $data);

        if ($hasDebugDetail || $status >= 500) {
            $message = $status >= 500
                ? 'Ocorreu um erro inesperado.'
                : ($data['message'] ?? 'Ocorreu um erro ao processar a solicitação.');

            return response()->json(['message' => $message], $status);
        }

        return $response;
    }
}
