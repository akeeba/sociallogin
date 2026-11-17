<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Rsa;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\UnsafeRsa;
use const OPENSSL_ALGO_SHA256;
/** @deprecated Deprecated since v4.2 */
final class UnsafeSha256 extends UnsafeRsa
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
