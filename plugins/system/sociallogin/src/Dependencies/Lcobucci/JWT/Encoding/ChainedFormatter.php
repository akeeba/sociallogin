<?php

declare (strict_types=1);
namespace Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\Encoding;

use Akeeba\Plugin\System\SocialLogin\Dependencies\Lcobucci\JWT\ClaimsFormatter;
final class ChainedFormatter implements ClaimsFormatter
{
    /** @var list<ClaimsFormatter> */
    private array $formatters;
    public function __construct(ClaimsFormatter ...$formatters)
    {
        $this->formatters = $formatters;
    }
    public static function default() : self
    {
        return new self(new UnifyAudience(), new MicrosecondBasedDateConversion());
    }
    public static function withUnixTimestampDates() : self
    {
        return new self(new UnifyAudience(), new UnixTimestampDates());
    }
    /** @inheritdoc */
    public function formatClaims(array $claims) : array
    {
        foreach ($this->formatters as $formatter) {
            $claims = $formatter->formatClaims($claims);
        }
        return $claims;
    }
}
