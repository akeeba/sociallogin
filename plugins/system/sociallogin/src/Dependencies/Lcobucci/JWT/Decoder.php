<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Encoding\CannotDecodeContent;
interface Decoder
{
    /**
     * Decodes from JSON, validating the errors
     *
     * @return mixed
     *
     * @throws CannotDecodeContent When something goes wrong while decoding.
     */
    public function jsonDecode(string $json);
    /**
     * Decodes from Base64URL
     *
     * @link http://tools.ietf.org/html/rfc4648#section-5
     *
     * @throws CannotDecodeContent When something goes wrong while decoding.
     */
    public function base64UrlDecode(string $data) : string;
}
