<?php


namespace Xiaoniu\Socialite\Providers;


use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Xiaoniu\Socialite\Exceptions\AuthorizeFailedException;
use Xiaoniu\Socialite\Exceptions\BadRequestException;
use Xiaoniu\Socialite\Exceptions\Exception;
use Xiaoniu\Socialite\Exceptions\InvalidArgumentException;
use Xiaoniu\Socialite\Exceptions\InvalidTicketException;
use Xiaoniu\Socialite\Exceptions\InvalidTokenException;
use Xiaoniu\Socialite\User;

class Feishu extends Base
{
    public const NAME = 'feishu';
    protected string $baseUrl = 'https://open.feishu.cn/open-apis';
    protected string $expiresInKey = 'refresh_expires_in';
    protected bool $isInternalApp = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->isInternalApp = ($this->config->get('app_mode') ?? $this->config->get('mode')) == 'internal';
    }

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl . '/authen/v1/index');
    }

    protected function getCodeFields(): array
    {
        return [
            'redirect_uri' => $this->redirectUrl,
            'app_id' => $this->getClientId(),
        ];
    }

    /**
     * 根据code获取app_access_token
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return $this->baseUrl . '/authen/v1/access_token';
    }

    protected function getCheckSessionUrl(): string
    {
        return $this->baseUrl . '/mina/v2/tokenLoginValidate';
    }

    /**
     * 网页获取token
     * @param string $code
     * @return array
     * @throws AuthorizeFailedException
     * @throws GuzzleException
     * @throws InvalidTicketException
     * @throws InvalidTokenException
     */
    public function tokenFromCode(string $code): array
    {
        return $this->normalizeAccessTokenResponse($this->getTokenFromCode($code));
    }

    /**
     * @param string $code
     * @return array
     * @throws AuthorizeFailedException
     * @throws InvalidTicketException
     * @throws InvalidTokenException
     * @throws GuzzleException
     */
    protected function getTokenFromCode(string $code): array
    {
        $this->configAppAccessToken();
        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'json' => [
                    'app_access_token' => $this->config->get('app_access_token'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]
        );
        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['data'])) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }

        return $this->normalizeAccessTokenResponse($response['data']);
    }

    /**
     * @param string $token
     * @return array
     * @throws GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/authen/v1/user_info',
            [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
                'query' => array_filter(
                    [
                        'user_access_token' => $token,
                    ]
                ),
            ]
        );

        $response = \json_decode($response->getBody(), true) ?? [];

        if (empty($response['data'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['data'];
    }

    /**
     * @param array $user
     * @return User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User(
            [
                'id' => $user['user_id'] ?? null,
                'name' => $user['name'] ?? null,
                'nickname' => $user['name'] ?? null,
                'avatar' => $user['avatar_url'] ?? null,
                'email' => $user['email'] ?? null,
            ]
        );
    }

    public function withInternalAppMode(): self
    {
        $this->isInternalApp = true;
        return $this;
    }

    public function withDefaultMode(): self
    {
        $this->isInternalApp = false;
        return $this;
    }

    /**
     * @param string $appTicket
     * @return $this
     * @throws InvalidArgumentException
     */
    public function withAppTicket(string $appTicket): self
    {
        $this->config->set('app_ticket', $appTicket);
        return $this;
    }

    /**
     * 设置 app_access_token 到 config 设置中
     * 应用维度授权凭证，开放平台可据此识别调用方的应用身份
     * 分内建和自建
     */
    protected function configAppAccessToken()
    {
        $url = $this->baseUrl . '/auth/v3/app_access_token/';
        $params = [
            'json' => [
                'app_id' => $this->config->get('client_id'),
                'app_secret' => $this->config->get('client_secret'),
                'app_ticket' => $this->config->get('app_ticket'),
            ],
        ];

        if ($this->isInternalApp) {
            $url = $this->baseUrl . '/auth/v3/app_access_token/internal/';
            $params = [
                'json' => [
                    'app_id' => $this->config->get('client_id'),
                    'app_secret' => $this->config->get('client_secret'),
                ],
            ];
        }
        if (!$this->isInternalApp && !$this->config->has('app_ticket')) {
            throw new InvalidTicketException('You are using default mode, please config \'app_ticket\' first');
        }

        $response = $this->getHttpClient()->post($url, $params);
        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['app_access_token'])) {
            throw new InvalidTokenException('Invalid \'app_access_token\' response', json_encode($response));
        }

        $this->config->set('app_access_token', $response['app_access_token']);
    }

    /**
     * 设置 tenant_access_token 到 config 属性中
     * 应用的企业授权凭证，开放平台据此识别调用方的应用身份和企业身份
     * 分内建和自建
     */
    protected function configTenantAccessToken()
    {
        $url = $this->baseUrl . '/auth/v3/tenant_access_token/';
        $params = [
            'json' => [
                'app_id' => $this->config->get('client_id'),
                'app_secret' => $this->config->get('client_secret'),
                'app_ticket' => $this->config->get('app_ticket'),
            ],
        ];

        if ($this->isInternalApp) {
            $url = $this->baseUrl . '/auth/v3/tenant_access_token/internal/';
            $params = [
                'json' => [
                    'app_id' => $this->config->get('client_id'),
                    'app_secret' => $this->config->get('client_secret'),
                ],
            ];
        }

        if (!$this->isInternalApp && !$this->config->has('app_ticket')) {
            throw new BadRequestException('You are using default mode, please config \'app_ticket\' first');
        }

        $response = $this->getHttpClient()->post($url, $params);
        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['tenant_access_token'])) {
            throw new AuthorizeFailedException('Invalid tenant_access_token response', $response);
        }

        $this->config->set('tenant_access_token', $response['tenant_access_token']);
    }

    /**
     * 小程序code转token
     * @param $code
     * @return array
     * @throws AuthorizeFailedException
     * @throws GuzzleException
     * @throws InvalidTicketException
     * @throws InvalidTokenException
     */
    public function code2Session($code)
    {
        return $this->normalizeAccessTokenResponse($this->checkSession($code));
    }

    /**
     * code2session
     * @param $code
     * @return array
     * @throws AuthorizeFailedException
     * @throws GuzzleException
     * @throws InvalidTicketException
     * @throws InvalidTokenException
     */
    protected function checkSession($code)
    {
        $this->configAppAccessToken();
        $response = $this->getHttpClient()->post(
            $this->getCheckSessionUrl(),
            [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->config->get('app_access_token')],
                'json' => [
                    'token' => $this->config->get('app_access_token'),
                    'code' => $code
                ],
            ]
        );
        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['data'])) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }

        return $this->normalizeAccessTokenResponse($response['data']);
    }

    /**
     * @return mixed
     * @throws AuthorizeFailedException
     * @throws BadRequestException
     * @throws GuzzleException
     */
    public function fetchAllEmployee()
    {
        $this->configTenantAccessToken();
        try {
            $response = $this->getHttpClient()->get(
                $this->baseUrl . '/ehr/v1/employees',
                [
                    'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->config->get('tenant_access_token')],
                    'query' => array_filter(
                        [
                            'status' => [2, 4],
                            'user_id_type' => 'union_id'
                        ]
                    ),
                ]
            );
        }catch(RequestException $exception){
            //接口返回400
            if($exception->getResponse()->getStatusCode() == 400){
                $response = json_decode($exception->getResponse()->getBody()->getContents(),true);
                throw new AuthorizeFailedException($response['msg'],$response);
            }
        }

        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['data'])) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }

        return $response['data'];
    }

    /**
     * 通讯录-获取部门信息
     * @return mixed
     * @throws AuthorizeFailedException
     * @throws BadRequestException
     * @throws GuzzleException
     */
    public function fetchContractDeps(){
        $this->configTenantAccessToken();
        try {
            $response = $this->getHttpClient()->get(
                $this->baseUrl . '/contact/v3/departments',
                [
                    'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->config->get('tenant_access_token')],
                    'query' => array_filter(
                        [
                            'status' => [2, 4],
                            'user_id_type' => 'union_id'
                        ]
                    ),
                ]
            );
        }catch(RequestException $exception){
            //接口返回400
            if($exception->getResponse()->getStatusCode() == 400){
                $response = json_decode($exception->getResponse()->getBody()->getContents(),true);
                throw new AuthorizeFailedException($response['msg'],$response);
            }
        }

        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['data'])) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }

        return $response['data'];
    }

    /**
     * 通讯录-获取用户
     * @return mixed
     * @throws AuthorizeFailedException
     * @throws BadRequestException
     * @throws GuzzleException
     */
    public function fetchContractUsers(){
        $this->configTenantAccessToken();
        try {
            $response = $this->getHttpClient()->get(
                $this->baseUrl . '/contact/v3/users',
                [
                    'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->config->get('tenant_access_token')],
                    'query' => array_filter(
                        [
//                            'status' => [2, 4],
                            'user_id_type' => 'union_id'
                        ]
                    ),
                ]
            );
        }catch(RequestException $exception){
            //接口返回400
            if($exception->getResponse()->getStatusCode() == 400){
                $response = json_decode($exception->getResponse()->getBody()->getContents(),true);
                throw new AuthorizeFailedException($response['msg'],$response);
            }
        }

        $response = \json_decode($response->getBody(), true) ?? [];
        if (empty($response['data'])) {
            throw new AuthorizeFailedException('Invalid token response', $response);
        }

        return $response['data'];
    }

}
