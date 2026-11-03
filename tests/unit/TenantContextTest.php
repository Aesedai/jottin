<?php

namespace Tests\Unit;

use App\Libraries\Tenancy\TenantContext;
use App\Libraries\Tenancy\TenantScopeException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TenantContextTest extends TestCase
{
    public function testIdFailsWhenNoTenantIsResolved(): void
    {
        $this->expectException(TenantScopeException::class);

        (new TenantContext())->id();
    }

    public function testTenantCannotChangeDuringARequest(): void
    {
        $context = new TenantContext();
        $context->set(1);

        $this->expectException(TenantScopeException::class);

        $context->set(2);
    }

    public function testSettingTheSameTenantAgainIsAllowed(): void
    {
        $context = new TenantContext();
        $context->set(1);
        $context->set(1);

        $this->assertSame(1, $context->id());
    }

    public function testInvalidTenantIdIsRefused(): void
    {
        $this->expectException(TenantScopeException::class);

        (new TenantContext())->set(0);
    }

    public function testRunAsRestoresThePreviousTenantAfterAnException(): void
    {
        $context = new TenantContext();
        $context->set(1);

        try {
            $context->runAs(2, static function (): void {
                throw new RuntimeException('job failed');
            });
        } catch (RuntimeException) {
            // The job's failure is not what this test checks.
        }

        $this->assertSame(1, $context->id());
    }

    public function testRunAsLeavesNoTenantBehindWhenThereWasNone(): void
    {
        $context = new TenantContext();

        $this->assertSame(2, $context->runAs(2, static fn () => 2));
        $this->assertFalse($context->hasTenant());
    }
}
