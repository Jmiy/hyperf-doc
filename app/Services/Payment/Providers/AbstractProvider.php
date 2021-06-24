<?php

namespace App\Services\Payment\Providers;

use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Hyperf\HttpServer\Contract\RequestInterface as Request;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use App\Services\Payment\Contracts\Provider as ProviderContract;

abstract class AbstractProvider implements ProviderContract {

    /**
     * Application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application  $app
     */
    protected $app;

    /**
     * The HTTP request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The HTTP Client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * The currency.
     *
     * @var string
     */
    protected $currency; //币种

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected $callbackUri;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * Create a new provider instance.
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @param  string  $currency
     * @param  string  $callbackUri
     * @param  array  $guzzle
     * @return void
     */
    public function __construct($app, Request $request, $clientId, $clientSecret, $currency, $callbackUri, $guzzle = []) {
        $this->app = $app;
        $this->request = $request;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->currency = $currency;
        $this->callbackUri = $callbackUri;
    }

    /**
     * Set the request instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return $this
     */
    public function setRequest(Request $request) {
        $this->request = $request;

        return $this;
    }

    /**
     * Set the clientId.
     *
     * @param  string  $clientId
     * @return $this
     */
    public function setClientId($clientId) {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * Set the clientSecret.
     *
     * @param  string  $clientSecret
     * @return $this
     */
    public function setClientSecret($clientSecret) {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * Set the currency.
     *
     * @param  string  $currency
     * @return $this
     */
    public function setCurrency($currency) {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Set the callbackUri.
     *
     * @param  string  $callbackUri
     * @return $this
     */
    public function setCallbackUri($callbackUri) {
        $this->callbackUri = $callbackUri;

        return $this;
    }

    /**
     * Get the pay URL for the provider.
     *
     * @param  string  $state
     * @return string
     */
    abstract protected function getPayUrl($orderData);

    /**
     * Redirect the user of the application to the provider's authentication screen.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect($orderData) {
//        $this->request->session()->put('state', 'state');
//
//        $state = $this->request->session()->pull('state');
        //dd($this->getPayUrl($orderData));

        //return header("Location: " . $this->getPayUrl($orderData));

        return new RedirectResponse($this->formatUrl($this->getPayUrl($orderData)));
    }

    /**
     * Build URL.
     *
     * @param  string  $url
     * @param  string  $state
     * @return string
     */
    public function buildUrl($uri, $parameters) {
        return $uri . '?' . http_build_query($parameters, '', '&');
    }

    /**
     * Format the callback URL, resolving a relative URI if needed.
     *
     * @param  array  $config
     * @return string
     */
    public function formatUrl($url) {
        return Str::startsWith($url, '/') ? $this->app->make('url')->to($url) : $url;
    }

    /**
     * {@inheritdoc}
     */
    public function user() {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $user = $this->mapUserToObject($this->getUserByToken(
                        $token = Arr::get($response, 'access_token')
        ));

        return $user->setToken($token)
                        ->setRefreshToken(Arr::get($response, 'refresh_token'))
                        ->setExpiresIn(Arr::get($response, 'expires_in'));
    }

    /**
     * Get the access token response for the given code.
     *
     * @param  string  $code
     * @return array
     */
    public function getAccessTokenResponse($code) {
        $response = $this->getHttpClient()->post(
                $this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenFields($code) {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];
    }

    /**
     * Set the redirect URL.
     *
     * @param  string  $url
     * @return $this
     */
    public function redirectUrl($url) {
        $this->redirectUrl = $url;

        return $this;
    }

    /**
     * Get a instance of the Guzzle HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new Client($this->guzzle);
        }

        return $this->httpClient;
    }

    /**
     * Set the Guzzle HTTP client instance.
     *
     * @param  \GuzzleHttp\Client  $client
     * @return $this
     */
    public function setHttpClient(Client $client) {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param  array  $parameters
     * @return $this
     */
    public function with(array $parameters) {
        $this->parameters = $parameters;

        return $this;
    }

}
