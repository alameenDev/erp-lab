<?php

namespace App\Http\Middleware;

use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditActivity
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/activity/*', 'api/log/*', 'api/device/heartbeat') ||
            (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS']) && ! $request->header('X-Audit-Action-Id')) ||
            preg_match('~/search[-/]|/heartbeat$~', $request->path())) {
            return $next($request);
        }
        $audit = app(AuditTrail::class);
        $status = 500;
        $transactionLevel = DB::transactionLevel();
        $audit->begin($request);
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            return $response;
        } catch (Throwable $error) {
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : ($error instanceof \Illuminate\Validation\ValidationException ? 422 : 500);
            throw $error;
        } finally {
            try {
                // Some legacy controllers return an error from inside a transaction.
                // Only unwind transactions opened by this failed request.
                if ($status >= 400) {
                    while (DB::transactionLevel() > $transactionLevel) {
                        DB::rollBack();
                    }
                }
                $audit->finish($status);
            } catch (Throwable $error) {
                // Never turn an already-committed medical operation into an apparent failure.
                Log::error('Activity audit persistence failed', ['exception' => get_class($error)]);
                $audit->reset();
            }
        }
    }
}
