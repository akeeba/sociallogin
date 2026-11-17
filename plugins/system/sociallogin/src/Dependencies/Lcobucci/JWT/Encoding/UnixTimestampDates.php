<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Encoding;

use DateTimeImmutable;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\ClaimsFormatter;
use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Token\RegisteredClaims;
use function array_key_exists;
final class UnixTimestampDates implements ClaimsFormatter
{
    /** @inheritdoc */
    public function formatClaims(array $claims) : array
    {
        foreach (RegisteredClaims::DATE_CLAIMS as $claim) {
            if (!array_key_exists($claim, $claims)) {
                continue;
            }
            $claims[$claim] = $this->convertDate($claims[$claim]);
        }
        return $claims;
    }
    private function convertDate(DateTimeImmutable $date) : int
    {
        return $date->getTimestamp();
    }
}
