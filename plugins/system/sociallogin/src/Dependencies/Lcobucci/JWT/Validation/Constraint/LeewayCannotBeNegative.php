<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;

use InvalidArgumentException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Exception;
final class LeewayCannotBeNegative extends InvalidArgumentException implements Exception
{
    public static function create() : self
    {
        return new self('Leeway cannot be negative');
    }
}
