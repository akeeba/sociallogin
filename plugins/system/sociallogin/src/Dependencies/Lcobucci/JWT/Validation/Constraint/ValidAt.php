<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;

use DateInterval;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\Clock\Clock;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;
/** @deprecated Use \Lcobucci\JWT\Validation\Constraint\LooseValidAt */
final class ValidAt implements Constraint
{
    private LooseValidAt $constraint;
    public function __construct(Clock $clock, ?DateInterval $leeway = null)
    {
        $this->constraint = new LooseValidAt($clock, $leeway);
    }
    public function assert(Token $token) : void
    {
        $this->constraint->assert($token);
    }
}
