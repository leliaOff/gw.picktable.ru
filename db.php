<?php
class Db {
	
	/* Строка соединения */
	private $mysqli = '';
	
	/* Конструктор */
	function __construct($server, $user, $password, $databse) {
		$this->mysqli = mysqli_connect($server, $user, $password, $databse);
		if(mysqli_connect_errno($this->mysqli)) {
			return Array('status' => 'error', 'message' => mysqli_connect_error());
		} else {
			$this->mysqli->set_charset("utf8");
			return Array('status' => 'success');
		}
	}
	/* конец Конструктор */
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * *
	*			A U T H O R I Z A T I O N				 *
	* * * * * * * * * * * * * * * * * * * * * * * * * * */
	
	/* Проверить данные сессии */
	public function checkSession($sessionId) {
		$query = 'SELECT `update`, `user_id` FROM `authorization` WHERE `key` = ?';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('s', $sessionId);
			$stmt->execute(); 
			$stmt->bind_result($update, $userId);
			$stmt->store_result();
			if($stmt->num_rows == 0) return Array('status' => 'relogin');
			$stmt->fetch();
			if(strtotime('now') - strtotime($update) > 600) return Array('status' => 'relogin');
			return Array('status' => 'success', 'result' => $userId);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Проверить данные сессии */
	
	/* Добавить данные в таблицу Authorization */
	function insertAuthorization($data) {
		$query = 'INSERT INTO `authorization`(`user_id`, `key`, `ip`, `browser`, `devices`, `update`) VALUES (?, ?, ?, ?, ?, ?)';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('ssssss', $data['user_id'], $data['key'], $data['ip'], $data['browser'], $data['devices'], $data['update']);
			$stmt->execute(); 
			$id = $this->mysqli->insert_id;
			return Array('status' => 'success', 'result' => $id);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Добавить данные в таблицу Authorization */
	
	/* Обновить данные таблицы Authorization */
	function updateAuthorizationUpdate($sessionId, $value) {
		$query = 'UPDATE `authorization` set `update` = ? WHERE `key` = ?';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('ss', $value, $sessionId);
			$stmt->execute(); 
			return Array('status' => 'success');
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Обновить данные таблицы Authorization */
	
	/* Удалить данные из таблицы Authorization */
	function deleteAuthorization($sessionId) {
		$query = 'DELETE FROM `authorization` WHERE `key` = ?';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('s', $sessionId);
			$stmt->execute(); 
			return Array('status' => 'success');
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Удалить данные из таблицы Authorization */
	
	/* Удалить данные из таблицы Authorization для пользователя */
	function deleteAuthorizationByUser($id) {
		$query = 'DELETE FROM `authorization` WHERE `user_id` = ?';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('i', $id);
			$stmt->execute(); 
			return Array('status' => 'success', 'result' => $id);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Удалить данные из таблицы Authorization  для пользователя */
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * *
	*			P U B S									 *
	* * * * * * * * * * * * * * * * * * * * * * * * * * */
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * *
	*			R E S E R V E R							 *
	* * * * * * * * * * * * * * * * * * * * * * * * * * */
	
	/* Поиск брони по Пользователю */
	function selectReserverByUserId($userId, $top, $limit, $timeStart, $timeFinish) {
		$query = "SELECT `id` FROM `reserver` as `r` WHERE `r`.`user_id` = ?";
		if($timeStart != null) $query .= " AND `r`.`time` > ?"; //Если указана начальная дата
		if($timeFinish != null) $query .= " AND `r`.`time` < ?"; //Если указана конечная дата
		$query .= " LIMIT ?, ?";
		if($stmt = $this->mysqli->prepare($query)) {
			if($timeStart != null && $timeFinish != null) $stmt->bind_param('issii', $userId, $timeStart, $timeFinish, $top, $limit);
			elseif($timeStart != null) $stmt->bind_param('isii', $userId, $timeStart, $top, $limit);
			elseif($timeFinish != null) $stmt->bind_param('isii', $userId, $timeFinish, $top, $limit);
			else $stmt->bind_param('iii', $userId, $top, $limit);
			$stmt->execute(); 
			$stmt->bind_result($id);
			$result = Array();
			while($stmt->fetch()) { $result[$id] = Array(); }
			$stmt->close();
			foreach($result as $id => $item) {
				$item = $this->selectReserverDescriptionByUserId($id);
				if($item['status'] == 'success') $result[$id] = $item['result'];
			}
			return Array('status' => 'success', 'result' => $result);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Поиск брони по Пользователю */
	
	/* Описание брони при поиске по Пользователю */
	function selectReserverDescriptionByUserId($id) {
		$query = "SELECT `r`.`id` as `id`, `r`.`time` as `time`, `r`.`confirmed` as `confirmed`,
				  `t`.`pub_id` as `pub_id`, `t`.`pub_title` as `pub_title`, `t`.`pub_map` as `pub_map`, 
				  `t`.`table_id` as `table_id`, `t`.`note` as `note`, `t`.`number` as `number`, 
				  `t`.`seat_count_min` as `seat_count_min`, `t`.`seat_count_max` as `seat_count_max`, 
				  `t`.`value_x` as `value_x`, `t`.`value_y` as `value_y`, `t`.`index` as `index` 
				  FROM `reserver` as `r`, `table_reserver` as `tr`, 
					(SELECT `pubs`.`id` as `pub_id`, `pubs`.`title` as `pub_title`, `pubs`.`map_filename` as `pub_map`,
					`tables`.`id` as `table_id`, `tables`.`note` as `note`, `tables`.`number` as `number`, `tables`.`seat_count_min` as `seat_count_min`, `tables`.`seat_count_max` as `seat_count_max`,
					`tables_coordinates`.`value_x` as `value_x`, `tables_coordinates`.`value_y` as `value_y`,
					`tables_coordinates`.`index` as `index`
					FROM `tables` LEFT JOIN `pubs` ON `pubs`.`id` = `tables`.`pub_id`
					LEFT JOIN `tables_coordinates` ON `tables`.`id` = `tables_coordinates`.`table_id`) as `t` 
				WHERE `tr`.`table_id` = `t`.`table_id` AND `tr`.`reserver_id` = `r`.`id` AND `r`.`id` = ?";
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('i', $id);
			$stmt->execute(); 
			$stmt->bind_result($id, $time, $confirmed, $pubId, $pubTitle, $pubMap, $tableId, $note, $number,
							   $seatCountMin, $seatCountMax, $valueX, $valueY, $index);
			$result = Array();
			while($stmt->fetch()) {
				//Если не существует такой брони
				if(count($result) == 0) {
					$result = Array('id' => $id, 'time' => $time, 'confirmed' => $confirmed,
									'pub_id' => $pubId, 'pub_title' => $pubTitle, 'pub_map' => $pubMap,
									'tables' => Array());
				}
				//Если не существует в брони такого стола
				if(!isset($result['tables'][$tableId])) {
					$result['tables'][$tableId] = Array('table_id' => $tableId, 'note' => $note, 'number' => $number,
														'seat_count_min' => $seatCountMin, 'seat_count_max' => $seatCountMax,
														'coordinaters' => Array());
				}
				$result['tables'][$tableId]['coordinaters'][] = Array('value_x' => $valueX, 'value_y' => $valueY, 'index' => $index);
			}
			return Array('status' => 'success', 'result' => $result);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Описание брони при поиске по Пользователю */
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * *
	*			R O L E S								 *
	* * * * * * * * * * * * * * * * * * * * * * * * * * */
	
	/* Поиск всех ролей */
	function selectRoles() {
		$query = "SELECT `name`, `title` FROM `roles`";
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->execute(); 
			$stmt->bind_result($name, $title);
			$stmt->store_result();
			$result = Array();
			while ($stmt->fetch()) {
				$result[] = Array('name' => $name, 'title' => $title);
			}
			return Array('status' => 'success', 'result' => $result);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}		 
	}
	/* конец Поиск всех ролей */
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * *
	*			U S E R S								 *
	* * * * * * * * * * * * * * * * * * * * * * * * * * */
	
	/* Поиск пользователя по телефону */
	function selectUsersByPhone($phone, $id = 0) {
		$query = "SELECT * FROM `users` WHERE `phone` = ? AND `status` = 'open'";
		if($id != 0) $query .= ' AND `id` != ?';
		if($stmt = $this->mysqli->prepare($query)) {
			if($id != 0) $stmt->bind_param('si', $phone, $id);
			else  $stmt->bind_param('s', $phone);
			$stmt->execute(); 
			$stmt->bind_result($id, $email, $phone, $name, $password, $salt, $status);
			$stmt->store_result();
			if($stmt->num_rows == 0) return false;
			$stmt->fetch();
			return Array('status' => 'success', 'result' => Array('id' => $id, 'email' => $email, 'phone' => $phone, 'name' => $name, 'password' => $password, 'salt' => $salt));
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Поиск пользователя по телефону */
	
	/* Поиск пользователя по email */
	function selectUsersByEmail($email, $id = 0) {
		$query = "SELECT * FROM `users` WHERE `email` = ? AND `status` = 'open'";
		if($id != 0) $query .= ' AND `id` != ?';
		if($stmt = $this->mysqli->prepare($query)) {
			if($id != 0) $stmt->bind_param('si', $email, $id);
			else  $stmt->bind_param('s', $email);
			$stmt->execute(); 
			$stmt->bind_result($id, $email, $phone, $name, $password, $salt, $status);
			$stmt->store_result();
			if($stmt->num_rows == 0) return false;
			$stmt->fetch();
			return Array('status' => 'success', 'result' => Array('id' => $id, 'email' => $email, 'phone' => $phone, 'name' => $name, 'password' => $password, 'salt' => $salt));
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Поиск пользователя по email */
	
	/* Поиск пользователя по ИД */
	function selectUsersById($id) {
		$query = "SELECT * FROM `users` WHERE `id` = ? AND `status` = 'open'";
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('i', $id);
			$stmt->execute(); 
			$stmt->bind_result($id, $email, $phone, $name, $password, $salt, $status);
			$stmt->store_result();
			if($stmt->num_rows == 0) return false;
			$stmt->fetch();
			return Array('status' => 'success', 'result' => Array('id' => $id, 'email' => $email, 'phone' => $phone, 'full_name' => $name, 'password' => $password, 'salt' => $salt));
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Поиск пользователя по ИД */
	
	/* Добавить данные в таблицу Users */
	function insertUsers($data) {
		$query = 'INSERT INTO `users`(`email`, `phone`, `full_name`, `password`, `salt`, `status`) VALUES (?, ?, ?, ?, ?, "open")';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('sssss', $data['email'], $data['phone'], $data['full_name'], $data['password'], $data['salt']);
			$stmt->execute(); 
			$id = $this->mysqli->insert_id;
			return Array('status' => 'success', 'result' => $id);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Добавить данные в таблицу Users */
	
	/* Изменить данные в таблице Users */
	function updateUsers($data, $id) {
		$query = 'UPDATE `users` SET `email` = ?, `phone` = ?, `full_name` = ?, `password` = ?, `salt` = ? WHERE `id` = ?';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('sssssi', $data['email'], $data['phone'], $data['full_name'], $data['password'], $data['salt'], $id);
			$stmt->execute();
			return Array('status' => 'success');
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Изменить данные в таблице Users */
	
	/* Удалить пользователя */
	function deleteUser($id) {
		$query = 'UPDATE `users` SET `status` = "delet" WHERE `id` = ?';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('i', $id);
			$stmt->execute(); 
			return Array('status' => 'success', 'result' => $id);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Удалить пользователя */
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * *
	*			U S E R S   I N   R O L E S				 *
	* * * * * * * * * * * * * * * * * * * * * * * * * * */
	
	/* Добавить данные в таблицу UsersInRoles */
	function insertUsersInRoles($userId, $roleName) {
		$query = "SELECT `id` FROM `roles` WHERE `name` = ?";
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('s', $roleName);
			$stmt->execute(); 
			$stmt->bind_result($roleId);
			$stmt->fetch();
			$stmt->close();
			$isExist = $this->selectUsersInRolesByUserRole($userId, $roleId);
			if($isExist) return $isExist['result'];
			$query = 'INSERT INTO `users_in_roles`(`user_id`, `role_id`) VALUES (?, ?)';
			if($stmt = $this->mysqli->prepare($query)) {
				$stmt->bind_param('ii', $userId, $roleId);
				$stmt->execute();
				return Array('status' => 'success', 'result' => $this->mysqli->insert_id);
			} else {
				return Array('status' => 'error', 'message' => $this->mysqli->error);
			}
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Добавить данные в таблицу UsersInRoles */
	
	/* Удалить данные из таблицы UsersInRoles */
	function deleteUsersInRoles($userId, $roleName) {
		$query = "SELECT `id` FROM `roles` WHERE `name` = ?";
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('s', $roleName);
			$stmt->execute(); 
			$stmt->bind_result($roleId);
			$stmt->fetch();
			$stmt->close();
			$isExist = $this->selectUsersInRolesByUserRole($userId, $roleId);
			if(!$isExist) return Array('status' => 'success');
			$query = 'DELETE FROM `users_in_roles` WHERE `id` = ?';
			if($stmt = $this->mysqli->prepare($query)) {
				$stmt->bind_param('i', $isExist['result']);
				$stmt->execute();
				return Array('status' => 'success');
			} else {
				return Array('status' => 'error', 'message' => $this->mysqli->error);
			}
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Удалить данные из таблицы UsersInRoles */
	
	/* Удалить данные из таблицы UsersInRoles для пользователя */
	function deleteUsersInRolesByUser($id) {
		$query = 'DELETE FROM `users_in_roles` WHERE `user_id` = ?';
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('i', $id);
			$stmt->execute(); 
			return Array('status' => 'success', 'result' => $id);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}
	}
	/* конец Удалить данные из таблицы UsersInRoles  для пользователя */
	
	/* Поиск ролей по Пользователю */
	function selectUsersInRolesByUserId($userId) {
		$query = "SELECT `roles`.`name`, `roles`.`title` FROM `roles` 
				  LEFT JOIN `users_in_roles` ON `roles`.`id` = `users_in_roles`.`role_id`
				  WHERE `users_in_roles`.`user_id` = ?";
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('i', $userId);
			$stmt->execute(); 
			$stmt->bind_result($name, $title);
			$stmt->store_result();
			$result = Array();
			while ($stmt->fetch()) {
				$result[] = Array('name' => $name, 'title' => $title);
			}
			return Array('status' => 'success', 'result' => $result);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}		 
	}
	/* конец Поиск ролей по Пользователю */
	
	/* Поиск соответствия пользрвателя и роли */
	function selectUsersInRolesByUserRole($userId, $roleId) {
		$query = "SELECT `id` FROM `users_in_roles` 
				  WHERE `user_id` = ? AND `role_id` = ?";
		if($stmt = $this->mysqli->prepare($query)) {
			$stmt->bind_param('ii', $userId, $roleId);
			$stmt->execute(); 
			$stmt->bind_result($id);
			$stmt->store_result();
			if($stmt->num_rows == 0) return false;
			return Array('status' => 'success', 'result' => $id);
		} else {
			return Array('status' => 'error', 'message' => $this->mysqli->error);
		}		 
	}
	/* конец Поиск соответствия пользрвателя и роли */
    
    /* Получение спика всех пользователей */
    function getAllUsers($sessionId, $top=null, $limit=null, $search=null)
    {
       $usersQuery = "SELECT id, email, phone, full_name, status FROM users";
       if($top != null) { $usersQuery .= " TOP={$top}"; };
       if($limit != null) { $usersQuery .= " LIMIT={$LIMIT}"; };
       if($search != null) { $usersQuery .= " where full_name LIKE %{$search}%"; };
       
       $roles = function($id) {
           $rolesQuery = "SELECT * FROM roles WHERE id = (SELECT role_id FROM users_in_roles uir WHERE uir.user_id = ".$id.")";
           if($stmt = $this->mysqli->prepare($rolesQuery))
           {
                $stmt->execute(); 
                $stmt->bind_result($rid,$name,$title);
                $stmt->store_result();
                $result = Array();
                if($stmt->num_rows == 0) return 0;
                while($stmt->fetch()) {
                    $result[] = array('id'=>$rid,'name'=>$name,'title'=>$title);
                };
                return $result;
                } else {
                    return Array('status' => 'error',  'message' => "roles".$this->mysqli->error);
                };
       };
       
       if($stmt = $this->mysqli->prepare($usersQuery))
       {
            $stmt->execute(); 
            $stmt->bind_result($id,$email,$phone,$full_name,$status);
            $stmt->store_result();
            $result = Array();
            if($stmt->num_rows == 0) return false;
            while($stmt->fetch())
            {
                $result[] = Array(  'id'    => $id,
                                    'email' => $email,
                                    'phone' => $phone,
                                    'full_name' => $full_name,
                                    'status'    => $status,
                                    'roles'     => $roles($id)
                
                );
            };
       return Array('status' => 'success', 'result' => $result);
       } else {
           return Array('status' => 'error', 'message' => "usrs".$this->mysqli->error);
       };
    }
    /* конец Получение спика всех пользователей */
}

/*
SELECT `r`.`id` as `id`, `r`.`time` as `time`, `r`.`confirmed` as `confirmed`,
`t`.`pub_id` as `pub_id`, `t`.`pub_title` as `pub_title`, `t`.`pub_map` as `pub_map`, `t`.`table_id` as `table_id`, `t`.`note` as `note`, `t`.`number` as `number`, `t`.`seat_count_min` as `seat_count_min`, `t`.`seat_count_max` as `seat_count_max`, `t`.`value_x` as `value_x`, `t`.`value_y` as `value_y`, `t`.`index` as `index` 
FROM `reserver` as `r`, `table_reserver` as `tr`, 
(SELECT `pubs`.`id` as `pub_id`, `pubs`.`title` as `pub_title`, `pubs`.`map_filename` as `pub_map`,
`tables`.`id` as `table_id`, `tables`.`note` as `note`, `tables`.`number` as `number`, `tables`.`seat_count_min` as `seat_count_min`, `tables`.`seat_count_max` as `seat_count_max`,
`tables_coordinates`.`value_x` as `value_x`, `tables_coordinates`.`value_y` as `value_y`,
`tables_coordinates`.`index` as `index`
FROM `tables` LEFT JOIN `pubs` ON `pubs`.`id` = `tables`.`pub_id`
LEFT JOIN `tables_coordinates` ON `tables`.`id` = `tables_coordinates`.`table_id`) as `t` 
WHERE `tr`.`table_id` = `t`.`table_id` AND `tr`.`reserver_id` = `r`.`id` AND `r`.`user_id` = 
(SELECT `user_id` FROM `authorization` WHERE `key` = "481d2b914cd30d0528a257029af4ca7bdc1cc8a3" LIMIT 1)
*/
?>