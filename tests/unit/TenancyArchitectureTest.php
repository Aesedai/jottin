<?php

namespace Tests\Unit;

use App\Models\ApiCredentialLookup;
use App\Models\DeliveryQueue;
use App\Models\TenantModel;
use App\Models\TenantScopedModel;
use App\Models\UserLoginLookup;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps the isolation rules from eroding: a change that adds an unscoped model or
 * direct database access fails CI until someone deliberately allowlists it here.
 */
final class TenancyArchitectureTest extends TestCase
{
    /** Models allowed to bypass the tenant scope, each needing a stated reason. */
    private const UNSCOPED_MODELS = [
        TenantModel::class, // The tenants table is the root of isolation.
        UserLoginLookup::class, // At sign-in the tenant isn't known yet; it comes from this lookup.
        ApiCredentialLookup::class, // API auth derives the tenant from the key, so it can't be known first.
        DeliveryQueue::class, // The worker finds the next tenant's work; the work itself runs scoped.
    ];

    /** Files allowed to reach the database without a model. */
    private const DIRECT_DB_ALLOWED = [
        'Controllers/Health.php', // Runs SELECT 1 and reads no data.
        'Libraries/Transaction.php', // Opens transactions and reads no data.
        'Models/DeliveryQueue.php', // Row locking needs SQL the builder can't express.
    ];

    private const DIRECT_DB_PATTERN = '/Database::connect\(|db_connect\(|->query\(|->simpleQuery\(|->table\(/';

    /** @return list<string> Paths relative to app/ */
    private function phpFiles(string $directory): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPPATH . $directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen(APPPATH));
            }
        }

        return $files;
    }

    public function testEveryModelIsTenantScopedUnlessAllowlisted(): void
    {
        $files = $this->phpFiles('Models');

        $this->assertContains('Models/TenantScopedModel.php', $files);

        foreach ($files as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');

            if ($class === TenantScopedModel::class || in_array($class, self::UNSCOPED_MODELS, true)) {
                continue;
            }

            $this->assertTrue(
                is_subclass_of($class, TenantScopedModel::class),
                "{$class} must extend TenantScopedModel, or be allowlisted with a reason."
            );
        }
    }

    public function testNoDirectDatabaseAccessOutsideTheAllowlist(): void
    {
        $offenders = [];

        foreach (['Controllers', 'Libraries', 'Models', 'Filters', 'Commands'] as $directory) {
            if (! is_dir(APPPATH . $directory)) {
                continue;
            }

            foreach ($this->phpFiles($directory) as $file) {
                $relative = str_replace('\\', '/', $file);

                if (in_array($relative, self::DIRECT_DB_ALLOWED, true)) {
                    continue;
                }

                if (preg_match(self::DIRECT_DB_PATTERN, (string) file_get_contents(APPPATH . $file)) === 1) {
                    $offenders[] = $relative;
                }
            }
        }

        $this->assertSame([], $offenders, 'Use a TenantScopedModel, or allowlist the file with a reason.');
    }
}
