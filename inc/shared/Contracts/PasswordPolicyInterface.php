<?php
// File: inc/shared/Contracts/PasswordPolicyInterface.php
declare(strict_types=1);

namespace Shared\Contracts;

interface PasswordPolicyInterface
{
    public static function getMinLength(): int;
}
