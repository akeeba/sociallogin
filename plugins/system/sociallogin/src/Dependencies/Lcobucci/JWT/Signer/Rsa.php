<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;

use const OPENSSL_KEYTYPE_RSA;
abstract class Rsa extends OpenSSL
{
    private const MINIMUM_KEY_LENGTH = 2048;
    public final function sign(string $payload, Key $key) : string
    {
        return $this->createSignature($key->contents(), $key->passphrase(), $payload);
    }
    public final function verify(string $expected, string $payload, Key $key) : bool
    {
        return $this->verifySignature($expected, $payload, $key->contents());
    }
    protected final function guardAgainstIncompatibleKey(int $type, int $lengthInBits) : void
    {
        if ($type !== OPENSSL_KEYTYPE_RSA) {
            throw InvalidKeyProvided::incompatibleKeyType(self::KEY_TYPE_MAP[OPENSSL_KEYTYPE_RSA], self::KEY_TYPE_MAP[$type]);
        }
        if ($lengthInBits < self::MINIMUM_KEY_LENGTH) {
            throw InvalidKeyProvided::tooShort(self::MINIMUM_KEY_LENGTH, $lengthInBits);
        }
    }
}
