<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //Intercepts OPTIONS requests
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        } else {
            // Dont return headers for Ixdura request to passport's "oauth/token,oauth/refresh, etc" route. 
            // It will always return authentication failure during Login.
            // Note: that I am tracking Ixdura request to OAuth here with "client_id" and "grant_type".You can add more OAuth parameters
            if ($request->get('client_id') && $request->get('grant_type')) {
                return  $next($request);
            } else {
                // Pass the request to the next middleware
                $response =  $next($request);
            }
        }

        // Adds headers to the response
        $response->header('Access-Control-Allow-Methods', '*');
        $response->header(
            'Access-Control-Allow-Headers',
            $request->header('Access-Control-Request-Headers')
        );
        // $response->header('Access-Control-Allow-Headers', '*');
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Expose-Headers', 'Location');

        // Sends it
        return $response;
    }
}
