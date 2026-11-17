<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\Constraint;

use DateInterval;
use DateTimeInterface;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\Clock\Clock;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\UnencryptedToken;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Validation\ValidAt as ValidAtInterface;
final class StrictValidAt implements ValidAtInterface
{
    private Clock $clock;
    private DateInterval $leeway;
    public function __construct(Clock $clock, ?DateInterval $leeway = null)
    {
        $this->clock = $clock;
        $this->leeway = $this->guardLeeway($leeway);
    }
    private function guardLeeway(?DateInterval $leeway) : DateInterval
    {
        if ($leeway === null) {
            return new DateInterval('PT0S');
        }
        if ($leeway->invert === 1) {
            throw LeewayCannotBeNegative::create();
        }
        return $leeway;
    }
    public function assert(Token $token) : void
    {
        if (!$token instanceof UnencryptedToken) {
            throw ConstraintViolation::error('You should pass a plain token', $this);
        }
        $now = $this->clock->now();
        $this->assertIssueTime($token, $now->add($this->leeway));
        $this->assertMinimumTime($token, $now->add($this->leeway));
        $this->assertExpiration($token, $now->sub($this->leeway));
    }
    /** @throws ConstraintViolation */
    private function assertExpiration(UnencryptedToken $token, DateTimeInterface $now) : void
    {
        if (!$token->claims()->has(Token\RegisteredClaims::EXPIRATION_TIME)) {
            throw ConstraintViolation::error('"Expiration Time" claim missing', $this);
        }
        if ($token->isExpired($now)) {
            throw ConstraintViolation::error('The token is expired', $this);
        }
    }
    /** @throws ConstraintViolation */
    private function assertMinimumTime(UnencryptedToken $token, DateTimeInterface $now) : void
    {
        if (!$token->claims()->has(Token\RegisteredClaims::NOT_BEFORE)) {
            throw ConstraintViolation::error('"Not Before" claim missing', $this);
        }
        if (!$token->isMinimumTimeBefore($now)) {
            throw ConstraintViolation::error('The token cannot be used yet', $this);
        }
    }
    /** @throws ConstraintViolation */
    private function assertIssueTime(UnencryptedToken $token, DateTimeInterface $now) : void
    {
        if (!$token->claims()->has(Token\RegisteredClaims::ISSUED_AT)) {
            throw ConstraintViolation::error('"Issued At" claim missing', $this);
        }
        if (!$token->hasBeenIssuedBefore($now)) {
            throw ConstraintViolation::error('The token was issued in the future', $this);
        }
    }
}
