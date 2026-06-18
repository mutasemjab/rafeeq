<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HomeController extends Controller
{
    public function index()
    {
        $homeUrl = route('front.home');
        $supportedLocales = LaravelLocalization::getSupportedLocales();

        $localeLinks = collect(['en', 'ar'])->map(function (string $locale) use ($homeUrl, $supportedLocales): array {
            return [
                'code' => $locale,
                'label' => strtoupper($locale),
                'native' => $supportedLocales[$locale]['native'] ?? strtoupper($locale),
                'url' => LaravelLocalization::getLocalizedURL($locale, $homeUrl),
            ];
        })->all();

        return view('front.home', [
            'adminLoginUrl' => route('admin.showlogin'),
            'homeUrl' => $homeUrl,
            'isRtl' => app()->getLocale() === 'ar',
            'localeLinks' => $localeLinks,
        ]);
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Log::info('Rafiq landing contact message received.', $data);

        return redirect()
            ->to(route('front.home').'#cta')
            ->with('status', __('landing.flash.contact_success'));
    }

    public function subscribeNewsletter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Log::info('Rafiq landing newsletter request received.', $data);

        return redirect()
            ->to(route('front.home').'#cta')
            ->with('status', __('landing.flash.newsletter_success'));
    }

    public function catalogs(): RedirectResponse
    {
        return redirect()
            ->to(route('front.home').'#platform')
            ->with('status', __('landing.flash.catalog_redirect'));
    }
}
