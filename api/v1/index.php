<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* ArrestDB 1.9.0 (github.com/alixaxel/ArrestDB/)
* Copyright (c) 2014 Alix Axel <alix.axel@gmail.com>
**/

include('./log4php/Logger.php');
Logger::configure('./log4php/config.xml');
$log = Logger::getLogger('qabaselog');

$username="";
$password="";
$schema="";
$sql="";
$op="";
$key="";
$err="No error message";
$internalId= array();

//Get Username, Password and Schema from request header
foreach (getallheaders() as $name => $value) {
    //echo "$name: $value\n";
	if($name=='username'){
		$username=$value;
	}
	if($name=='password'){
		$password=$value;
	}
	if($name=='schema'){
		$schema=$value;
	}
	if($name=='sql'){
		$sql=$value;
	}
	if($name=='op'){
		$op=$value;
	}
	if($name=='key'){
		$key=$value;
	}
}

$log->info("Access: Username:[".$username."] Schema:[".$schema."]");

//DSN to configure the connection parameters
$dsn = 'mysql://'.$username.':'.$password.'@localhost/'.$schema.'/';
$clients = array
(
);

if(empty($username) || empty($password) || empty($schema)){
	$log->error("Error: Username:[".$username."] Schema:[".$schema."]. Missing Request Headers: username, password and schema are mandatory");
	$result = array
	(
		'error' => array
		(
			'code' => 403,
			'status' => 'Auth Failure',
			'message' => 'Missing Request Headers: username, password and schema are mandatory',
		),
	);
	exit(ArrestDB::Reply($result));
}


//Arrest DB Starts here
if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('ArrestDB should not be run from CLI.' . PHP_EOL);
}

if ((empty($clients) !== true) && (in_array($_SERVER['REMOTE_ADDR'], (array) $clients) !== true))
{
	$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. Access from requested IP was block. Contact your system administrator");
	$result = array
	(
		'error' => array
		(
			'code' => 403,
			'status' => 'Forbidden',
			'message' => 'Access from requested IP was block. Contact your system administrator',
		),
	);

	exit(ArrestDB::Reply($result));
}

else if (ArrestDB::Query($dsn) === false)
{
	$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. Service Unavailable: ".$GLOBALS['err']);
	$result = array
	(
		'error' => array
		(
			'code' => 503,
			'status' => 'Service Unavailable',
			'message' => $GLOBALS['err'],
		),
	);

	exit(ArrestDB::Reply($result));
}

if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

else if (array_key_exists('HTTP_X_HTTP_METHOD_OVERRIDE', $_SERVER) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

ArrestDB::Serve('GET', '/(#any)/(#any)/(#any)', function ($table, $id, $data)
{
	$query = array
	(
		sprintf('SELECT * FROM "%s"', $table),
		sprintf('WHERE "%s" %s ?', $id, (ctype_digit($data) === true) ? '=' : 'LIKE'),
	);

	if (isset($_GET['by']) === true)
	{
		if (isset($_GET['order']) !== true)
		{
			$_GET['order'] = 'ASC';
		}

		$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
	}

	if (isset($_GET['limit']) === true)
	{
		$query[] = sprintf('LIMIT %u', $_GET['limit']);

		if (isset($_GET['offset']) === true)
		{
			$query[] = sprintf('OFFSET %u', $_GET['offset']);
		}
	}

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $data);

	if ($result === false)
	{
		$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. GET Request: ".$GLOBALS['err']);
		$result = array
		(
			'error' => array
			(
				'code' => 404,
				'status' => 'Not Found',
				'message' => $GLOBALS['err'],
			),
		);
	}

	else if (empty($result) === true)
	{
		$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. GET Request: Table / Object is Empty");
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
				'message' => 'Table / Object is Empty',
			),
		);
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('GET', '/(#any)/(#num)?', function ($table, $id = null)
{
	if(empty($GLOBALS['sql'])){
		$query = array
		(
			sprintf('SELECT * FROM "%s" ', $table),
		);
	} else {
		$query = array
		(
			sprintf('%s',$GLOBALS['sql']),
		);
	}
	if (isset($id) === true)
	{
		$query[] = sprintf('WHERE "%s" = ? LIMIT 1', 'id');
	}
	else
	{
		if (isset($_GET['by']) === true)
		{
			if (isset($_GET['order']) !== true)
			{
				$_GET['order'] = 'ASC';
			}

			$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
		}

		if (isset($_GET['limit']) === true)
		{
			$query[] = sprintf('LIMIT %u', $_GET['limit']);

			if (isset($_GET['offset']) === true)
			{
				$query[] = sprintf('OFFSET %u', $_GET['offset']);
			}
		}
	}
	//print_r ($query);
	$query = sprintf('%s;', implode(' ', $query)); 
	$GLOBALS['log']->info("INFO: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. GET Request on Table[".$table."] with SQL[".$GLOBALS['sql']."]");
	$result = (isset($id) === true) ? ArrestDB::Query($query, $id) : ArrestDB::Query($query);
	//print_r ($result);
	if ($result === false)
	{
		$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. GET Request: ".$GLOBALS['err']);
		$result = array
		(
			'error' => array
			(
				'code' => 404,
				'status' => 'Not Found',
				'message' => $GLOBALS['err'],
			),
		);
	}

	else if (empty($result) === true)
	{
		$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. GET Request: Table / Object is Empty");
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
				'message' => 'Table / Object is Empty',
			),
		);
	}

	else if (isset($id) === true)
	{
		$result = array_shift($result);
	}

	return ArrestDB::Reply($result);
});


ArrestDB::Serve('DELETE', '/(#any)/(#num)', function ($table, $id)
{
	$query = array
	(
		sprintf('DELETE FROM "%s" WHERE "%s" = ?', $table, 'id'),
	);

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $id);

	if ($result === false)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 404,
				'status' => 'Not Found',
				'message' => $GLOBALS['err'],
			),
		);
	}

	else if (empty($result) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
				'message' => 'Table / Object is Empty',
			),
		);
	}

	else
	{
		$result = array
		(
			'success' => array
			(
				'code' => 200,
				'status' => 'OK',
				'message' => 'Query Processed Successfully',
			),
		);
	}

	return ArrestDB::Reply($result);
});

if (in_array($http = strtoupper($_SERVER['REQUEST_METHOD']), array('POST', 'PUT')) === true)
{
	if (preg_match('~^\x78[\x01\x5E\x9C\xDA]~', $data = file_get_contents('php://input')) > 0)
	{
		$data = gzuncompress($data);
	}

	if ((array_key_exists('CONTENT_TYPE', $_SERVER) === true) && (empty($data) !== true))
	{
		if (strncasecmp($_SERVER['CONTENT_TYPE'], 'application/json', 16) === 0)
		{
			$GLOBALS['_' . $http] = json_decode($data, true);
		}

		else if ((strncasecmp($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded', 33) === 0) && (strcasecmp($_SERVER['REQUEST_METHOD'], 'PUT') === 0))
		{
			parse_str($data, $GLOBALS['_' . $http]);
		}
	}

	if ((isset($GLOBALS['_' . $http]) !== true) || (is_array($GLOBALS['_' . $http]) !== true))
	{
		$GLOBALS['_' . $http] = array();
	}

	unset($data);
}

ArrestDB::Serve('POST', '/(#any)', function ($table)
{
//echo ($_POST);
	if (empty($_POST) === true)
	{
		$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. POST Request: Request Body is Invalid /Empty");
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
				'message' => 'Request Body is Invalid /Empty: Please send valid JSON',
			),
		);
	}
	else if ((empty($GLOBALS['op']) === true) || (($GLOBALS['op'] !== "INSERT") && ($GLOBALS['op'] !== "UPDATE") && ($GLOBALS['op'] !== "UPSERT") && ($GLOBALS['op'] !== "DELETE") ))
	{
		$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. POST Request requires valid header op with either INSERT/UPDATE/UPSERT/DELETE");
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'Invalid Operation',
				'message' => 'POST Request requires valid header \'op\' with either INSERT/UPDATE/UPSERT/DELETE',
			),
		);
	}
	else if (($GLOBALS['op'] !== "INSERT") && (empty($GLOBALS['key']) === true))
	{
		$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. POST Request requires key field to UPDATE/UPSERT/DELETE ");
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'Operation Key Missing',
				'message' => 'POST Request requires key field to UPDATE/UPSERT/DELETE',
			),
		);
	}
	else if (is_array($_POST) === true)
	{
		$queries = array();

		if (count($_POST) == count($_POST, COUNT_RECURSIVE))
		{
			$_POST = array($_POST);
		}

		foreach ($_POST as $row)
		{
			$data = array();
			$pk = array();

			foreach ($row as $key => $value)
			{
				$data[sprintf('"%s"', $key)] = $value;
				if(empty($GLOBALS['key']) === false)
				{
					if ($GLOBALS['op'] === "UPDATE")
						$updata[$key] = sprintf('"%s" = ?', $key);
					if ($GLOBALS['op'] === "UPSERT")
						$usdata[$key] = sprintf('"%s" = \'%s\'', $key , $value);
					if($GLOBALS['key'] === $key)
					{
						$pk[sprintf('"%s"', $key)] = $value;
					}
				}
			}
			//print_r ($usdata);
			//print_r ($pk);
			if ($GLOBALS['op'] === "INSERT")
			{
				$query = array
				(
					sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $table, implode(', ', array_keys($data)), implode(', ', array_fill(0, count($data), '?'))),
				);
			}
			else if ($GLOBALS['op'] === "UPDATE")
			{
				$query = array
				(
					sprintf('UPDATE "%s" SET %s WHERE %s = \'%s\'', $table, implode(', ', $updata), implode(', ', array_keys($pk)), implode(', ', $pk)),
				);
			}
			else if ($GLOBALS['op'] === "UPSERT")
			{
				$query = array
				(
					sprintf('INSERT INTO "%s" (%s) VALUES (%s) ', $table, implode(', ', array_keys($data)), implode(', ', array_fill(0, count($data), '?'))),
					sprintf('ON DUPLICATE KEY UPDATE %s', implode(', ', $usdata)),
					//sprintf('INSERT INTO "new_table" ("ID", "Name") VALUES (\'99\', \'Sha\') ON DUPLICATE KEY UPDATE "ID" = \'99\', "Name" = \'Rath\''),
					//sprintf('INSERT IGNORE "%s" (%s) VALUES (%s)', $table, implode(', ', array_keys($data)), implode(', ', array_fill(0, count($data), '?'))),
				);
			}
			else if ($GLOBALS['op'] === "DELETE")
			{
				$query = array
				(
					sprintf('DELETE FROM "%s" WHERE %s = \'%s\'', $table, implode(', ', array_keys($pk)), implode(', ', $pk)),
				);
			}

			$queries[] = array
			(
				sprintf('%s;', implode(' ', $query)),
				$data,
			);
		}
		//print_r ($queries);
		$result_full = array();
		$rowId = 0;
		$internalId = array();
		$rowCount = count($queries);
		if (count($queries) > 1)
		{
			while (is_null($query = array_shift($queries)) !== true)
			{
				$result = ArrestDB::Query($query[0], $query[1]);
				//print_r ($result);
				//Compute the result
				if (($result) === false)
				{
					array_push($result_full,$GLOBALS['err']);
				} 
				else 
				{
					if($result === 0)
					{
						array_push($result_full,'Row was not updated/deleted');
					} 
					else
					{
						array_push($result_full,'OK');
						$rowId++;
					}
				}
			}
			//print_r ($result_full);
		}
		else if (is_null($query = array_shift($queries)) !== true)
		{
			$result = ArrestDB::Query($query[0], $query[1]);
			//echo $result;
			if (($result) === false)
				{
					array_push($result_full,$GLOBALS['err']);
				} 
				else 
				{
					if($result === 0)
					{
						array_push($result_full,'Row was not updated/deleted');
					} 
					else
					{
						array_push($result_full,'OK');
						$rowId++;
					}
				}
			
		}
		$status="";
		if($rowId === $rowCount)
		{
			$status = 'PASS';
		}
		else if($rowId === 0)
		{
			$status = 'FAIL';
		} else
		{
			$status = 'WARNING';
		}
		if (empty($result_full) !== true)
		{
			$GLOBALS['log']->error("INFO: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. POST Request Query Status: ".$status." => SuccessRows:[".$rowId."] ErrorRows:[".($rowCount - $rowId)."]");
			$result = array
			(
				'code' => 200,
				'status' => $status,
				'operation' => $GLOBALS['op'],
				'successRows' => $rowId,
				'errorRows' => ($rowCount - $rowId),
				'internalID' => $GLOBALS['internalId'],
				'message' => $result_full,
			);
		}

	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('PUT', '/(#any)/(#num)', function ($table, $id)
{
	if (empty($GLOBALS['_PUT']) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
			),
		);
	}

	else if (is_array($GLOBALS['_PUT']) === true)
	{
		$data = array();

		foreach ($GLOBALS['_PUT'] as $key => $value)
		{
			$data[$key] = sprintf('"%s" = ?', $key);
		}

		$query = array
		(
			sprintf('UPDATE "%s" SET %s WHERE "%s" = ?', $table, implode(', ', $data), 'id'),
		);

		$query = sprintf('%s;', implode(' ', $query));
		//print_r ($query);
		$result = ArrestDB::Query($query, $GLOBALS['_PUT'], $id);

		if ($result === false)
		{
			$result = array
			(
				'error' => array
				(
					'code' => 409,
					'status' => 'Conflict',
				),
			);
		}

		else
		{
			$result = array
			(
				'success' => array
				(
					'code' => 200,
					'status' => 'OK',
				),
			);
		}
	}

	return ArrestDB::Reply($result);
});

$GLOBALS['log']->error("Error: Username:[".$GLOBALS['username']."] Schema:[".$GLOBALS['schema']."]. Bad Request.");
$result = array
(
	'error' => array
	(
		'code' => 400,
		'status' => 'Bad Request',
	),
);

exit(ArrestDB::Reply($result));

class ArrestDB
{
	public static function Query($query = null)
	{
		static $db = null;
		static $result = array();
		//echo $query;
		try
		{
			if (isset($db, $query) === true)
			{
				if (strncasecmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'mysql', 5) === 0)
				{
					$query = strtr($query, '"', '`');
				}

				if (empty($result[$hash = crc32($query)]) === true)
				{
					$result[$hash] = $db->prepare($query);
				}

				$data = array_slice(func_get_args(), 1);

				if (count($data, COUNT_RECURSIVE) > count($data))
				{
					$data = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data)), false);
				}
				
				if ($result[$hash]->execute($data) === true)
				{
					$sequence = null;

					if ((strncmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'pgsql', 5) === 0) && (sscanf($query, 'INSERT INTO %s', $sequence) > 0))
					{
						$sequence = sprintf('%s_id_seq', trim($sequence, '"'));
					}

					switch (strstr($query, ' ', true))
					{
						case 'INSERT':
						case 'REPLACE':
							if($db->lastInsertId($sequence) > 0) array_push($GLOBALS['internalId'],$db->lastInsertId($sequence));
							return $db->lastInsertId($sequence);

						case 'UPDATE':
						case 'DELETE':
							return $result[$hash]->rowCount();

						case 'SELECT':
						case 'EXPLAIN':
						case 'PRAGMA':
						case 'SHOW':
							return $result[$hash]->fetchAll();
					}
					return true;
				}

				return false;
			}

			else if (isset($query) === true)
			{
				$options = array
				(
					\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_EMULATE_PREPARES => false,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
					\PDO::MYSQL_ATTR_FOUND_ROWS => true,
				);

				if (preg_match('~^sqlite://([[:print:]]++)$~i', $query, $dsn) > 0)
				{
					$options += array
					(
						\PDO::ATTR_TIMEOUT => 3,
					);

					$db = new \PDO(sprintf('sqlite:%s', $dsn[1]), null, null, $options);
					$pragmas = array
					(
						'automatic_index' => 'ON',
						'cache_size' => '8192',
						'foreign_keys' => 'ON',
						'journal_size_limit' => '67110000',
						'locking_mode' => 'NORMAL',
						'page_size' => '4096',
						'recursive_triggers' => 'ON',
						'secure_delete' => 'ON',
						'synchronous' => 'NORMAL',
						'temp_store' => 'MEMORY',
						'journal_mode' => 'WAL',
						'wal_autocheckpoint' => '4096',
					);

					if (strncasecmp(PHP_OS, 'WIN', 3) !== 0)
					{
						$memory = 131072;

						if (($page = intval(shell_exec('getconf PAGESIZE'))) > 0)
						{
							$pragmas['page_size'] = $page;
						}

						if (is_readable('/proc/meminfo') === true)
						{
							if (is_resource($handle = fopen('/proc/meminfo', 'rb')) === true)
							{
								while (($line = fgets($handle, 1024)) !== false)
								{
									if (sscanf($line, 'MemTotal: %d kB', $memory) == 1)
									{
										$memory = round($memory / 131072) * 131072; break;
									}
								}

								fclose($handle);
							}
						}

						$pragmas['cache_size'] = intval($memory * 0.25 / ($pragmas['page_size'] / 1024));
						$pragmas['wal_autocheckpoint'] = $pragmas['cache_size'] / 2;
					}

					foreach ($pragmas as $key => $value)
					{
						$db->exec(sprintf('PRAGMA %s=%s;', $key, $value));
					}
				}

				else if (preg_match('~^(mysql|pgsql)://(?:(.+?)(?::(.+?))?@)?([^/:@]++)(?::(\d++))?/(\w++)/?$~i', $query, $dsn) > 0)
				{
					if (strncasecmp($query, 'mysql', 5) === 0)
					{
						$options += array
						(
							\PDO::ATTR_AUTOCOMMIT => true,
							\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_general_ci", time_zone = "+00:00";',
							\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						);
					}

					$db = new \PDO(sprintf('%s:host=%s;port=%s;dbname=%s', $dsn[1], $dsn[4], $dsn[5], $dsn[6]), $dsn[2], $dsn[3], $options);
				}
			}
		}

		catch (\Exception $exception)
		{
			$GLOBALS['err'] = $exception->getMessage();
			array_push($GLOBALS['internalId'],"null");
			return false;
		}

		return (isset($db) === true) ? $db : false;
	}

	public static function Reply($data)
	{
		$bitmask = 0;
		$options = array('UNESCAPED_SLASHES', 'UNESCAPED_UNICODE', 'JSON_NUMERIC_CHECK');

		if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) === true)
		{
			$options[] = 'PRETTY_PRINT';
		}

		foreach ($options as $option)
		{
			$bitmask |= (defined('JSON_' . $option) === true) ? constant('JSON_' . $option) : 0;
		}

		if (($result = json_encode($data, $bitmask)) !== false)
		{
			$callback = null;

			if (array_key_exists('callback', $_GET) === true)
			{
				$callback = trim(preg_replace('~[^[:alnum:]\[\]_.]~', '', $_GET['callback']));

				if (empty($callback) !== true)
				{
					$result = sprintf('%s(%s);', $callback, $result);
				}
			}

			if (headers_sent() !== true)
			{
				header(sprintf('Content-Type: application/%s; charset=utf-8', (empty($callback) === true) ? 'json' : 'javascript'));
			}
		}

		return $result;
	}

	public static function Serve($on = null, $route = null, $callback = null)
	{
		static $root = null;

		if (isset($_SERVER['REQUEST_METHOD']) !== true)
		{
			$_SERVER['REQUEST_METHOD'] = 'CLI';
		}

		if ((empty($on) === true) || (strcasecmp($_SERVER['REQUEST_METHOD'], $on) === 0))
		{
			if (is_null($root) === true)
			{
				$root = preg_replace('~/++~', '/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])) . '/');
			}

			if (preg_match('~^' . str_replace(array('#any', '#num'), array('[^/]++', '[0-9]++'), $route) . '~i', $root, $parts) > 0)
			{
				return (empty($callback) === true) ? true : exit(call_user_func_array($callback, array_slice($parts, 1)));
			}
		}

		return false;
	}
}
