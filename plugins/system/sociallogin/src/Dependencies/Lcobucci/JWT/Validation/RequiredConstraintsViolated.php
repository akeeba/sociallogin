<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Exception;
use RuntimeException;
use function array_map;
use function implode;
final class RequiredConstraintsViolated extends RuntimeException implements Exception
{
    /** @var ConstraintViolation[] */
    private array $violations = [];
    public static function fromViolations(ConstraintViolation ...$violations) : self
    {
        $exception = new self(self::buildMessage($violations));
        $exception->violations = $violations;
        return $exception;
    }
    /** @param ConstraintViolation[] $violations */
    private static function buildMessage(array $violations) : string
    {
        $violations = array_map(static function (ConstraintViolation $violation) : string {
            return '- ' . $violation->getMessage();
        }, $violations);
        $message = "The token violates some mandatory constraints, details:\n";
        $message .= implode("\n", $violations);
        return $message;
    }
    /** @return ConstraintViolation[] */
    public function violations() : array
    {
        return $this->violations;
    }
}
