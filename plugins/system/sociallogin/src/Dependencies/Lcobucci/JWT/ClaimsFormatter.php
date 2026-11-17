<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT;

interface ClaimsFormatter
{
    /**
     * @param array<string, mixed> $claims
     *
     * @return array<string, mixed>
     */
    public function formatClaims(array $claims) : array;
}
