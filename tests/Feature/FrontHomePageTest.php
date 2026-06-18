<?php

namespace Tests\Feature;

use App\Http\Controllers\Front\HomeController;
use Illuminate\View\View;
use Tests\TestCase;

class FrontHomePageTest extends TestCase
{
    public function test_english_landing_page_view_is_composed_correctly(): void
    {
        app()->setLocale('en');

        $view = app(HomeController::class)->index();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('front.home', $view->getName());
        $this->assertFalse($view->getData()['isRtl']);
        $this->assertStringContainsString('admin/login', $view->getData()['adminLoginUrl']);
        $this->assertStringContainsString('Rafiq AI', $view->render());
        $this->assertStringContainsString('Login', $view->render());
    }

    public function test_arabic_landing_page_view_is_composed_correctly(): void
    {
        app()->setLocale('ar');

        $view = app(HomeController::class)->index();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('front.home', $view->getName());
        $this->assertTrue($view->getData()['isRtl']);
        $this->assertCount(2, $view->getData()['localeLinks']);
        $this->assertStringContainsString('رفيق AI', $view->render());
        $this->assertStringContainsString('تسجيل الدخول', $view->render());
    }
}
