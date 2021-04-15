<?php


namespace Xiaoniu\Socialite\Contracts;

use Xiaoniu\Socialite\User;

interface ProviderInterface
{
    public function redirect(?string $redirectUrl = null): string;

    public function userFromCode(string $code): User;

    public function userFromToken(string $token): User;
}
