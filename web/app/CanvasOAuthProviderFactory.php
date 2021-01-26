<?php


namespace App;


use League\OAuth2\Client\Provider\GenericProvider;

class CanvasOAuthProviderFactory
{

    public static function getProvider()
    {
        return new GenericProvider(
            [
                'clientId' => env("CANVAS_DEVELOPER_ACCESS_ID"),
                'clientSecret' => env("CANVAS_DEVELOPER_KEY"),
                'purpose' => "UT Austin Apps",
                'redirectUri' => env('APP_URL') . "oauth_redirect_complete",
                'urlAuthorize' => env('CANVAS_URL') . '/login/oauth2/auth',
                'urlAccessToken' => env('CANVAS_URL') . '/login/oauth2/token',
                'urlResourceOwnerDetails' => env('CANVAS_URL') . '/api/v1/users/self',
            ]
        );
    }
}
