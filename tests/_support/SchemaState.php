<?php

namespace Tests\Support;

/**
 * Whether this test run has built the schema, shared across test classes.
 */
final class SchemaState
{
    public static bool $built = false;
}
