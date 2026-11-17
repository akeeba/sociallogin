<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Exception;
use RuntimeException;
final class NoConstraintsGiven extends RuntimeException implements Exception
{
}
