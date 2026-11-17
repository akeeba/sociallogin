<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;

use InvalidArgumentException;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Exception;
final class UnsupportedHeaderFound extends InvalidArgumentException implements Exception
{
    public static function encryption() : self
    {
        return new self('Encryption is not supported yet');
    }
}
