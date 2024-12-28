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

use function trim;
use function is_array;
use function is_string;
use function strlen;
use function array_merge;
use function md5;
use function http_build_query;
use function strtoupper;
use function count;
use function json_encode;
use function json_decode;
use function ksort;
use function array_walk_recursive;
use function is_null;
use function sleep;
use function date;
use function call_user_func;


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
	 * Хост
	 */
	private $host;

	/**
	 * @var int
	 * Порт
	 */
	private $port = 443;

	/**
	 * @var string
	 * Подготовленный URL
	 */
	private $url;


	/**
	 * Создание клиента API Бизнес.ру
	 * В конструктор в качестве параметров нужно обязательно передать
	 *  - имя аккаунта (например для https://a12233.business.ru/ имя аккаунта - a12233)
	 *  - ID интеграции это идентификатор, выдаваемый при подключении интеграции в CRM
	 *  - Секретный ключ это строка, состоящая из 32 символов, выдается так же при подключении интеграции в CRM
	 * Необязательные параметры:
	 *  - $sleepy - При превышении количества запросов включение данной функции даст возможность
	 *                ожидать сброса лимита и продолжить выполнение запросов к API.
	 *                true - Включить функцию
	 *                false - Отключить функцию (По умолчанию)
	 * - CacheInterface - Библиотека использует кеширование для хранения токенов. Если вы хотите
	 *                чтобы использовался ваш кэш, в качестве параметра нужно передать объект,
	 *                реализующий интерфейс CacheInterface (PSR-16). По умолчанию библиотека использует
	 *                встроенный кэш.
	 *  - ClientInterface - Если вы хотите использовать свой HTTP - клиент для запросов к API, в качестве
	 *                параметра нужно передать объект, реализующий интерфейс ClientInterface (PSR-18).
	 *                По умолчанию библиотека использует встроенный HTTP - клиент.
	 *
	 * @param string $account Имя аккаунта
	 * @param int $app_id ID интеграции
	 * @param string $secret Секретный ключ
	 * @param false $sleepy Засыпать при превышении лимита запросов
	 * @param CacheInterface|null $cache Объект для кэширования
	 * @param ClientInterface|null $httpClient HTTP - клиент
	 * @throws BruApiClientException
	 * @throws ClientExceptionInterface
	 * @throws Exceptions\SimpleFileCacheInvalidArgumentException
	 * @throws InvalidArgumentException
	 * @throws JsonException
	 * @throws SimpleFileCacheException
	 */
	public function __construct(string $account, int $app_id, string $secret, bool $sleepy = false, CacheInterface $cache = null, ClientInterface $httpClient = null)
	{
        if(filter_var($account, FILTER_VALIDATE_URL)){
            $tmpAccountData = explode(':', trim($account, '/'));
			$this->account = $account;
			$this->host = ltrim($tmpAccountData[1], '/');
            if(count($tmpAccountData) === 3){
                $this->port = $tmpAccountData[2];
            }
		}
		else {
			$this->account = 'https://' . $account . '.business.ru';
			$this->host = $account . '.business.ru';
		}

		$this->app_id = $app_id;
		$this->secret = $secret;
		$this->sleepy = $sleepy;

		$this->cache = $cache ?? new SimpleFileCache();
		$this->httpClient = $httpClient ?? new SimpleHttpClient();

		if ($this->cache->has($this->getCacheKey())) {
			$this->token = $this->cache->get($this->getCacheKey());
		} else {
			$this->token = $this->getNewToken();
			$this->cache->set($this->getCacheKey(), $this->token);
		}
	}

	/**
	 * Запрос всех записей модели.
	 * Аналогично методу request, за исключением того, что данный метод
	 * выполняет get - запрос к переданной модели и возвращает все записи,
	 * даже если они превышают лимит (250 записей). Время выполнения данного
	 * метода может занимать длительное время. Если при создании данного объекта
	 * был передан параметр $sleepy равный true, то даже при превышении лимита
	 * запросов метод будет продолжать работу до тех пор, пока не получит все записи.
	 * В качестве параметров обязательно нужно передать:
	 *  - Модель (Список всех моделей можно узнать на https://developers.business.ru/)
	 *  - Параметры (Список всех параметров можно узнать на https://developers.business.ru/)
	 * Если во время выполнения выдается ошибка Maximum execution time of 30 seconds exceeded
	 * в конфигурации php.ini нужно увеличить допустимое время работы скрипта max_execution_time
	 * Например max_execution_time = 900
	 *
	 * @param string $model Модель
	 * @param array $params Параметры
	 * @return array|int|mixed|string[]
	 * @throws InvalidArgumentException
	 * @throws JsonException
	 * @throws SimpleFileCacheException|ClientExceptionInterface Получить все записи модели (с условиями в $params)
	 * @throws BruApiClientException
	 */
	public function requestAll(string $model, array $params = [])
	{
		$method = 'GET';

		if (!isset($params['limit'])) {
			$tempParams = $params;
			$tempParams['count_only'] = 1;
			$request = $this->request($method, $model, $tempParams);

			if (!isset($request['result']['count'])) return $request;

			$maxLimit = $request['result']['count'];
		} else {
			$maxLimit = (int)$params['limit'];
		}

		if ($maxLimit > 250) {
			$this->log(LogLevel::INFO, 'Выполнение запроса: количество записей в ответе - ' . $maxLimit);

			$pages = ceil($maxLimit / 250);

			$result = [];
			$result['result'] = [];

			for ($i = 0; $i < $pages; $i++) {
				$params['page'] = $i + 1;
				$params['limit'] = 250;
				$request = $this->request($method, $model, $params);
				$result['result'] = array_merge($request['result'], $result['result']);
				$result['status'] = $request['status'];
				$result['request_count'] = $request['request_count'];
			}
			return $result;
		}

		$this->log(LogLevel::WARNING, 'Запрос с лимитом меньше 250 записей рекомендуется выполнять методом request. Текущий лимит - ' . $maxLimit);
		return $this->request($method, $model, $params);
	}

	/**
	 * Метод проверяет уведомление при работе с веб - хуками.
	 * Аналогично методу check, но качестве данных использует данные переданные
	 * при создании данного объекта (ID интеграции и секретный ключ).
	 * В случае успешной проверки возвращает true, в остальных - false
	 *
	 * @return bool
	 */
	public function checkNotification(): bool
	{
		return self::checkN($this->app_id, $this->secret);
	}

	/**
	 * Метод проверяет уведомление при работе с веб - хуками.
	 * В качестве параметра нужно передать:
	 *  - ID интеграции это идентификатор, выдаваемый при подключении интеграции в CRM
	 *  - Секретный ключ это строка, состоящая из 32 символов, выдается так же при подключении интеграции в CRM
	 * Метод аналогичен checkNotification, но для работы не нужно создавать объект.
	 *
	 * @param int $app_id
	 * @param string $secret
	 * @return bool
	 */
	public static function check(int $app_id, string $secret): bool
	{
		return self::checkN($app_id, $secret);
	}


	/**
	 * Отправить уведомление пользователям.
	 * Требуемые параметры:
	 *  - $employees - ID пользователя либо массив с ID пользователей адресатов уведомления
	 *  - $header - Заголовок уведомления
	 *  - $message - Сообщение
	 * Необязательные параметры:
	 *  - $document_id - ID прикрепляемого документа
	 *  - $model_name - Модель прикрепляемого документа
	 *  - $action - Текст ссылки на документ в уведомлении
	 *  - $seconds - Задержка в секундах, перед тем как пользователи получат уведомление
	 *
	 * Подробнее можно узнать на https://developers.business.ru/
	 *
	 * @param $employees string|array ID пользователя или пользователей
	 * @param string $header Заголовок
	 * @param string $message Сообщение
	 * @param null $document_id ID документа
	 * @param null $model_name Название модели документа
	 * @param null $action Текст ссылки на документ
	 * @param int $seconds Задержка в секундах
	 * @return int|mixed|string[]
	 * @throws BruApiClientException
	 * @throws ClientExceptionInterface
	 * @throws InvalidArgumentException
	 * @throws JsonException
	 * @throws SimpleFileCacheException
	 */
	public function sendNotificationSystem(array $employees, string $header, string $message, $document_id = null, $model_name = null, $action = null, $seconds = 0)
	{
		$data['employee_ids'] = $employees;
		$data['header'] = $header;
		$data['message'] = $message;
		if ($document_id) $data['document_id'] = $document_id;
		if ($model_name) $data['model_name'] = $model_name;
		if ($action) $data['action'] = $action;
		if ($seconds) $data['seconds'] = $seconds;

		$this->log(LogLevel::INFO, 'Уведомление успешно отправлено на обработку');
		return $this->request('post', 'notifications', $data);
	}


	/**
	 *
	 * Проверяет подлинность уведомления полученного из веб-хука
	 * Вернет true в результате успешной проверки и false в результате провала
	 *
	 * @param int $app_id ID интеграции
	 * @param string $secret Секретный ключ
	 * @return bool Результат
	 */
	private static function checkN(int $app_id, string $secret): bool
	{
		$params = [];

		if (!isset($_REQUEST['app_psw'])) return false;

		if (!isset($_REQUEST['app_id']) || ((string)$_REQUEST['app_id'] !== (string)$app_id)) return false;
		else $params['app_id'] = $_REQUEST['app_id'];

		if (isset($_REQUEST['model'])) $params['model'] = $_REQUEST['model'];

		if (isset($_REQUEST['action'])) $params['action'] = $_REQUEST['action'];

		if (isset($_REQUEST['changes'])) $params['changes'] = $_REQUEST['changes'];

		if (isset($_REQUEST['data'])) $params['data'] = $_REQUEST['data'];

		if (md5($secret . http_build_query($params)) !== $_REQUEST['app_psw']) return false;
		return true;
	}

	/**
	 * Метод позволяет выполнить запрос к API
	 * В качестве параметров нужно передать:
	 *  - Метод. Поддерживаются 4 метода (не для всех моделей) get - чтение, post - создание, put - изменение, delete - удаление
	 *  - Модель. Список всех моделей и их описание можно узнать на https://developers.business.ru/
	 *  - Параметры. Список всех возможных параметров можно узнать на https://developers.business.ru/
	 *
	 * В результате успешной работы вернет массив со статусом и результатом выполнения запроса
	 *
	 * @param string $method Метод
	 * @param string $model Модель
	 * @param array $params Параметры
	 * @return int|mixed|string[]
	 * @throws JsonException
	 * @throws SimpleFileCacheException
	 * @throws ClientExceptionInterface
	 * @throws InvalidArgumentException|BruApiClientException
	 */
	public function request(string $method, string $model, array $params = [])
	{
		$result = $this->sendRequest(strtoupper($method), $model, $params);
		//Токен не прошел
		if ($result === 401) {
			$this->token = $this->getNewToken();
			$this->cache->set($this->getCacheKey(), $this->token);
			$result = $this->sendRequest($method, $model, $params);
		}
		if ($result === 503 && $this->sleepy) {
			$result = $this->rSleep($method, $model, $params);
		}
		if (isset($result['result']) && is_array($result['result'])) {
			$this->log(LogLevel::INFO, 'Количество записей в ответе: ' . count($result['result']));
		}
		return $result;
	}

	/**
	 * Запрос к API в формате GraphQL.
	 * $data - Строка в формате GraphQL
	 *
	 * @param $data string Строка в формате GraphQL
	 * @return mixed
	 * @throws BruApiClientException
	 * @throws ClientExceptionInterface
	 * @throws Exceptions\HttpClientException
	 * @throws Exceptions\SimpleFileCacheInvalidArgumentException
	 * @throws InvalidArgumentException
	 * @throws JsonException
	 * @throws SimpleFileCacheException
	 */
	public function graphQL(string $data)
	{
		$uri = new Uri();

		$scheme = explode(':', $this->account);
		$uri = $uri->withScheme(reset($scheme));

		$uri = $uri->withScheme($scheme[0]);
		$uri = $uri->withHost($this->host);
		$uri = $uri->withPort($this->port);

		$uri = $uri->withPath('api/rest/graphql.json');

		$stream = new Stream();
		$stream->write(json_encode(['query' => $data]));
		$stream->seek(0);

		$request = new Request();
		$request = $request->withMethod('POST');
		$request = $request->withBody($stream);
		$request = $request->withProtocolVersion('1.1');
		$request = $request->withHeader('Content-Type', 'application/json');
		$request = $request->withHeader('Authorization', $this->token);
		$request = $request->withHeader('Accept-Encoding', 'gzip');
		$request = $request->withRequestTarget('api/rest/graphql.json');

		$app_psw = md5(((string)$this->app_id) . $this->secret . $this->token);
		$uri = $uri->withQuery('app_id=' . $this->app_id . '&app_psw=' . $app_psw);

		$request = $request->withUri($uri);
		$responce = $this->httpClient->sendRequest($request);
		$responce->getBody()->seek(0);
		$result = $responce->getBody()->getContents();
		$result = json_decode($result, true);
		if ($responce->getStatusCode() == 200) {
			return $result;
		} elseif ($responce->getStatusCode() == 401) {
			$this->token = $this->getNewToken();
			if (is_string($this->token) && (strlen($this->token) == 32)) {
				$this->cache->set($this->getCacheKey(), $this->token);
			} else {
				throw new BruApiClientException('Данные для API неверные.');
			}
			return $this->graphQL($data);
		}
	}

	/**
	 * Получить подотовленный URL для запроса в формате массива где
	 * 'url' => Адрес для запроса
	 * 'data' => Тело запроса (Не актуально для метода GET)
	 * @param string $method Метод
	 * @param string $model Модель
	 * @param array $params Параметры
	 */
	public function getPreparedUrl(string $method, string $model, array $params = []): array
	{
		$method = strtoupper($method);
		$result = $this->account . '/api/rest/' . $model . '.json';

		if (isset($params['images']))
			if (is_array($params['images']))
				$params['images'] = json_encode($params['images'], JSON_THROW_ON_ERROR);

		$params['app_id'] = $this->app_id;
		ksort($params);
		array_walk_recursive($params, static function (&$val) {
			if (is_null($val)) {
				$val = '';
			}
		});
		$params_string = http_build_query($params);

		$params = [];
		$params['app_psw'] = MD5($this->token . $this->secret . $params_string);

		$params_string .= '&' . http_build_query($params);


		if ($method === 'GET') $result = ['url' => $result . '?' . $params_string, 'data' => null];
		else $result = ['url' => $result, 'data' => $params_string];
		return $result;
	}


	/**
	 * Отправить запрос HTTP - клиентом
	 *
	 * @param string $model Модель
	 * @param string $method Метод (get, post, put, delete)
	 * @param array $params Параметры
	 * @return mixed
	 * @throws JsonException|ClientExceptionInterface
	 */
	private function sendRequest(string $method, string $model, array $params = [])
	{
		$method = strtoupper($method);

		$request = new Request();
		$uri = new Uri();
		$stream = new Stream('php://temp/bruapi/request', 'w+');

		$scheme = explode(':', $this->account);
		$uri = $uri->withScheme(reset($scheme));
		$uri = $uri->withHost($this->host);
		$uri = $uri->withPath('api/rest/' . $model . '.json');
		$uri = $uri->withPort($this->port);

		$request = $request->withRequestTarget('api/rest/' . $model . '.json');
		$request = $request->withMethod($method);

		if (isset($params['images']))
			if (is_array($params['images']))
				$params['images'] = json_encode($params['images'], JSON_THROW_ON_ERROR);

		$params['app_id'] = $this->app_id;
		ksort($params);
		array_walk_recursive($params, static function (&$val) {
			if (is_null($val)) {
				$val = '';
			}
		});
		$params_string = http_build_query($params);

		$params = array();
		$params['app_psw'] = MD5($this->token . $this->secret . $params_string);

		$params_string .= '&' . http_build_query($params);

		if ($method === 'GET') $uri = $uri->withQuery($params_string);
		else $stream->write($params_string);

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
	 * Если указан параметр $sleep = true в конструкторе
	 * метод будет вызывать sleep() каждый раз, когда
	 * будет превышен лимит запросов к API
	 *
	 * @param $method
	 * @param $model
	 * @param $params
	 * @return int|mixed|string[]
	 * @throws BruApiClientException
	 * @throws JsonException
	 * @throws ClientExceptionInterface
	 */
	private function rSleep($method, $model, $params)
	{
		$this->log(LogLevel::INFO, 'Превышен лимит запросов, пауза запроса - 30с');
		sleep(30);
		$result = $this->sendRequest($method, $model, $params);
		if ($result === 503) {
			return $this->rSleep($method, $model, $params);
		}
		return $result;
	}


	/**
	 * Восстановить токен
	 * Отправит запрос к API на восстановление токена
	 * в случае успеха вернет строку с токеном
	 * @return string|array Токен
	 * @throws ClientExceptionInterface
	 * @throws JsonException|BruApiClientException
	 */
	private function getNewToken(): string
	{
		$this->token = '';
		$result = $this->sendRequest('GET', 'repair');

		if (isset($result['token']) && is_string($result['token']) && strlen($result['token']) === 32) {
			$this->log(LogLevel::INFO, 'Получен новый токен');
			return $result['token'];
		}

		$errorMessage = 'Не удалось получить токен.';

		if (is_array($result) && isset($result['error_code']) && !empty($result['error_code'])) $errorMessage .= ' Код ошибки: ' . $result['error_code'];

		throw new BruApiClientException($errorMessage);
	}

	/**
	 * Форматирует сообщение для логирования и записывает в лог,
	 * если был установлен логгер через метод setLogger()
	 * @param string $level Уровень важности
	 * @param string $message Сообщение
	 * @param array $context Контекст сообщения
	 */
	private function log(string $level, string $message, array $context = []): void
	{
		if ($this->logger) {
			$messageF = date("Y-m-d H:i:s") . ': ' . trim($message, '.') . '.' . PHP_EOL;
			call_user_func([$this->logger, $level], $messageF, $context);
		}
	}

	/**
	 * Возвращает ключ кэша текущего приложения
	 * @return string
	 */
	private function getCacheKey(): string
	{
		return md5($this->account . $this->app_id);
	}
}
