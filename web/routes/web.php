<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/oauth_redirect', 'CanvasOAuthController@getRedirect');
Route::get('/oauth_redirect_complete', 'CanvasOAuthController@getRedirectComplete');
Route::get('/oauth_logout', 'CanvasOAuthController@getLogout');

Route::get('/home/{course_id}', 'PeerReviewController@index')->middleware('oauth')->name('home');
Route::get('/v1/scores/{course_id}/{assignment_id}', 'PeerReviewController@loadCourseInfo')->middleware('oauth');
Route::get('/v1/export/scores/{course_id}/{assignment_id}', 'PeerReviewController@exportScores')->middleware('oauth');
Route::get('/v1/export1/scores/{course_id}/{assignment_id}', 'PeerReviewController@gradebookExportScores')->middleware('oauth');
Route::post('/v1/import/scores', 'PeerReviewController@import_grades_to_gradebook')->middleware('oauth');

Route::post('/lti_launch', 'PeerReviewController@ltiLaunch');
