<?php

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

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


Route::group([
    'prefix'     => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
], function () {

    Route::get('/', [\App\Http\Controllers\Front\HomeController::class, 'index'])
        ->name('front.home');

    Route::post('/contact', [\App\Http\Controllers\Front\HomeController::class, 'sendMessage'])
        ->name('front.contact.send');

         Route::post('/newsletter', [\App\Http\Controllers\Front\HomeController::class, 'subscribeNewsletter'])
         ->name('front.newsletter.subscribe');

         Route::get('/catalogs', [\App\Http\Controllers\Front\HomeController::class, 'catalogs'])
    ->name('front.catalogs');
});



