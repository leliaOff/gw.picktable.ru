<?php
	require_once dirname($_SERVER['SCRIPT_FILENAME']) . '/base.php';
	require_once dirname($_SERVER['SCRIPT_FILENAME']) . '/db.php';
	$base = new Base();
	$db = new Db('37.140.192.180', 'u0079295_default', 'VHhPV1Ny', 'u0079295_default');
	
	$method = '';
	if(isset($_GET['name'])) $method = $_GET['name'];
	if(@function_exists($method)) $result = $method();
	else $result = Array('status' => 'error', 'message' => 'Method not found');
    
    //Это что?
	function test() {
		global $base, $db;
		$sessionId = $_GET['session_id'];
		return $db->updateAuthorizationUpdate($sessionId, date('Y-m-d H:i:s'));
	}
	
	/* * * * * * * * * * А в т о р и з а ц и я ,   р а б о т а   с   п о л ь з о в а т е л я м и * * * * * * * * * */
	
	/* Анонимная авторизация */
	function anonymousAuthentication() {
		global $base, $db;
		//Проверка всех полей
		if(!isset($_GET['ip'])) return Array('status' => 'error', 'message' => 'Do not type the IP');
		//Поля
		$ip 	 = 	$_GET['ip'];
		$browser =	isset($_GET['browser']) ? $_GET['browser'] : '';
		$devices =	isset($_GET['devices']) ? $_GET['devices'] : '';
		$sessionId = $base->getSessionId();
		//Записываем в базу данных анонимного пользователя
		$result = $db->insertUsers(Array('email' => '-', 'phone' => '-', 'full_name' => '-', 'password' => '-', 'salt' => '-'));
		if($result['status'] == 'error') return $result;
		$userId = $result['result'];
		//Роль пользователя
		$db->insertUsersInRoles($userId, 'guest');
		//Записываем данные о сессии
		$result = $db->insertAuthorization(Array('user_id' => $userId, 'key' => $sessionId, 'ip' => $ip, 'browser' => $browser, 'devices' => $devices, 'update' => date('Y-m-d H:i:s')));
		if($result['status'] == 'error') return $result;
		return Array('status' => 'success', 'result' => Array('session_id' => $sessionId));
	}
	/* конец Анонимная авторизация */
	
	/* Регистрация */
	function registration() {
		global $base, $db;
		//Проверка всех полей
        //А проверку на тип данных?
		if(!isset($_GET['email'])) return Array('status' => 'error', 'message' => 'Unknown email');
		if(!isset($_GET['phone'])) return Array('status' => 'error', 'message' => 'Unknown phone');
		if(!isset($_GET['full_name'])) return Array('status' => 'error', 'message' => 'Unknown name');
		if(!isset($_GET['password'])) return Array('status' => 'error', 'message' => 'Unknown password');
		if(!isset($_GET['repeat_password'])) return Array('status' => 'error', 'message' => 'Unknown repeat password');
		if(!isset($_GET['role_name'])) return Array('status' => 'error', 'message' => 'Unknown repeat role');
		//Поля
		$email 				= $_GET['email'];
		$phone 				= $_GET['phone'];
		$fullName 			= $_GET['full_name'];
		$password 			= $_GET['password'];
		$repeatPassword 	= $_GET['repeat_password'];
		$roleName 			= $_GET['role_name'];
		//Совпадение паролей
		if($password != $repeatPassword) return Array('status' => 'error', 'message' => 'Password mismatch');
		//Поиск в базе такого же email
		$user = $db->selectUsersByEmail($email);
		if($user) return Array('status' => 'error', 'message' => 'Email already exists');
		//Поиск в базе такого же телефона
		$user = $db->selectUsersByPhone($phone);
		if($user) return Array('status' => 'error', 'message' => 'Phone already exists');
		//Генерируем соль
		$salt = $base->getSalt();
		//Хешируем пароль
		$password = $base->getPasswordHash($password, $salt);
		//Сохраняем в базе
		$result = $db->insertUsers(Array('email' => $email, 'phone' => $phone, 'full_name' => $fullName, 'password' => $password, 'salt' => $salt));
		$userId = $result['result'];
		$db->insertUsersInRoles($userId, $roleName);
		return $result;
	}
	/* конец Регистрация */
	
	/* Авторизация */
	function login() {
		global $base, $db;
		//Проверка всех полей
		if(!isset($_GET['login'])) return Array('status' => 'error', 'message' => 'Unknown login');
		if(!isset($_GET['password'])) return Array('status' => 'error', 'message' => 'Unknown password');
		if(!isset($_GET['ip'])) return Array('status' => 'error', 'message' => 'Do not type the IP');
		//Поля
		$login		= $_GET['login'];
		$password	= $_GET['password'];
		$ip 	 	= 	$_GET['ip'];
		$browser 	=	isset($_GET['browser']) ? $_GET['browser'] : '';
		$devices 	=	isset($_GET['devices']) ? $_GET['devices'] : '';
		//Поиск в базе по email
		$result = $db->selectUsersByEmail($login);
		if(!$result) {
			//Поиск в базе по телефону
			$result = $db->selectUsersByPhone($login);
			if(!$result) return Array('status' => 'error', 'message' => 'Invalid password or user not found ' . $login);
		}
		$user = $result['result'];
		$password = $base->getPasswordHash($password, $user['salt']);
		if($password != $user['password']) return Array('status' => 'error', 'message' => 'Invalid password or user not found');
		//Получаем код сессии
		$sessionId = $base->getSessionId();
		$result = $db->insertAuthorization(Array('user_id' => $user['id'], 'key' => $sessionId, 'ip' => $ip, 'browser' => $browser, 'devices' => $devices, 'update' => date('Y-m-d H:i:s')));
		if($result['status'] == 'error') return $result;
		return Array('status' => 'success', 'result' => Array('session_id' => $sessionId));
	}
	/* конец Авторизация */
	
	/* Выход */
	function logout() {
		global $db;
		//Проверка всех полей
		if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
		//Поля
		$sessionId = $_GET['session_id'];
		//Удаляем из таблицы сессию
		return $db->deleteAuthorization($sessionId);
	}
	/* конец Выход */
	
	/* Получение данных пользователя */
	function getUser() {
		global $db;
		//Проверка всех полей
		if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
		//Поля
		$sessionId = $_GET['session_id'];
		//Проверка сессии
		$session = $db->checkSession($sessionId);
		if($session['status'] != 'success') return $session;
		$userId = $session['result'];
		//Ищем пользователя
		$result = $db->selectUsersById($userId);
		if(!$result) return Array('status' => 'error', 'message' => 'User not found');
		if($result['status'] != 'success') return $result;
		unset($result['result']['password']);
		unset($result['result']['salt']);
		//Роли пользователя
		$roles = $db->selectUsersInRolesByUserId($userId);
		if($roles['status'] != 'success') return $roles;
		$result['result']['roles'] = $roles['result'];
		return $result;
	}
	/* конец Получение данных пользователя */
	
	/* Список брони пользователя (фильтр по датам) */
	function getReserverUser() {
		global $db;
		//Проверка всех полей
		if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
		//Поля
		$sessionId	= $_GET['session_id'];
		$timeStart	=	isset($_GET['time_start']) ? $_GET['time_start'] : date('Y-m-d H:i:s');
		$timeFinish	=	isset($_GET['time_finish']) ? $_GET['time_finish'] : null;
		$top		=	isset($_GET['top']) ? $_GET['top'] : 0;
		$limit		=	isset($_GET['limit']) ? $_GET['limit'] : 10;
		//Проверка сессии
		$session = $db->checkSession($sessionId);
		if($session['status'] != 'success') return $session;
		$userId = $session['result'];
		//Список брони
		$reserver = $db->selectReserverByUserId($userId, $top, $limit, $timeStart, $timeFinish);
		return $reserver;
	}
	/* конец Список брони пользователя (фильтр по датам) */
	
	/* Изменить пользователя */
	function setUser() {
		global $base, $db;
		//Проверка всех полей
		if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
		if(!isset($_GET['data']) && !isset($_GET['roles'])) return Array('status' => 'error', 'message' => 'No data');
		//Поля
		$sessionId	= $_GET['session_id'];
		$email	= $_GET['email'];
		$data	= isset($_GET['data']) ? json_decode($_GET['data']) : Array();
		$roles	= isset($_GET['roles']) ? json_decode($_GET['roles']) : Array();
		//Проверка сессии
		$session = $db->checkSession($sessionId);
		if($session['status'] != 'success') return $session;
		$userId = $session['result'];
		//Если мы изменяем пароль
		if(isset($data->password)) {
			//Совпадение паролей
			if($data->password != $data->repeat_password) return Array('status' => 'error', 'message' => 'Password mismatch');
			//Генерируем соль
			$data->salt = $base->getSalt();
			//Хешируем пароль
			$data->password = $base->getPasswordHash($data->password, $data->salt);
		}
		//Если меняется email
		if(isset($data->email)) {
			//Поиск в базе такого же email
			$result = $db->selectUsersByEmail($data->email, $userId);
			if($result) return Array('status' => 'error', 'message' => 'Email already exists');
		}
		//Если меняется телефон
		if(isset($data->phone)) {
			//Поиск в базе такого же email
			$result = $db->selectUsersByPhone($data->phone, $userId);
			if($result) return Array('status' => 'error', 'message' => 'Phone already exists');
		}
		//Получение пользователя
		$result = $db->selectUsersById($userId);
		if($result['status'] == 'error') return $result;
		$user = $result['result'];
		//Объединяем старые и новые данные
		foreach($data as $key => $item) {
			$user[$key] = $item;
		}
		//Сохраняем в базе
		$result = $db->updateUsers($user, $userId);
		return $result;
	}
	/* конец Изменить пользователя */
	
	/* Удалить пользователя */
	function removeUser() {
		global $db;
		//Проверка всех полей
		if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
		//Поля
		$sessionId	= $_GET['session_id'];
		//Проверка сессии
		$session = $db->checkSession($sessionId);
		if($session['status'] != 'success') return $session;
		$userId = $session['result'];
		//Удаляем пользователя
		$result = $db->deleteUser($userId);
		return $result;
	}
	/* конец Удалить пользователя */
	
	/* Пинг */
	function ping() {
		global $db;
		//Проверка всех полей
		if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
		//Поля
		$sessionId	= $_GET['session_id'];
		//Проверка сессии
		$session = $db->checkSession($sessionId);
		if($session['status'] != 'success') return $session;
		//Обновляем дату сессии
		return $db->updateAuthorizationUpdate($sessionId, date('Y-m-d H:i:s'));
	}
	/* конец Пинг */
	
	/* Список ролей */
	function getRoles() {
		global $db;
		//Проверка всех полей
		if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
		//Поля
		$sessionId	= $_GET['session_id'];
		//Проверка сессии
		$session = $db->checkSession($sessionId);
		if($session['status'] != 'success') return $session;
		//Получить список всех ролей
		return $db->selectRoles();
	}
	/* конец Список ролей */
    
    /* Список пользователей */
    function getUsers()
    {
        //Такой подход мне не нравится, но лучше пока что не могу предложить. НО менять этот ужос надо, однозначно!
        global $db;
        //Проверка всех полей
        if(!isset($_GET['session_id'])) return Array('status' => 'relogin');
        //Поля
        $sessionId    = $_GET['session_id'];
        //Проверка сессии
        $session = $db->checkSession($sessionId);
        if($session['status'] != 'success') return $session;
        
        //Да, я знаю, что способ передачи параметров в ф-ию ебанутый, но я ниче лучше не придумал
        $param = array();
        //Проверка полей,
        if(isset($_GET['top']))     { $param['top'] = $_GET['top']; } else { $param['top']=null; };
        if(isset($_GET['limit']))   { $param['limit'] = $_GET['limit']; } else { $param['limit']=null; };
        if(isset($_GET['search']))  { $param['search'] = $_GET['search']; } else { $param['search']=null; };
        //Получить список всех пользователей
        return $db->getAllUsers($sessionId,$param['top'],$param['limit'],$param['search']);
    }
    /* конец Список пользователей */
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * VHhPV1Ny */
	
	exit(@json_encode($result));