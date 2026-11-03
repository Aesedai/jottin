<?php

namespace App\Libraries\Delivery;

use RuntimeException;

/**
 * Its message is safe to show the user and store in delivery history.
 */
final class BlockedTarget extends RuntimeException
{
}
