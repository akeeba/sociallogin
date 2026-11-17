<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;
final class PermittedFor implements Constraint
{
    private string $audience;
    public function __construct(string $audience)
    {
        $this->audience = $audience;
    }
    public function assert(Token $token) : void
    {
        if (!$token->isPermittedFor($this->audience)) {
            throw ConstraintViolation::error('The token is not allowed to be used by this audience', $this);
        }
    }
}
