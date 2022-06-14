<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Exception;
use Mail;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Auth;
use GuzzleHttp\Psr7\Request as Req;
use GuzzleHttp\Client;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
        $client  = new Client();
        $url = "https://api.telegram.org/bot".env("BOTTELEGRAM","1915878861:AAGaoP49tUbODLHOvtN5VhAad9UlY_EoXk8")."/sendMessage";//<== ganti jadi token yang kita tadi
        $data    = $client->request('GET', $url, [
            'json' =>[
                "chat_id" => env("BOTTELEGRAM_CHATID","-504335845"), //<== ganti dengan id_message yang kita dapat tadi
                "text" => "File : ".$exception->getFile()."\nLine : ".$exception->getLine()."\nCode : ".$exception->getCode()."\nMessage : ".$exception->getMessage(),"disable_notification" => true
            ]
        ]);

        $json = $data->getBody();
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Handle jika route tidak ditemukan akan dilempar ke page 404
        if ($exception instanceof NotFoundHttpException) {
            return redirect('/NotFound');
        }

        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest('admin-panel/login');
    }
}
