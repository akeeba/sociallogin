<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;
final class IssuedBy implements Constraint
{
    /** @var string[] */
    private array $issuers;
    public function __construct(string ...$issuers)
    {
        $this->issuers = $issuers;
    }
    public function assert(Token $token) : void
    {
        if (!$token->hasBeenIssuedBy(...$this->issuers)) {
            throw ConstraintViolation::error('The token was not issued by the given issuers', $this);
        }
    }
}
