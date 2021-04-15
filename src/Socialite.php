<?php


namespace Xiaoniu\Socialite;


use Xiaoniu\Socialite\Contracts\ProviderInterface;
use Xiaoniu\Socialite\Exceptions\InvalidArgumentException;

class Socialite
{
    protected Config $config;
    protected array $resolved = [];
    protected array $customCreators = [];

    protected array $providers = [
        Providers\Feishu::NAME => Providers\FeiShu::class,
    ];


    public function __construct(array $config){
        $this->config = new Config($config);
    }

    public function config(Config $config):self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @param string $name
     * @return ProviderInterface
     * @throws InvalidArgumentException
     */
    public function create(string $name): ProviderInterface
    {
        $name = strtolower($name);

        if (!isset($this->resolved[$name])) {
            $this->resolved[$name] = $this->createProvider($name);
        }

        return $this->resolved[$name];
    }

    /**
     * @return ProviderInterface[]
     */
    public function getResolvedProviders(): array
    {
        return $this->resolved;
    }

    /**
     * @param string $provider
     * @param array  $config
     *
     * @return ProviderInterface
     */
    public function buildProvider(string $provider, array $config): ProviderInterface
    {
        return new $provider($config);
    }

    /**
     * @param string $name
     * @return ProviderInterface
     * @throws InvalidArgumentException
     */
    protected function createProvider(string $name)
    {
        $config = $this->config->get($name, []);
        $provider = $config['provider'] ?? $name;

        if (isset($this->customCreators[$provider])) {
            return $this->callCustomCreator($provider, $config);
        }

        if (!$this->isValidProvider($provider)) {
            throw new InvalidArgumentException("Provider [$provider] not supported.");
        }

        return $this->buildProvider($this->providers[$provider] ?? $provider, $config);
    }

    /**
     * @param string $driver
     * @param array  $config
     *
     * @return ProviderInterface
     */
    protected function callCustomCreator(string $driver, array $config): ProviderInterface
    {
        return $this->customCreators[$driver]($config);
    }

    /**
     * @param string $provider
     *
     * @return bool
     */
    protected function isValidProvider(string $provider): bool
    {
        return isset($this->providers[$provider]) || is_subclass_of($provider, ProviderInterface::class);
    }
}
