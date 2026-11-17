<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\UnencryptedToken;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;
use function in_array;
final class HasClaimWithValue implements Constraint
{
    private string $claim;
    /** @var mixed */
    private $expectedValue;
    /** @param mixed $expectedValue */
    public function __construct(string $claim, $expectedValue)
    {
        if (in_array($claim, Token\RegisteredClaims::ALL, \true)) {
            throw CannotValidateARegisteredClaim::create($claim);
        }
        $this->claim = $claim;
        $this->expectedValue = $expectedValue;
    }
    public function assert(Token $token) : void
    {
        if (!$token instanceof UnencryptedToken) {
            throw ConstraintViolation::error('You should pass a plain token', $this);
        }
        $claims = $token->claims();
        if (!$claims->has($this->claim)) {
            throw ConstraintViolation::error('The token does not have the claim "' . $this->claim . '"', $this);
        }
        if ($claims->get($this->claim) !== $this->expectedValue) {
            throw ConstraintViolation::error('The claim "' . $this->claim . '" does not have the expected value', $this);
        }
    }
}
