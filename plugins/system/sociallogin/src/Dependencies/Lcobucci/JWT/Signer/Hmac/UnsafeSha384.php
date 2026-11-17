<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Hmac;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer\Hmac;
/** @deprecated Deprecated since v4.2 */
final class UnsafeSha384 extends Hmac
{
    public function algorithmId() : string
    {
        return 'HS384';
    }
    public function algorithm() : string
    {
        return 'sha384';
    }
    public function minimumBitsLengthForKey() : int
    {
        return 1;
    }
}
