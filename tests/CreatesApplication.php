<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        foreach ([
            $app->getCachedRoutesPath(),
            $app->getCachedConfigPath(),
        ] as $cachedBootstrapPath) {
            if (is_file($cachedBootstrapPath)) {
                @unlink($cachedBootstrapPath);
            }
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
