<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App;
use App\CanvasOAuthProviderFactory;
use Illuminate\Support\Facades\Session;

class CanvasOauthController extends Controller
{

    public function getRedirect(Request $request)
    {
        $request->session()->put("oauth2_return_url", $request->get('return_url'));
        $provider = CanvasOAuthProviderFactory::getProvider();
        $authorizationUrl = $provider->getAuthorizationUrl();
        Session::put('oauth2_state', $provider->getState());
        return redirect($authorizationUrl);
    }

    public function getRedirectComplete(Request $request)
    {
        $provider = CanvasOAuthProviderFactory::getProvider();

        /* check that the passed state matches the stored state to mitigate cross-site request forgery attacks */
        if (!$request->has('state') || (Session::has('oauth2_state') && $request->get('state') !== Session::get(
                    'oauth2_state'
                ))) {
            if (Session::has('oauth2_state')) {
                Session::forget('oauth2_state');
            }

            exit('Invalid state');
        }

        if ($request->has("error")) {
            App::abort(500, "ERROR: Canvas reports '" . $request->get('error') . "'.  Please try again?");
        }

        if (!Session::has('oauth2_state')) {
            return "Sorry, but your login session expired while waiting for you to log in to Canvas.  Please try again.";
        }

        $code = $request->get("code");
        Session::put('oauth2_code', $code);
        $access_token = $provider->getAccessToken(
            'authorization_code',
            [
                'code' => $code,
                'replace_tokens' => 'true',
            ]
        );

        Session::put("oauth2_access_token", $access_token);
        Session::put("oauth2_refresh_token", $access_token->getRefreshToken());
        $resourceOwner = $provider->getResourceOwner($access_token);
        Session::put("oauth2_user_id", $resourceOwner->getId());
        $return_url = Session::get("oauth2_return_url");
        return redirect($return_url);
    }

    public function getLogout(Request $request)
    {
        Session::flush();
        $return_url = $request->get("return_url");
        if ($return_url === null) {
            $return_url = "https://www.utexas.edu/";
        }
        return redirect($return_url);
    }
}
