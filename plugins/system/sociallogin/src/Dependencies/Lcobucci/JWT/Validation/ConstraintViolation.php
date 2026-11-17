<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Exception;
use RuntimeException;
use function get_class;
final class ConstraintViolation extends RuntimeException implements Exception
{
    /**
     * @readonly
     * @var class-string<Constraint>|null
     */
    public ?string $constraint = null;
    public static function error(string $message, Constraint $constraint) : self
    {
        $exception = new self($message);
        $exception->constraint = get_class($constraint);
        return $exception;
    }
}
