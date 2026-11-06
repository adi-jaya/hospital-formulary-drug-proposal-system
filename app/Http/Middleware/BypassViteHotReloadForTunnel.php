<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class BypassViteHotReloadForTunnel
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // If accessed through external tunnel (e.g. *.trycloudflare.com) or remote network,
        // bypass local Vite HMR dev server and serve compiled production assets from public/build.
        if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.test')) {
            Vite::useHotFile(storage_path('framework/non-existent-hot'));
        }

        return $next($request);
    }
}
