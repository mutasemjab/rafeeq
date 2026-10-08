<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatAttachmentController;
use App\Http\Controllers\Api\ChildChatController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\ChildDocumentController;
use App\Http\Controllers\Api\ChildMemoryController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\KnowledgeDocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PersonProfileController;
use App\Http\Controllers\Api\PersonDocumentController;
use App\Http\Controllers\Api\SupportPathwayController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SpecialistController;
use App\Http\Controllers\Api\SpecialistReviewController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserPrivacyController;
use App\Http\Controllers\Api\UserDeviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rafiq API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public ──────────────────────────────────────────────────────────
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);
    Route::post('/auth/social',   [AuthController::class, 'socialLogin']);

    Route::get('/plans',                                  [PlanController::class, 'index']);
    Route::get('/settings',                               [SettingsController::class, 'index']);
    Route::get('/specialists',                            [SpecialistController::class, 'index']);
    Route::get('/specialists/{specialist}',               [SpecialistController::class, 'show']);
    Route::get('/specialists/{specialist}/availabilities',[SpecialistController::class, 'availabilities']);
    Route::get('/specialists/{specialist}/reviews',       [SpecialistReviewController::class, 'index']);
    Route::get('/knowledge',                              [KnowledgeDocumentController::class, 'index']);

    Route::get('/private-files/attachments/{attachment}', [ChatAttachmentController::class, 'downloadSigned'])
        ->middleware('signed')->name('attachments.temporary-download');
    Route::get('/private-files/child-documents/{document}', [ChildDocumentController::class, 'downloadSigned'])
        ->middleware('signed')->name('child-documents.temporary-download');
    Route::get('/private-files/person-documents/{document}', [PersonDocumentController::class, 'downloadSigned'])
        ->middleware('signed')->name('person-documents.temporary-download');

    // ── Authenticated ────────────────────────────────────────────────────
    Route::middleware('auth:user-api')->group(function () {

        // Auth
        Route::get('/auth/me',             [AuthController::class, 'me']);
        Route::put('/auth/profile',        [AuthController::class, 'updateProfile']);
        Route::post('/auth/logout',        [AuthController::class, 'logout']);
        Route::get('/user/ai-consent',     [UserPrivacyController::class, 'consent']);
        Route::post('/user/ai-consent',    [UserPrivacyController::class, 'saveConsent']);
        Route::delete('/user/account',     [UserPrivacyController::class, 'deleteAccount']);

        // Children
        Route::apiResource('children', ChildController::class);
        Route::apiResource('person-profiles', PersonProfileController::class)->parameters(['person-profiles' => 'personProfile']);
        Route::post('/person-profiles/{personProfile}/consent', [PersonProfileController::class, 'consent']);
        Route::get('/person-profiles/{personProfile}/memories', [PersonProfileController::class, 'memories']);
        Route::get('/person-profiles/{personProfile}/documents', [PersonDocumentController::class, 'index']);
        Route::post('/person-profiles/{personProfile}/documents', [PersonDocumentController::class, 'store']);
        Route::post('/person-profiles/{personProfile}/documents/{document}/retry', [PersonDocumentController::class, 'retry'])->middleware('ai.consent');
        Route::delete('/person-profiles/{personProfile}/documents/{document}', [PersonDocumentController::class, 'destroy']);
        Route::get('/support-pathways', [SupportPathwayController::class, 'index']);

        // Child documents
        Route::get( '/children/{child}/documents',        [ChildDocumentController::class, 'index']);
        Route::post('/children/{child}/documents',        [ChildDocumentController::class, 'store']);
        Route::delete('/children/{child}/documents/{document}', [ChildDocumentController::class, 'destroy']);
        Route::get('/children/{child}/documents/{document}/download', [ChildDocumentController::class, 'download'])
            ->name('child-documents.download');

        // Child memories
        Route::get(   '/children/{child}/memories',        [ChildMemoryController::class, 'index']);
        Route::post(  '/memories',                         [ChildMemoryController::class, 'store']);
        Route::put(   '/memories/{memory}',                [ChildMemoryController::class, 'update']);
        Route::delete('/memories/{memory}',                [ChildMemoryController::class, 'destroy']);

        // Conversations
        Route::get(   '/conversations',              [ConversationController::class, 'index']);
        Route::post(  '/conversations',              [ConversationController::class, 'store']);
        Route::get(   '/conversations/{conversation}',[ConversationController::class, 'show']);
        Route::delete('/conversations/{conversation}',[ConversationController::class, 'destroy']);

        // Chat
        Route::get('/chat/usage', [ChildChatController::class, 'usage']);
        Route::post('/conversations/{conversation}/chat', [ChildChatController::class, 'chat'])
            ->middleware('ai.consent');
        Route::get('/conversations/{conversation}/chat/turns/{clientMessageId}', [ChildChatController::class, 'status']);
        Route::delete('/conversations/{conversation}/chat/turns/{clientMessageId}', [ChildChatController::class, 'cancel']);

        // Chat attachments
        Route::get( '/conversations/{conversation}/attachments', [ChatAttachmentController::class, 'index']);
        Route::post('/attachments',                              [ChatAttachmentController::class, 'store'])
            ->middleware('ai.consent');
        Route::delete('/attachments/{attachment}',               [ChatAttachmentController::class, 'destroy']);
        Route::post('/attachments/{attachment}/retry', [ChatAttachmentController::class, 'retry'])->middleware('ai.consent');
        Route::get('/attachments/{attachment}/download', [ChatAttachmentController::class, 'download'])->name('attachments.download');

        // Appointments
        Route::get(  '/appointments',                    [AppointmentController::class, 'index']);
        Route::post( '/appointments',                    [AppointmentController::class, 'store']);
        Route::get(  '/appointments/{appointment}',      [AppointmentController::class, 'show']);
        Route::match(['put', 'patch'], '/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::post( '/appointments/{appointment}/cancel',[AppointmentController::class, 'cancel']);

        // Reviews
        Route::post('/reviews', [SpecialistReviewController::class, 'store']);

        // Subscriptions
        Route::get('/subscription',         [SubscriptionController::class, 'current']);
        Route::get('/subscription/history', [SubscriptionController::class, 'history']);

        // Notifications
        Route::get( '/notifications',                      [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read',  [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all',             [NotificationController::class, 'markAllRead']);
        Route::post('/devices/push-token',                 [UserDeviceController::class, 'store']);
        Route::delete('/devices/push-token',               [UserDeviceController::class, 'destroy']);

        // Knowledge (admin upload)
        Route::post(  '/knowledge',           [KnowledgeDocumentController::class, 'store']);
        Route::delete('/knowledge/{knowledgeDocument}', [KnowledgeDocumentController::class, 'destroy']);
    });
});
