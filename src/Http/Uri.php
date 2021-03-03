<?php

namespace bru\api\Http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{

	/**
	 * @var string
	 * Схема URI
	 */
	private $scheme;

	/**
	 * @var string
	 * Имя хоста
	 */
	private $host;

	/**
	 * @var string
	 * Имя пользователя
	 */
	private $user;

	/**
	 * @var string
	 * Пароль
	 */
	private $pass;

	/**
	 * @var int
	 * Порт
	 */
	private $port;

	/**
	 * @var string
	 * Компонент пути
	 */
	private $path;

	/**
	 * @var string
	 * Строка запроса
	 */
	private $query;

	/**
	 * @var string
	 * Фрагмент запроса
	 */
	private $fragment;

	/**
	 * @return string
	 */
	public function getScheme(): string
	{
		if ($this->scheme) return $this->scheme;
		return '';
	}

	/**
	 * @return string
	 */
	public function getAuthority(): string
	{
		//TODO проверить правильность
		$authority = '';

		if ($this->user) $authority .= $this->user;
		if ($this->pass) $authority .= ':' . $this->pass;
		if ($this->host) $authority .= '@' . $this->host;
		if ($this->port) $authority .= ':' . $this->port;

		return $authority;
	}

	/**
	 * @return string
	 */
	public function getUserInfo(): string
	{
		$user_info = '';

		if ($this->user) $user_info .= $this->user;
		if ($this->pass) $user_info .= $this->pass;

		return $user_info;
	}

	/**
	 * @return string
	 */
	public function getHost(): string
	{
		if ($this->host) return $this->host;
		return '';
	}

	/**
	 * @return int|null
	 */
	public function getPort()
	{
		if ($this->port) return $this->port;
		return null;
	}

	/**
	 * @return string
	 */
	public function getPath(): string
	{
		if ($this->path) return $this->path;
		return '';
	}

	/**
	 * @return string
	 */
	public function getQuery(): string
	{
		if ($this->query) return $this->query;
		return '/';
	}

	/**
	 * @return string
	 */
	public function getFragment(): string
	{
		if ($this->fragment) return $this->fragment;
		return '';
	}

	/**
	 * @param string $scheme
	 * @return $this
	 */
	public function withScheme($scheme): self
	{
		//TODO добавить проверку параметра
		$this->scheme = $scheme;
		return $this;
	}

	/**
	 * @param string $user
	 * @param null $password
	 * @return $this
	 */
	public function withUserInfo($user, $password = null): self
	{
		$this->user = $user;
		if ($password) $this->pass = $password;
		return $this;
	}

	/**
	 * @param string $host
	 * @return $this
	 */
	public function withHost($host): self
	{
		//TODO добавить проверку параметра
		$this->host = $host;
		return $this;
	}

	/**
	 * @param int|null $port
	 * @return $this
	 */
	public function withPort($port): self
	{
		//TODO добавить проверку параметра
		$this->port = $port;
		return $this;
	}

	/**
	 * @param string $path
	 * @return $this
	 */
	public function withPath($path): self
	{
		//TODO добавить проверку параметра
		$this->path = $path;
		return $this;
	}

	/**
	 * @param string $query
	 * @return $this
	 */
	public function withQuery($query): self
	{
		//TODO добавить проверку параметра
		$this->query = $query;
		return $this;
	}

	/**
	 * @param string $fragment
	 * @return $this
	 */
	public function withFragment($fragment): self
	{
		//TODO добавить проверку параметра
		$this->fragment = $fragment;
		return $this;

	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		//TODO добавить проверки
		$str = '';
		if ($this->scheme) $str .= $this->scheme . '://';
		if ($this->user) $str .= $this->user;
		if ($this->pass) $str .= ':' . $this->pass;
		if ($this->host) $str .= '@' . $this->host;
		if ($this->port) $str .= ':' . $this->port;
		if ($this->path) $str .= '/' . $this->path;
		if ($this->query) $str .= '?' . $this->query;
		if ($this->fragment) $str .= '#' . $this->fragment;
		return $str;
	}
}