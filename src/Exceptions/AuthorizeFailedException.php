<?php


namespace Xiaoniu\Socialite\Exceptions;


class AuthorizeFailedException extends Exception
{
    /**
     * AuthorizeFailedException constructor.
     * @param string $message
     * @param $body
     */
    public function __construct(string $message, $body)
    {
        parent::__construct($message, -1);

        $this->body = (array) $body;
    }
}
