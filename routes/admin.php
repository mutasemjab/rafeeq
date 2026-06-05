<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ChildrenController;
use App\Http\Controllers\Admin\SpecialistsController;
use App\Http\Controllers\Admin\PlansController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\NotificationsController;
use App\Http\Controllers\Admin\SubscriptionsController;
use App\Http\Controllers\Admin\ActivityLogController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

defined('PAGINATION_COUNT') || define('PAGINATION_COUNT', 15);

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
], function () {

    Route::group([
        'prefix' => 'admin',
        'as'     => 'admin.',
        'middleware' => 'auth:admin',
    ], function () {

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('account/edit/{id}',   [LoginController::class, 'editlogin'])->name('login.edit');
        Route::put('account/update/{id}', [LoginController::class, 'updatelogin'])->name('login.update');

        // Users
        Route::resource('users', UserController::class);

        // Children (read-only + soft-delete restore)
        Route::get('children', [ChildrenController::class, 'index'])->name('children.index');
        Route::get('children/{child}', [ChildrenController::class, 'show'])->name('children.show');
        Route::delete('children/{child}', [ChildrenController::class, 'destroy'])->name('children.destroy');
        Route::post('children/{id}/restore', [ChildrenController::class, 'restore'])->name('children.restore');

        // Specialists
        Route::resource('specialists', SpecialistsController::class);
        Route::post('specialists/{specialist}/availabilities', [SpecialistsController::class, 'storeAvailability'])
            ->name('specialists.availabilities.store');
        Route::delete('specialists/{specialist}/availabilities/{availability}', [SpecialistsController::class, 'destroyAvailability'])
            ->name('specialists.availabilities.destroy');

        // Plans
        Route::resource('plans', PlansController::class);

        // Appointments
        Route::get('appointments', [AppointmentsController::class, 'index'])->name('appointments.index');
        Route::get('appointments/{appointment}', [AppointmentsController::class, 'show'])->name('appointments.show');
        Route::post('appointments/{appointment}/status', [AppointmentsController::class, 'updateStatus'])->name('appointments.status');

        // Knowledge Base
        Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
        Route::get('knowledge/create', [KnowledgeController::class, 'create'])->name('knowledge.create');
        Route::get('knowledge/statuses', [KnowledgeController::class, 'statuses'])->name('knowledge.statuses');
        Route::post('knowledge/openai-config', [KnowledgeController::class, 'updateAiConfig'])->name('knowledge.openai-config');
        Route::post('knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
        Route::delete('knowledge/{knowledge}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');
        Route::post('knowledge/{knowledge}/reprocess', [KnowledgeController::class, 'reprocess'])->name('knowledge.reprocess');

        // Subscriptions
        Route::get('subscriptions', [SubscriptionsController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions/{subscription}/status', [SubscriptionsController::class, 'updateStatus'])->name('subscriptions.status');

        // Activity Log
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

        // Notifications
        Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::post('notifications', [NotificationsController::class, 'store'])->name('notifications.store');
    });

    Route::group([
        'prefix' => 'admin',
        'as'     => 'admin.',
        'middleware' => 'guest:admin',
    ], function () {
        Route::get('login',  [LoginController::class, 'show_login_view'])->name('showlogin');
        Route::post('login', [LoginController::class, 'login'])->name('login');
    });

});
