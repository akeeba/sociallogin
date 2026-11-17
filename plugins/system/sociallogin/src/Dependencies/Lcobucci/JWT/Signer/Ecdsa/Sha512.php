<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Ecdsa;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Ecdsa;
use const OPENSSL_ALGO_SHA512;
final class Sha512 extends Ecdsa
{
    public function algorithmId() : string
    {
        return 'ES512';
    }
    public function algorithm() : int
    {
        return OPENSSL_ALGO_SHA512;
    }
    public function pointLength() : int
    {
        return 132;
    }
    public function expectedKeyLength() : int
    {
        return 521;
    }
}
