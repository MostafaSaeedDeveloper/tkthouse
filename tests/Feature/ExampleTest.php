<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_healthcheck_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }
}
