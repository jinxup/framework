<?php

	class JXP_DB_PDO
	{
		private $_con       = null;
		private $_log       = array();
		private $_driver    = null;
		private $_hash      = null;
		private $_alias     = null;
		private $_fetchMode = PDO::FETCH_ASSOC;
		private $_mute      = true;

		public function __construct($alias, $driver, $user = null, $pass = null)
		{
			$this->_alias  = $alias;
			$this->_driver = $driver;

			if (is_null($this->_con))
			{
				try {

					if (strpos($driver, 'sqlite') !== false)
						$this->_con = new PDO($driver);
					else
						$this->_con = new PDO($driver, $user, $pass);

					$this->_con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					$this->_con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
					$this->_con->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

				} catch (PDOException $e) {

					$this->_log['connection'] = $e;
				}
			}
		}

		public function mute($mute = false)
		{
			$this->_mute = $mute;
		}

		public function getDSN()
		{
			return $this->_driver;
		}

		public function setFetchMode($mode = 'assoc')
		{
			$mode = strtolower($mode);

			$this->_fetchMode = PDO::FETCH_ASSOC;

			if ($mode == 'object')
				$this->_fetchMode = PDO::FETCH_OBJ;

			return $this;
		}

		public function getConnection()
		{
			return isset($this->_con) ? $this->_con : null;
		}

		public function getHash($query, $bind)
		{
			return md5($query . json_encode($bind));
		}

		public function results($hash = null)
		{
			$hash = is_null($hash) ? $this->_hash : $hash;

			return isset($this->_log[$hash]['results']) ? $this->_log[$hash]['results'] : array();
		}

		public function log($hash = null)
		{
			$hash = is_null($hash) ? $this->_hash : $hash;

			return is_null($hash) ? $this->_log : $this->_log[$hash];
		}

		public function clearLog($hash = null)
		{
			$hash = is_null($hash) ? $this->_hash : $hash;
			
			if (is_null($hash))
				$this->_log = array();
			else
				$this->_log[$hash] = array();
		}

		public function trimQuery($query)
		{
			return trim(preg_replace('/(\r\n|\s{2,})/m', ' ', $query));
		}

		public function previewQuery($query = null, $params = array())
		{
			$query  = $this->trimQuery($query);
			$keys   = array();
			$values = array();

			if (!empty($params))
			{
				foreach ($params as $key => $value)
				{
					if (!is_array($value))
					{
						$keys[]   = is_string($key) ? '$:' . $key . '\b$' : '$[?]$';
						$values[] = is_numeric($value) ? intval($value) : '"' . $value . '"';
					}
				}

				$query = preg_replace($keys, $values, $query);
			}

			return $query;
		}

		public function query($query, $bind = array())
		{
			$this->_hash = $this->getHash($query, $bind);

			$query  = $this->trimQuery($query);
			$return = $this->_runQuery($query, $bind, $this->_hash);

			return $return;
		}

		public function beginTransaction()
		{
			$this->_con->beginTransaction();
		}

		public function commit()
		{
			$this->_con->commit();
		}

		private function _runQuery($query, $bind, $hash)
		{
			$results  = null;
			$starTime = microtime(true);
			$endTime  = 0;

			$this->_log[$hash]['alias']  = $this->_alias;
			$this->_log[$hash]['hash']   = $this->_hash;
			$this->_log[$hash]['error']  = null;
			$this->_log[$hash]['time']   = 0;
			$this->_log[$hash]['query']  = array('raw' => $query, 'preview' => $this->previewQuery($query, $bind));

			if (!empty($this->_con))
			{
				$this->_con->beginTransaction();

				try
				{
					$stmt = $this->_con->prepare($query);

					if (count($bind) > 0)
					{
						$this->_log[$hash]['tokens']['total'] = count($bind);

						preg_match_all('/(?<=\:)\w*/im', $query, $params);

						$params = array_map('array_values', array_map('array_filter', $params));

						$this->_prepareParameters($stmt, $bind, $params, $hash);
					}

					$execute = $stmt->execute();

					if ($execute !== false)
					{
						if (preg_match('/^(select|describe|desc|call|drop|create|show)/im', $query))
							$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

						if (preg_match('/^(delete|update)/im', $query))
							$results = $stmt->rowCount();

						if (preg_match('/^insert/im', $query))
							$results = $this->_con->lastInsertId();
					
						$endTime = microtime(true);

						$this->_con->commit();

					} else {

						$this->_log[$hash]['error']['message'] = 'There was an error executing your query';
					}

				} catch (PDOException $e) {

					$endTime = microtime(true);
					$debug   = debug_backtrace();

					/*$callerIdx['file']     = 0;
					$callerIdx['line']     = 0;
					$callerIdx['class']    = 0;
					$callerIdx['function'] = 0;

					if (isset($debug[3]) && $debug[3]['function'] == '_loadApplication')
					{
						$callerIdx['file']     = 1;
						$callerIdx['line']     = 2;
						$callerIdx['class']    = 2;
						$callerIdx['function'] = 2;
					}

					if (isset($debug[5]) && $debug[5]['function'] == '_loadApplication')
					{
						$callerIdx['file']     = 3;
						$callerIdx['line']     = 4;
						$callerIdx['class']    = 4;
						$callerIdx['function'] = 4;
					}

					if (isset($debug[6]) && $debug[6]['function'] == '_loadApplication')
					{
						$callerIdx['file']     = 3;
						$callerIdx['line']     = 3;
						$callerIdx['class']    = 4;
						$callerIdx['function'] = 4;
					}

					if (isset($debug[7]) && $debug[7]['function'] == '_loadApplication')
					{
						$callerIdx['file']     = 4;
						$callerIdx['line']     = 4;
						$callerIdx['class']    = 6;
						$callerIdx['function'] = 5;
					}

					if (isset($debug[8]) && $debug[8]['function'] == '_loadApplication')
					{
						$callerIdx['file']     = 3;
						$callerIdx['line']     = 3;
						$callerIdx['class']    = 4;
						$callerIdx['function'] = 4;
					}

					if (isset($debug[9]) && $debug[9]['function'] == '_loadApplication')
					{
						$callerIdx['file']     = 5;
						$callerIdx['line']     = 5;
						$callerIdx['class']    = 6;
						$callerIdx['function'] = 6;
					}

					if (isset($debug[11]) && $debug[11]['function'] == '_loadApplication')
					{
						$callerIdx['file']     = 8;
						$callerIdx['line']     = 8;
						$callerIdx['class']    = 6;
						$callerIdx['function'] = 6;
					}

					$this->_log[$hash]['caller'] = array(
						'file'     => $debug[$callerIdx['file']]['file'],
						'line'     => $debug[$callerIdx['line']]['line'],
						'class'    => $debug[$callerIdx['class']]['class'],
						'function' => $debug[$callerIdx['function']]['function']
					);*/


					$this->_log[$hash]['error'] = array(
						'file'    => $debug[2]['file'],
						'line'    => $debug[2]['line'],
						'message' => $e->getMessage()
					);

					$this->_errorLog($this->_log[$hash]['error']);

					$this->_con->rollBack();
				}

			}  else {

				$this->_log[$hash]['error']['message'] = $this->_log['connection']->getMessage();

				$this->_errorLog($this->_log[$hash]['error']);
			}

			$this->_log[$hash]['time'] = $endTime - $starTime;

			if (is_null($this->_log[$hash]['error']))
				unset($this->_log[$hash]['error']);

			$this->_log[$hash]['results'] = $results;

			return $results;
		}

		private function _errorLog($log)
		{
			$config = JXP_Config::get('error');

			if (isset($config['database']))
			{
				$dbError = $config['database'];

				if (isset($dbError['using']))
				{
					$using = $dbError['using'];

					switch (strtolower($using))
					{
						case 'email':

							if (isset($dbError[$using]['transport']))
							{
								$transport = $dbError[$using]['transport'];

								if (isset($transport['type']))
								{
									$type = $transport['type'];

									if (strtolower($type) == 'ses')
									{
										if (isset($transport['credentials']))
										{
											$email = $dbError[$using];

											if ((isset($email['to']) && !empty($email['to'])) && isset($email['from']) && !empty($email['from']))
											{
												$ses = JXP_Vendor::load('aws')->using($transport['credentials'])->get('Ses');

												$subject = null;

												if (isset($email['subject']) && !empty($email['subject']))
													$subject = $email['subject'];

												$replyTo    = $email['from'];
												$returnPath = $email['from'];

												if ((isset($email['replyTo']) && !empty($email['replyTo'])))
													$returnPath = $email['replyTo'];

												if ((isset($email['returnPath']) && !empty($email['returnPath'])))
													$returnPath = $email['returnPath'];

												try
												{
													ob_start();

													echo '<pre>' . print_r($log, true) . '</pre>';

													$body = ob_get_contents();

													ob_end_clean();

													$ses->sendEmail([
														'Source'      => $email['from'],
														'Destination' => [
															'ToAddresses' => [$email['to']]
														],
														'Message' => [
															'Subject' => [
																'Data'    => $subject,
																'Charset' => 'UTF-8',
															],
															'Body' => [
																'Html' => [
																	'Data'    => $body,
																	'Charset' => 'UTF-8',
																],
															],
														],
														'ReplyToAddresses' => [$replyTo],
														'ReturnPath'       => $returnPath
													]);

												} catch (Aws\Ses\Exception\SesException $e) {

													echo $e->getMessage();
												}
											}
										}
									}
								}
							}

							break;
					}
				}

			} else {

				echo '<pre>', print_r($log, true), '</pre>';
			}
		}

		private function _prepareParameters($stmt, $bind, $params, $hash)
		{
			foreach ($params as $key)
			{
				foreach ($key as $value)
				{
					if (isset($bind[$value]))
					{
						$param = null;
						$type  = null;

						if (is_string($bind[$value]))
						{
							$type  = 'STRING';
							$param = PDO::PARAM_STR;
						}

						if (is_null($bind[$value]) || empty($bind[$value]))
						{
							$type  = 'NULL';
							$param = PDO::PARAM_NULL;
						}

						if (is_numeric($bind[$value]))
						{
							$type  = 'INTEGER';
							$param = PDO::PARAM_INT;
						}

						if (is_bool($bind[$value]))
						{
							$type  = 'BOOLEAN';
							$param = PDO::PARAM_BOOL;
						}

						$arr = array(
							'name'  => $value,
							'value' => $bind[$value],
							'type'  => $type
						);

						$this->_log[$hash]['tokens']['bound'][] = $arr;

						$stmt->bindValue(":{$value}", $bind[$value], $param);

					} else {

						$this->_log[$hash]['tokens']['unknown'][] = $value;
					}
				}
			}
		}
	}