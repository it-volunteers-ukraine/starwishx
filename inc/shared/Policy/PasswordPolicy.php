<?php
// File: inc/shared/Policy/PasswordPolicy.php
declare(strict_types=1);

namespace Shared\Policy;

use Shared\Contracts\PasswordPolicyInterface;

final class PasswordPolicy implements PasswordPolicyInterface
{
    public const MIN_LENGTH = 12;

    public static function getMinLength(): int
    {
        return self::MIN_LENGTH;
    }
}
