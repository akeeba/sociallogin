<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\NoConstraintsGiven;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\RequiredConstraintsViolated;
interface Validator
{
    /**
     * @throws RequiredConstraintsViolated
     * @throws NoConstraintsGiven
     */
    public function assert(Token $token, Constraint ...$constraints) : void;
    /** @throws NoConstraintsGiven */
    public function validate(Token $token, Constraint ...$constraints) : bool;
}
