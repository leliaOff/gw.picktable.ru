<?php
class Base {
	
	/* Генерация ключа сессии */
	public function getSessionId() {
		$sessionId = '';
		for($i = 0; $i < 40; $i++) {
			$index = rand(33, 126);
			$sessionId .= chr($index);
		}
		$sessionId = sha1(sha1($sessionId) . sha1(date('YmdHis')));
		return $sessionId;
	}
	/* конец Генерация ключа сессии */
	
	/* Генерация соли */
	public function getSalt() {
		$salt = '';
		for($i = 0; $i < 5; $i++) {
			$index = rand(33, 126);
			$salt .= chr($index);
		}
		return $salt;
	}
	/* конец Генерация соли */
	
	/* Хэшируем пароль */
	public function getPasswordHash($password, $salt) {
		return sha1(sha1($salt) . $password);
	}
	/* конец Хэшируем пароль */
}