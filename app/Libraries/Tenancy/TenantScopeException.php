<?php

namespace App\Libraries\Tenancy;

use RuntimeException;

/**
 * Tenant isolation refused an operation. This always indicates a programming
 * error, so it is never caught to fall back to an unscoped query.
 */
final class TenantScopeException extends RuntimeException
{
}
