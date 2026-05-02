<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use App\Exceptions\InsufficientQuantityException;

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
        $this->renderable(function (InsufficientQuantityException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        });
    }


    public function render($request, Throwable $exception)
    {
        if ($exception instanceof \Exception) {

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage()
            ], 400);
        }

        return parent::render($request, $exception);
    }
}


