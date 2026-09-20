<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert behaviour, not assets. Keeping Vite out of the
        // test run means the suite never depends on a fresh `npm run build`.
        $this->withoutVite();
    }
}
