<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;
final class RelatedTo implements Constraint
{
    private string $subject;
    public function __construct(string $subject)
    {
        $this->subject = $subject;
    }
    public function assert(Token $token) : void
    {
        if (!$token->isRelatedTo($this->subject)) {
            throw ConstraintViolation::error('The token is not related to the expected subject', $this);
        }
    }
}
