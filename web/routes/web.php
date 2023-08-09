<?php

use App\Http\Controllers\CanvasOAuthController;
use App\Http\Controllers\PeerGradingController;
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

Route::get('/oauth_redirect', [CanvasOAuthController::class, 'getRedirect']);
Route::get('/oauth_redirect_complete', [CanvasOAuthController::class, 'getRedirectComplete']);
Route::get('/oauth_logout', [CanvasOAuthController::class, 'getLogout']);

Route::middleware(['oauth'])->group(function() {
    Route::get('/', [PeerGradingController::class, 'index']);
    Route::get('/course/{course_id}/section_select', [PeerGradingController::class, 'sectionHome']);
    Route::post('/course/{course_id}/section_select', [PeerGradingController::class, 'postSectionHome']);
    Route::get('/course/{course_id}', [PeerGradingController::class, 'courseHome']);
    Route::get('/course/{course_id}/assignment/{assignment_id}', [PeerGradingController::class, 'assignmentHome']);
    Route::get('/course/{course_id}/assignment/{assignment_id}/export_gradebook', [PeerGradingController::class, 'gradebookExportScores']);
    Route::get('/course/{course_id}/assignment/{assignment_id}/export_comments', [PeerGradingController::class, 'exportScores']);
    Route::post('/v1/import/scores', [PeerGradingController::class, 'import_grades_to_gradebook']);
});

Route::post('/lti_launch', [PeerGradingController::class, 'ltiLaunch']);
Route::get('/lti_xml', [PeerGradingController::class, 'getLTIXML']);
