<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token\DataSet;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token\Signature;
interface UnencryptedToken extends Token
{
    /**
     * Returns the token claims
     */
    public function claims() : DataSet;
    /**
     * Returns the token signature
     */
    public function signature() : Signature;
    /**
     * Returns the token payload
     */
    public function payload() : string;
}
