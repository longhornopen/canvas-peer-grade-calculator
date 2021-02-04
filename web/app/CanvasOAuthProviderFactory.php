<?php

namespace App;

use League\OAuth2\Client\Provider\GenericProvider;

class CanvasOAuthProviderFactory
{

    public static function getProvider()
    {
        $app_url = env('APP_URL');
        if (substr_compare( $app_url, '/', -1 ) !== 0) {
            $app_url .= '/';
        }

        $scopes = [
            'url:GET|/api/v1/courses/:course_id/assignments',
            'url:GET|/api/v1/courses/:course_id/assignments/:id',
            'url:GET|/api/v1/courses/:course_id/students',
            'url:GET|/api/v1/users/:user_id/courses',
            'url:GET|/api/v1/courses/:course_id/assignments/:assignment_id/peer_reviews',
            'url:GET|/api/v1/courses/:course_id/rubrics/:id',
            'url:GET|/api/v1/courses/:course_id/assignments/:assignment_id/submissions',
            'url:GET|/api/v1/courses/:course_id/assignments/:assignment_id/submissions/:user_id',
            'url:PUT|/api/v1/courses/:course_id/assignments/:assignment_id/submissions/:user_id',
            ];
        return new GenericProvider(
            [
                'clientId' => env("CANVAS_DEVELOPER_ACCESS_ID"),
                'clientSecret' => env("CANVAS_DEVELOPER_KEY"),
                'purpose' => "Peer Grade Calculator",
                'redirectUri' => $app_url . "oauth_redirect_complete",
                'urlAuthorize' => env('CANVAS_URL') . '/login/oauth2/auth',
                'urlAccessToken' => env('CANVAS_URL') . '/login/oauth2/token',
                'urlResourceOwnerDetails' => env('CANVAS_URL') . '/api/v1/users/self',
                'scopes' => [
                    implode(' ', $scopes)
                ],
            ]
        );
    }
}
