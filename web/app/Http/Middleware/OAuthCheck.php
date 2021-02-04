<?php

namespace App\Http\Middleware;

use App\CanvasOAuthProviderFactory;
use App\Http\Controllers\CanvasOAuthController;
use Closure;
use Illuminate\Http\Request;

class OAuthCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has("oauth2_access_token") || !$request->session()->has("oauth2_refresh_token")) {
            $url = $request->fullUrl();
            return redirect('oauth_redirect?return_url='.urlencode($url));
        }

        $refresh_token = $request->session()->get('oauth2_refresh_token');
        $access_token = $request->session()->get('oauth2_access_token');

        if ($access_token->hasExpired()) {
            $provider = CanvasOAuthProviderFactory::getProvider();
            $new_access_token = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $refresh_token
            ]);
            $request->session()->put('oauth2_access_token', $new_access_token);
        }

        return $next($request);
    }
}
