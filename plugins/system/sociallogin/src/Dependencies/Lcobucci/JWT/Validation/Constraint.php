<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
interface Constraint
{
    /** @throws ConstraintViolation */
    public function assert(Token $token) : void;
}
