<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Rsa;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Rsa;
use const OPENSSL_ALGO_SHA256;
final class Sha256 extends Rsa
{
    public function algorithmId() : string
    {
        return 'RS256';
    }
    public function algorithm() : int
    {
        return OPENSSL_ALGO_SHA256;
    }
}
