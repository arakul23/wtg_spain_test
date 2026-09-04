<?php
 
declare(strict_types=1);
 
namespace App\Exceptions;
 
use RuntimeException;
 
class OfferNotAvailableException extends RuntimeException
{
    public function __construct(string $message = 'Offer has no available units left.')
    {
        parent::__construct($message);
    }
}