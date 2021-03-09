<?php

namespace bru\api;

use bru\api\Exceptions\BruApiClientException;
use bru\api\Exceptions\SimpleFileCacheException;
use bru\api\Http\Request;
use bru\api\Http\Stream;
use bru\api\Http\Uri;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

final class Client implements LoggerAwareInterface
{

	use LoggerAwareTrait;

	/**
	 * @var string
	 * Имя аккаунта
	 */
	private $account;

	/**
	 * @var int
	 * ID интеграции
	 */
	private $app_id;

	/**
	 * @var string
	 * Секретный ключ
	 */
	private $secret;

	/**
	 * @var string
	 * Токен
	 */
	private $token;

	/**
	 * @var bool
	 * Спать при превышении лимита запросов
	 */
	private $sleepy;

	/**
	 * @var int
	 *  Метка времени начала выполнения
	 */
	private $startTime;

	/**
	 * @var object
	 * Объект для работы с кэшем
	 */
	private $cache;

	/**
	 * @var ClientInterface
	 *  Http - клиент
	 */
	private $httpClient;

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var int
	 */
	private $port = 0;


	/**
	 * Client constructor.
	 * @param string $account Имя аккаунта
	 * @param int $app_id ID интеграции
	 * @param string $secret Секретный ключ
	 * @param false $sleepy Засыпать при превышении лимита запросов
	 * @param CacheInterface|null $cache Объект для кэширования
	 * @param ClientInterface|null $httpClient HTTP - клиент
	 * @throws BruApiClientException
	 * @throws SimpleFileCacheException
	 * @throws InvalidArgumentException
	 */
	public function __construct(string $account, int $app_id, string $secret,bool $sleepy = false, CacheInterface $cache = null, ClientInterface $httpClient = null)
	{
		if (preg_match('~\d{3}\.\d{3}\.\d{3}\.\d{3}~', $account))
		{
			$this->account = trim($account, '/');
			preg_match('~\d{3}\.\d{3}\.\d{3}\.\d{3}~', $account, $host);
			preg_match('~:\d.*~', $account, $port);
			$this->port = trim($port[0], ':') ?? 0;
			$this->host = $host[0];
		} else {
			$this->account = 'https://' . $account . '.business.ru';
			$this->host = $account . '.business.ru';
		}

		$this->app_id = $app_id;
		$this->secret = $secret;
		$this->sleepy = $sleepy;

		if (!$cache) $this->cache = new SimpleFileCache();
		else $this->cache = $cache;

		if (!$httpClient) $this->httpClient = new SimpleHttpClient();
		else $this->httpClient = $httpClient;

		if ($this->cache->has($this->getCacheKey())) {
			$this->token = $this->cache->get($this->getCacheKey());
		} else {
			$this->token = $this->getNewToken();

			if (is_array($this->token) && $this->token['status'] === 'error') {
				$this->log(LogLevel::ERROR, 'Данные для API неверные. Код ошибки: ' . $this->token['error_code'], ['account' => $account,
					'app_id' => $app_id, 'secret' => $secret]);
				throw new BruApiClientException('Данные для API неверные. Код ошибки: ' . $this->token['error_code']);
			}

			if (is_string($this->token) && (strlen($this->token) == 32)) {
				$this->cache->set($this->getCacheKey(), $this->token);
			}
		}

		$this->startTime = $this->currentTime();
	}

	/**
	 * @param string $model Модель
	 * @param array $params Параметры
	 * @return array|int|mixed|string[]
	 * @throws InvalidArgumentException
	 * @throws JsonException
	 * @throws SimpleFileCacheException|ClientExceptionInterface Получить все записи модели (с условиями в $params)
	 * Запрос всех записей модели
	 */
	public function requestAll(string $model, array $params = [])
	{
		$method = 'get';

		$temp = $params;
		$temp['count_only'] = 1;

		$request = $this->request($method, $model, $temp);
		$maxLimit = $request['result']['count'];

		if ($maxLimit > 250) {
			$this->log(LogLevel::INFO, 'Выполнение запроса: количество записей в ответе - ' . $maxLimit);
			$pages = (int)($maxLimit / 250);
			$last_limit = $maxLimit % 250;

			$result = [];
			$result['result'] = [];
			$result['status'] = [];
			$result['request_count'] = 0;

			for ($i = 0; $i < $pages; $i++) {
				$params['limit'] = 250;
				$params['page'] = ($i + 1);
				$request = $this->request($method, $model, $params);
				$result['result'] = array_merge($result['result'], $request['result']);
				$result['status'] = $request['status'];
				$result['request_count'] += (int)$request['request_count'];
			}
			if ($last_limit > 0) {
				$params['limit'] = $last_limit;
				$params['page'] = $pages + 1;
				$request = $this->request($method, $model, $params);
				$result['result'] = array_merge($result['result'], $request['result']);
				$result['status'] = $request['status'];
				$result['request_count'] += (int)$request['request_count'];
			}
			return $result;
		}

		$this->log(LogLevel::WARNING, 'Запрос с лимитом меньше 250 записей рекомендуется выполнять методом request. Текущий лимит - ' . $maxLimit);
		return $this->request($method, $model, $params);
	}

	/**
	 * @return bool
	 * Проверка уведомления с экземпляром класса
	 */
	public function checkNotification(): bool
	{
		return self::checkN($this->app_id, $this->secret);
	}

	/**
	 * @param int $app_id
	 * @param string $secret
	 * @return bool
	 * Проверка уведомления без экземпляра класса
	 */
	public static function check(int $app_id, string $secret): bool
	{
		return self::checkN($app_id, $secret);
	}


	/**
	 * @param array $data Параметры уведомления
	 * @return int|mixed|string[]|void
	 * @throws InvalidArgumentException
	 * @throws JsonException * Отправляет уведомление пользователям
	 * @throws SimpleFileCacheException * Отправляет уведомление пользователям
	 * @throws ClientExceptionInterface
	 * Отправить уведомление
	 */
	public function sendNotification(array $data)
	{
		if (!isset($data['employee_ids']) || !isset($data['header']) || !isset($data['message'])) {
			$this->log(LogLevel::ERROR, 'Недостаточно параметров для отправки уведомления', $data);
			return;
		}
		$this->log(LogLevel::INFO, 'Уведомление успешно отправлено на обработку');
		return $this->request('post', 'notifications', $data);
	}


	/**
	 * @return bool
	 * Проверяет подлинность уведомления
	 */
	private static function checkN(int $app_id, string $secret): bool
	{
		$params = [];

		if (!isset($_REQUEST['app_psw'])) return false;

		if (!isset($_REQUEST['app_id']) || ($_REQUEST['app_id'] !== $app_id)) return false;

		if (isset($_REQUEST['model'])) $params['model'] = $_REQUEST['model'];

		if (isset($_REQUEST['action'])) $params['model'] = $_REQUEST['action'];

		if (isset($_REQUEST['changes'])) $params['model'] = $_REQUEST['changes'];

		if (isset($_REQUEST['data'])) $params['data'] = $_REQUEST['data'];

		if (md5($secret . http_build_query($params)) !== $_REQUEST['app_psw']) return false;
		else return true;
	}

	/**
	 * @param string $method Метод
	 * @param string $model Модель
	 * @param array $params Параметры
	 * @return int|mixed|string[]
	 * @throws JsonException
	 * @throws SimpleFileCacheException
	 * @throws ClientExceptionInterface
	 * @throws InvalidArgumentException|BruApiClientException
	 * Запрос к API
	 */
	public function request(string $method, string $model, array $params = [])
	{
		$result = $this->sendRequest($method, $model, $params);
		//Токен не прошел
		if ($result == 401) {
			$this->token = $this->getNewToken();
			$this->cache->set($this->getCacheKey(), $this->token);
			$result = $this->sendRequest($method, $model, $params);
		}
		if ($result == 503 && $this->sleepy)
		{
			$this->rSleep($method, $model, $params);
			$result = $this->request($method, $model, $params);
		}
		if (is_array($result['result'])) $this->log(LogLevel::INFO, 'Количество записей в ответе: ' . count($result['result']));
		return $result;
	}

	/**
	 * @param string $model Модель
	 * @param string $method Метод (get, post, put, delete)
	 * @param array $params Параметры
	 * @return mixed
	 * @throws JsonException|ClientExceptionInterface
	 */
	private function sendRequest(string $method, string $model, array $params = [])
	{

		$request = new Request();
		$uri = new Uri();
		$stream = new Stream('php://temp/bruapi/request', 'w+');

		preg_match('~^\w*~', $this->account, $scheme);
		$uri = $uri->withScheme($scheme[0]);
		$uri = $uri->withHost($this->host);
		$uri = $uri->withPath('api/rest/' . $model . '.json');
		$request = $request->withRequestTarget('api/rest/' . $model . '.json');

		if (!$this->port) {
			if ($scheme === 'http') $uri = $uri->withPort(80);
			if ($scheme === 'https') $uri = $uri->withPort(443);
		} else $uri = $uri->withPort($this->port);

		if (isset($params['images']))
			if (is_array($params['images']))
				$params['images'] = json_encode($params['images'], JSON_THROW_ON_ERROR);

		$params['app_id'] = $this->app_id;
		ksort($params);
		array_walk_recursive($params, function (&$val) {
			if (is_null($val)) {
				$val = '';
			}});
		$params_string = http_build_query($params);

		$params = array();
		$params['app_psw'] = MD5($this->token . $this->secret . $params_string);

		$params_string .= '&' . http_build_query($params);

		if (strtolower($method) === 'get') {
			$request = $request->withMethod('get');
			$uri = $uri->withQuery($params_string);
		} else {
			$request = $request->withMethod($method);
			$stream->write($params_string);
		}

		$stream->seek(0);

		$request = $request->withBody($stream);
		$request = $request->withUri($uri);

		$responce = $this->httpClient->sendRequest($request);

		$status_code = $responce->getStatusCode();
		$responce->getBody()->seek(0);
		$result = $responce->getBody()->getContents();

		if ($status_code == 200) {
			$this->log(LogLevel::INFO, 'Запрос выполнен успешно', ['method' => $method, 'model' => $model, 'params' => $params]);
			$result = json_decode($result, true, 2048, JSON_THROW_ON_ERROR);
			$app_psw = $result['app_psw'];
			unset($result['app_psw']);

			if (MD5($this->token . $this->secret . json_encode($result)) == $app_psw) {
				return ($result);
			}

			$this->log(LogLevel::ERROR, 'Ошибка авторизации', ['method' => $method, 'model' => $model, 'params' => $params]);
			return ["status" => "error",
				"error_code" => "auth:1",
				"error_text" => "Ошибка авторизации"];
		}

		if ($status_code == 401) {
			$this->log(LogLevel::INFO, 'Токен просрочен');
			return 401;
		}

		if ($status_code == 503) {
			$this->log(LogLevel::INFO, 'Превышен лимит запросов');
			return 503;
		}
		return ["status" => "error", "error_code" => "http:" . $status_code];
	}

	/**
	 * @param $method
	 * @param $model
	 * @param $params
	 * Спит пока не проснется
	 * @throws BruApiClientException
	 * @throws JsonException
	 * @throws ClientExceptionInterface
	 */
	private function rSleep($method, $model, $params): void
	{
		if (($this->currentTime() - $this->startTime) > ((int)ini_get('max_execution_time') < 300) ?  ini_get('max_execution_time') : 300) {
			$this->log(LogLevel::ERROR, 'Время ожидания сброса лимита запросов превышено');
			throw new BruApiClientException('Время ожидания сброса лимита запросов превышено');
		}
		sleep(10);
		$r = $this->sendRequest('get', $model, ['count_only' => 1]);
		if ($r === 503) $this->rSleep($method, $model, $params);
	}


	/**
	 * Восстановить токен
	 */
	private function getNewToken()
	{
		$this->token = '';
		$result = $this->sendRequest('get', 'repair');

		if ($result['token'] && strlen($result['token']) === 32)
		{
			$this->log(LogLevel::INFO, 'Получен новый токен');
			return $result['token'];
		}

		$this->log(LogLevel::ERROR, 'Не удалось получить токен. Код ошибки: ');
		return [
			"status" => "error",
			"error_code" => "http: 401"
		];
	}

	/**
	 * @return string
	 * Возвращает текущую метку времени
	 */
	private function currentTime(): string
	{
		return strtotime(date("Y-m-d H:i:s"));
	}

	/**
	 * @param string $level
	 * @param string $message
	 * @param array $context
	 * Форматирует сообщение для логирования
	 */
	private function log(string $level, string $message, array $context = []): void
	{
		if ($this->logger) {
			$messageF = date("Y-m-d H:i:s") . ': ' . trim($message, '.') . '.' . PHP_EOL;
			call_user_func([$this->logger, $level], $messageF, $context);
		}
	}

	/**
	 * @return string
	 * Возвращает ключ кэша текущего приложения
	 */
	private function getCacheKey(): string
	{
		return md5($this->account . $this->app_id);
	}
}
