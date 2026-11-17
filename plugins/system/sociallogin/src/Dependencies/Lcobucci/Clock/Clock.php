<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\Clock;

use DateTimeImmutable;
interface Clock
{
    public function now() : DateTimeImmutable;
}
