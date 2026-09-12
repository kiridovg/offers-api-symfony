<?php

namespace App\Exception;

final class OfferUnavailableException extends \RuntimeException
{
    public static function expired(): self
    {
        return new self('Offer has expired.');
    }

    public static function soldOut(): self
    {
        return new self('Offer has no available units left.');
    }
}
