<?php

namespace bru\api;

use bru\api\Exception\SimpleFileCacheException;
use bru\api\Exception\SimpleFileCacheInvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

final class SimpleFileCache implements CacheInterface
{

	/**
	 * @var string
	 * Домашняя директория библиотеки
	 */
	private $cachePath = __DIR__ . DIRECTORY_SEPARATOR . 'cache';

	/**
	 * SimpleFileCache constructor.
	 * @throws SimpleFileCacheException
	 */
	public function __construct()
	{
		if (!is_dir($this->cachePath) && !mkdir($this->cachePath))
		{
			throw new SimpleFileCacheException('Невозможно создать директорию для хранения кэша /src/cache/');
		}
	}

	/**
	 * @param string $key
	 * @param null $default
	 * @return false|mixed|string
	 * @throws SimpleFileCacheException
	 * @throws SimpleFileCacheInvalidArgumentException
	 */
	public function get($key, $default = null)
	{
		if (!is_string($key)) throw new SimpleFileCacheInvalidArgumentException('Ключ должен быть строкой');

		$cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $key;

		//Нет прав для чтения
		if (!is_readable($cacheFile))
		{
			throw new SimpleFileCacheException('Недостаточно прав для чтения кэша /src/cache/');
			return false;
		}

		//Нет кеша с полученным ключом
		if (!file_exists($cacheFile))
		{
			return false;
		}

		return file_get_contents($cacheFile);

	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param null $ttl
	 * @return bool
	 * @throws SimpleFileCacheException
	 * @throws SimpleFileCacheInvalidArgumentException
	 */
	public function set($key, $value, $ttl = null): bool
	{
		if (!is_string($key)) throw new SimpleFileCacheInvalidArgumentException('Ключ должен быть строкой');

		$cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $key;

		//Нет прав для записи
		if (!is_writable($this->cachePath))
		{
			throw new SimpleFileCacheException('Недостаточно прав для записи кэша /src/cache/');
			return false;
		}

		if (file_put_contents($cacheFile, $value)) return true;
		else return false;
	}

	/**
	 * @param string $key
	 * @return bool|void
	 * @throws SimpleFileCacheInvalidArgumentException
	 */
	public function delete($key): bool
	{
		if (!is_string($key)) throw new SimpleFileCacheInvalidArgumentException('Ключ должен быть строкой');
		$cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $key;

		if (file_exists($cacheFile)) {
			unlink($cacheFile);
		}

		return false;
	}

	public function clear()
	{
		// TODO: Implement clear() method.
	}

	public function getMultiple($keys, $default = null)
	{
		// TODO: Implement getMultiple() method.
	}

	public function setMultiple($values, $ttl = null)
	{
		// TODO: Implement setMultiple() method.
	}

	public function deleteMultiple($keys)
	{
		// TODO: Implement deleteMultiple() method.
	}

	/**
	 * @param string $key
	 * @return bool
	 * @throws SimpleFileCacheInvalidArgumentException
	 */
	public function has($key): bool
	{
		if (!is_string($key)) throw new SimpleFileCacheInvalidArgumentException('Ключ должен быть строкой');
		$cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $key;

		if (file_exists($cacheFile)) return true;
		else return false;
	}
}