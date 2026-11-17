<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Encoding\CannotEncodeContent;
interface Encoder
{
    /**
     * Encodes to JSON, validating the errors
     *
     * @param mixed $data
     *
     * @throws CannotEncodeContent When something goes wrong while encoding.
     */
    public function jsonEncode($data) : string;
    /**
     * Encodes to base64url
     *
     * @link http://tools.ietf.org/html/rfc4648#section-5
     */
    public function base64UrlEncode(string $data) : string;
}
