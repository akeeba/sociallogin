<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;
use function hash_equals;
use function hash_hmac;
use function strlen;
abstract class Hmac implements Signer
{
    public final function sign(string $payload, Key $key) : string
    {
        $actualKeyLength = 8 * strlen($key->contents());
        $expectedKeyLength = $this->minimumBitsLengthForKey();
        if ($actualKeyLength < $expectedKeyLength) {
            throw InvalidKeyProvided::tooShort($expectedKeyLength, $actualKeyLength);
        }
        return hash_hmac($this->algorithm(), $payload, $key->contents(), \true);
    }
    public final function verify(string $expected, string $payload, Key $key) : bool
    {
        return hash_equals($expected, $this->sign($payload, $key));
    }
    public abstract function algorithm() : string;
    /** @return positive-int */
    public abstract function minimumBitsLengthForKey() : int;
}
