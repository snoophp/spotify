<?php

namespace SnooPHP\Spotify;

use SnooPHP\Curl\Get;
use SnooPHP\Curl\Post;

/**
 * Perform raw api requests or use dedicated methods
 * 
 * Requests can be saved in a dedicated cache
 * 
 * @author Sneppy
 */
class Api
{
	/**
	 * @var string $clientId application client id
	 */
	protected $clientId;

	/**
	 * @var string $clientSecret application secret id
	 */
	protected $clientSecret;

	/**
	 * @var object $token user access token, used for api requests
	 */
	protected $token;

	/**
	 * @var string $lastResult last request result (raw)
	 */
	protected $lastResult;

	/**
	 * @var string $version api version (default: v1)
	 */
	protected $version = "v1";

	/**
	 * @var string $cacheClass cache class
	 */
	protected $cacheClass;

	/**
	 * @var string $defaultCacheClass
	 */
	protected static $defaultCacheClass = "SnooPHP\Spotify\NullCache";

	/**
	 * @const ENDPOINT spotify api endpoint
	 */
	const ENDPOINT = "https://api.spotify.com";

	/**
	 * @const ENDPOINT_ACCOUNT spotify accounts endpoint, used to retrieve tokens
	 */
	const ENDPOINT_ACCOUNT = "https://accounts.spotify.com";

	/**
	 * Create a new instance
	 */
	public function __construct()
	{
		// Set cache class
		$this->cacheClass = static::$defaultCacheClass;
	}

	/**
	 * Perform a generic query
	 * 
	 * @param string $query query string (with parameters)
	 * 
	 * @return object|bool false if fails
	 */
	public function query($query)
	{
		// If no access token, abort
		if (!$this->token || empty($this->authToken()))
		{
			error_log("Spotify API: no access token specified!");
			return false;
		}
		else
			$token = $this->authToken();
		
		// Build uri
		$uri = preg_match("/^https?:\/\//", $query) ? $query : static::ENDPOINT."/{$this->version}/{$query}";

		// Check if cached result exists
		if ($record = $this->cacheClass::fetch("$uri|$token")) return $record;

		// Make api request
		$curl = new Get($uri, ["Authorization" => $this->authToken()]);
		if ($curl->success())
		{
			// Save record in cache and return it
			$this->lastResult = $curl->content();
			return $this->cacheClass::store("$uri|$token", $this->lastResult);
		}
		else
		{
			$this->lastResult = false;
			return false;
		}
	}

	/**
	 * Get app refreshable token
	 * 
	 * @return object|false
	 */
	public function getAppToken()
	{
		// Build uri
		$uri = static::ENDPOINT_ACCOUNT."/api/token";

		// Check cache
		if ($record = $this->cacheClass::fetch($uri))
		{
			$this->token = $record;
			return $this->token;
		}

		// Make request
		$curl = new Post(
			$uri,
			http_build_query(["grant_type" => "client_credentials"]),
			["Authorization" => "Basic {$this->generateAppAuthHeader()}"]
		);
		if ($curl->success())
		{
			$this->token = $this->cacheClass::store($uri, $curl->content());
			return $this->token;
		}
		else
			return false;
	}

	/**
	 * Get authorization token header
	 * 
	 * @return string
	 */
	protected function authToken()
	{
		return $this->token->token_type." ".$this->token->access_token;
	}

	/**
	 * Generate authorization header given client id and secret
	 * 
	 * @return string
	 */
	protected function generateAppAuthHeader()
	{
		return base64_encode("{$this->clientId}:{$this->clientSecret}");
	}

	/**
	 * Create a new instance from client id and secret
	 * 
	 * @param string	$clientId		client id
	 * @param string	$clientSecret	client secret
	 * 
	 * @return Api
	 */
	public static function withClient($clientId, $clientSecret)
	{
		$api = new static();
		$api->clientId		= $clientId;
		$api->clientSecret	= $clientSecret;
		return $api;
	}
	
	/**
	 * Create a new instance from existing access token
	 * 
	 * @param string $token provided access token
	 * 
	 * @return Api
	 */
	public static function withToken($token)
	{
		$api = new static();
		$api->token = $token;
		return $api;
	}

	/**
	 * Set or get default cache class for this session
	 * 
	 * @param string|null	$defaultCacheClass	cache full classname
	 * 
	 * @return string
	 */
	public static function defaultCacheClass($defaultCacheClass = null)
	{
		if ($defaultCacheClass) static::$defaultCacheClass = $defaultCacheClass;
		return static::$defaultCacheClass;
	}
}