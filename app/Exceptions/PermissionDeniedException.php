<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain-level exception for permission/auth failures.
 * Must not be treated as a system error for logging purposes.
 */
class PermissionDeniedException extends RuntimeException
{
}
