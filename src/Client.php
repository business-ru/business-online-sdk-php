<?php

namespace bru\api;

use Exception;
use JsonException;

final class Client
{
	/**
	 * @var string
	 * Домашняя директория библиотеки
	 */
	public static $homePath = __DIR__ . DIRECTORY_SEPARATOR;

	/**
	 * @var string
	 * Имя CRM
	 */
	private $crm;

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
	 * @var resource
	 * Файл с токеном
	 */
	private $tokenFile;

	/**
	 * @var bool
	 * Спать при превышении лимита запросов
	 */
	private $sleepy;

	/**
	 * @var string
	 * Метка времени появления старого токена
	 */
	private $oldTokenTime;


	/**
	 * Client constructor.
	 * @param string $crm Имя CRM
	 * @param int $app_id ID интеграции
	 * @param string $secret Секретный ключ
	 * @param false $sleepy Засыпать при превишении лимита запросов
	 */
	public function __construct(string $crm, int $app_id, string $secret, $sleepy = false)
	{
		if (preg_match('~\d{3}\.\d{3}\.\d{3}\.\d{3}~', $crm)) {
			$this->crm = trim($crm, '/');
		} else $this->crm = 'https://' . $crm . '.business.ru';

		$this->app_id = $app_id;
		$this->secret = $secret;

		$this->sleepy = $sleepy;

		$this->storeInit();
	}

	/**
	 * @param string $model
	 * @param array $params
	 * @return array|int|mixed|string[]
	 * @throws JsonException
	 * Получить все записи модели (с условиями в $params)
	 */
	public function requestAll(string $model, array $params = [])
	{
		$method = 'get';

		$temp = $params;
		$temp['count_only'] = 1;

		$request = $this->request($method, $model, $temp);
		$maxLimit = $request['result']['count'];

		if ($maxLimit > 250) {
			$pages = (int)($maxLimit / 250);
			$last_limit = $maxLimit % 250;

			$result = [];
			$result['result'] = [];
			$result['status'] = [];
			$result['request_count'] = 0;

			for ($i = 0; $i < $pages; $i++) {
				$params['limit'] = 250;
				$params['page'] = $i + 1;
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
		} else return $this->request($method, $model, $params);
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
	 * @throws JsonException
	 * Отправляет уведомление пользователям
	 */
	public function sendNotification(array $data)
	{
		if (!isset($data['employee_ids']) || !isset($data['header']) || !isset($data['message'])) return;
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
	 * @param string $method
	 * @param string $model
	 * @param array $params
	 * @return int|mixed|string[]
	 * @throws JsonException
	 * Запрос к API
	 */
	public function request(string $method, string $model, array $params = [])
	{
		$result = $this->Sendrequest($method, $model, $params);
		if ($result == 401) {
			$this->token = $this->getNewToken();
			$this->writeToken($this->token);
			$result = $this->Sendrequest($method, $model, $params);
		}
		if ($result == 503 && $this->sleepy)
		{
			set_time_limit(300);
			$this->rSleep($method, $model, $params);
			$result = $this->request($method, $model, $params);
		}
		return $result;
	}

	/**
	 * @param string $model Модель
	 * @param string $method Метод (get, post, put, delete)
	 * @param array $params Параметры
	 * @return mixed
	 * @throws JsonException
	 */
	private function Sendrequest(string $method, string $model, array $params = [])
	{
		if (isset($params['images']))
			if (is_array($params['images']))
				$params['images'] = json_encode($params['images'], JSON_THROW_ON_ERROR);

		$params['app_id'] = $this->app_id;
		ksort($params);
		array_walk_recursive($params, function (&$val, $key) {
			if (is_null($val)) {
				$val = '';
			}
		});
		$params_string = http_build_query($params);
		$params = array();
		$params['app_psw'] = MD5($this->token . $this->secret . $params_string);

		$params_string .= '&' . http_build_query($params);
		$url = $this->crm . "/api/rest/" . $model . ".json";

		$c = curl_init();

		if ($method === 'post') {
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_POSTFIELDS, $params_string);
		} else if ($method === 'get') {
			curl_setopt($c, CURLOPT_URL, $url . '?' . $params_string);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		} else if ($method === 'put') {
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($c, CURLOPT_POSTFIELDS, $params_string);
		} else if ($method === 'delete') {
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($c, CURLOPT_POSTFIELDS, $params_string);
		}

		$result = curl_exec($c);

		$status_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
		curl_close($c);

		if ($status_code == 200) {
			$result = json_decode($result, true, 2048, JSON_THROW_ON_ERROR);
			$app_psw = $result['app_psw'];
			unset($result['app_psw']);

			if (MD5($this->token . $this->secret . json_encode($result)) == $app_psw) {
				$this->token = $result['token'];
				return ($result);
			} else {
				return ["status" => "error",
					"error_code" => "auth:1",
					"error_text" => "Ошибка авторизации"];
			}
		} elseif ($status_code == 401) {
			//Замена токена
			return 401;
		} elseif ($status_code == 503) {
			//Лимит запросов
			return 503;
		}
		else {
			return ["status" => "error",
				"error_code" => "http:" . $status_code];
		}
	}

	/**
	 * @param $method
	 * @param $model
	 * @param $params
	 * @throws JsonException
	 * Спит пока не проснется
	 */
	private function rSleep($method, $model, $params)
	{
		sleep(10);
		if (connection_aborted()) die();
		$r = $this->Sendrequest('get', $model, ['count_only' => 1]);
		if ($r == 503) $this->rSleep($method, $model, $params);
	}


	/**
	 * Инициализация файла с токеном
	 * @throws Exception
	 */
	private function storeInit(): void
	{
		if (!file_exists(self::$homePath . 'token')) {
			if (!is_readable(self::$homePath)) throw new Exception('Недостаточно прав для чтения в директории /src/');
			if (!is_writable(self::$homePath)) throw new Exception('Недостаточно прав для записи в директории /src/');
		}

		$this->tokenFile = fopen(self::$homePath . 'token', 'ab+');
		$this->readToken();
	}

	/**
	 * Получение токена
	 */
	private function readToken(): void
	{
		$ln = fgets($this->tokenFile);
		if (!$ln) {
			$this->token = $this->getNewToken();
			$this->writeToken($this->token);
			$this->oldTokenTime = $this->currentTime();
			return;
		}

		$t = explode('|', $ln);

		$this->oldTokenTime = array_shift($t);

		$this->token = array_shift($t);
	}

	/**
	 * @param string $token
	 * Пишет токен в файл
	 */
	private function writeToken(string $token): void
	{
		file_put_contents(self::$homePath . 'token', null);
		fwrite($this->tokenFile, $this->currentTime() . '|' . $token);
	}


	/**
	 * @return mixed|string[]
	 * Восстановить токен
	 */
	private function getNewToken()
	{
		$params = [];
		$params['app_id'] = $this->app_id;
		ksort($params);
		$params_string = http_build_query($params);
		$params = [];
		$params['app_psw'] = MD5($this->secret . $params_string);

		$params_string .= '&' . http_build_query($params);
		$url = $this->crm . "/api/rest/repair.json";

		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $url . '?' . $params_string);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($c);
		$status_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
		curl_close($c);

		if ($status_code == 200) {
			$result = json_decode($result, true);
			$app_psw = $result['app_psw'];
			unset($result['app_psw']);
			return $result['token'];
		} else return [
			"status" => "error",
			"error_code" => "http:" . $status_code
		];
	}

	private function currentTime(): string
	{
		return strtotime(date("Y-m-d H:i:s"));
	}

	public function __destruct()
	{
		fclose($this->tokenFile);
	}

}
