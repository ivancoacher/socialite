<?php


namespace Xiaoniu\Socialite\Contracts;


interface FactoryInterface
{
    /**
     * @param string $driver
     * @return ProviderInterface
     */
    public function create(string $driver): ProviderInterface;
}
