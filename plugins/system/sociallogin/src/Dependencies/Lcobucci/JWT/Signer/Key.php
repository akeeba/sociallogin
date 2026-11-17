<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Signer;

interface Key
{
    public function contents() : string;
    public function passphrase() : string;
}
