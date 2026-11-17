<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Encoding\CannotDecodeContent;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token\InvalidTokenStructure;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token\UnsupportedHeaderFound;
interface Parser
{
    /**
     * Parses the JWT and returns a token
     *
     * @throws CannotDecodeContent      When something goes wrong while decoding.
     * @throws InvalidTokenStructure    When token string structure is invalid.
     * @throws UnsupportedHeaderFound   When parsed token has an unsupported header.
     */
    public function parse(string $jwt) : Token;
}
