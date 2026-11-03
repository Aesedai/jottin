<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class HealthTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHealthReportsOkWhenDatabaseIsReachable(): void
    {
        $result = $this->get('health');

        $result->assertStatus(200);
        $result->assertJSONExact(['status' => 'ok']);
    }

    public function testLandingPageRenders(): void
    {
        $result = $this->get('/');

        $result->assertStatus(200);
        $result->assertSee('jott');
    }
}
