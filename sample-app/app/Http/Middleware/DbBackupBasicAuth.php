<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DbBackupBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (string) config('services.db_backup.user');
        $password = (string) config('services.db_backup.password');

        // With no password configured the endpoint doesn't exist.
        if ($password === '') {
            abort(404);
        }

        $validUser = hash_equals($user, (string) $request->getUser());
        $validPassword = hash_equals($password, (string) $request->getPassword());

        if (! $validUser || ! $validPassword) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Database backup"']);
        }

        return $next($request);
    }
}
