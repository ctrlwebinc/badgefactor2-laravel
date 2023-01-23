<?php

namespace Ctrlweb\BadgeFactor2\Badgr;

use Carbon\CarbonInterface;
use Ctrlweb\BadgeFactor2\Events\BadgrTokenRefreshed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

class BadgrClient
{
    /**
     * The client ID for authentication
     *
     * @var string
     */
    protected string $clientId;

    /**
     * The client secret for authentication
     *
     * @var string
     */
    protected string $clientSecret;

    /**
     * The redirect Uri
     *
     * @var string
     */
    protected string $redirectUri;

    /**
     * The Badgr Server URL
     *
     * @var string
     */
    protected string $serverUrl;

    /**
     * Scopes
     *
     * @var string
     */
    private $scopes;

    /**
     * The client instance
     *
     * @var mixed
     */
    private $httpClient;

    /**
     * The Auth Provider instance
     *
     * @var mixed
     */
    protected $authProvider;

    protected $accessToken;


    /**
     * BadgrClient constructor
     * 
     * @param string $clientId The client ID for authentication
     * @param string $clientSecret The client secret for authentication
     * @return void
     */
    public function __construct(string $clientId, string $clientSecret, string $redirectUri, string $serverUrl, string $scopes = "")
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->serverUrl = $serverUrl;
        $this->scopes = $scopes;

        $this->authProvider = $this->makeAuthProvider();
    }

    /**
     * Get the HTTP client
     * 
     * @param array $config The configuration array
     * @param array $token The token array
     * @throws \Exception If the base URL is not set in the config array
     * @return 
     */

    public function getHttpClient(?array $accessToken = null)
    {

        if ($this->httpClient === null) {
            $this->httpClient = Http::baseUrl($this->serverUrl);
        }

        if ($accessToken) {
            if ($this->accessTokenHasExpired($accessToken)) {
                $accessToken = $this->fetchAccessTokenUsingRefreshToken($accessToken['refresh_token']);
            }

            if ($accessToken instanceof AccessTokenInterface) {
                $this->httpClient = $this->httpClient->withToken($accessToken->getToken());
            } else {
                $this->httpClient = $this->httpClient->withToken($accessToken['access_token']);
            }
        }

        return $this->httpClient;
    }

    /**
     * Make Generic Auth provider to interact with Badgr
     * OAuth 2.0 service provider, using Bearer token authentication.
     *
     * @param array $config
     * @return GenericProvider
     */
    private function makeAuthProvider(array $config = [])
    {
        if ($this->authProvider instanceof GenericProvider) {
            return $this->authProvider;
        }
        if (!$this->clientId || !$this->clientSecret) {
            throw new \Exception('Client ID & Client Secret are required.');
        }

        if (!$this->redirectUri) {
            throw new \Exception('Redirect URI is required.');
        }

        return new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $this->redirectUri,
            'urlAuthorize' =>  $this->serverUrl . '/o/authorize',
            'urlAccessToken' => $this->serverUrl . '/o/token',
            'urlResourceOwnerDetails' => $this->serverUrl . '/o/resource',
            'scopes' => $config['scopes'] ?? null,
        ]);
    }

    /**
     * Get Auth Provider
     *
     * @return GenericProvider
     */
    public function getAuthProvider()
    {
        return $this->authProvider;
    }

    public function getAuthorizationUrl()
    {
        return $this->getAuthProvider()->getAuthorizationUrl();
    }

    /**
     * Get Access Token using authorization code
     * 
     * @param string $code The authorization code
     * @return mixed access token object or array
     */
    public function getAccessTokenUsingAuthCode(string $code)
    {
        return $this->getAuthProvider()->getAccessToken('authorization_code', [
            'code' => $code,
        ]);
    }

    /**
     * Fetch new Access Token object using refresh token
     *
     * @param string $refreshToken
     * @return mixed|AccessTokenInterface
     */
    public function fetchAccessTokenUsingRefreshToken(string $refreshToken)
    {
        $accessToken = $this->getAuthProvider()
            ->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken
            ]);

        if ($accessToken instanceof AccessTokenInterface) {
            BadgrTokenRefreshed::dispatch($accessToken);
        }

        return $accessToken;
    }

    private function accessTokenHasExpired($accessToken)
    {
        if (!isset($accessToken['access_token']) && isset($accessToken['expires_at'])) {
            throw new \Exception("The provided access token is invalid");
        }

        if (!($accessToken['expires_at'] instanceof CarbonInterface)) {
            try {
                $accessToken['expires_at'] = Carbon::parse($accessToken['expires_at']);
            } catch (\Exception $e) {
                throw new \Exception("Token expiration date is not in a valid format.");
            }
        }

        return Carbon::now()->gt($accessToken['expires_at']);
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }
}