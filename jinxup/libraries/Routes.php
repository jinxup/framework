<?php

	class JXP_Routes
	{
		public static $_routes = array('controller' => 'Index_Controller', 'action' => 'indexAction');
		public static $prefix  = null;

		public static function setRoutes($routes = array())
		{
			self::$_routes = $routes;
		}

		public static function getRoutes()
		{
			return self::$_routes;
		}

		public static function setController($controller)
		{
			self::$_routes['controller'] = $controller;
		}

		public static function getController($friendly = false)
		{
			return $friendly === true ? self::$_routes['controller'] : str_replace('_Controller', '', self::$_routes['controller']);
		}

		public static function getActionCall($friendly = false)
		{
			return $friendly === true ? self::$_routes['action'] : str_replace('Action', '', self::$_routes['action']);
		}

		public static function getModel($friendly = false)
		{
			return $friendly === true ? self::$_routes['controller'] . '_Model' : self::getController();
		}

		public static function setAction($action)
		{
			self::$_routes['action'] = $action;
		}

		public static function getDomain()
		{
			return parse_url(getenv('HTTP_HOST'), PHP_URL_PATH);
		}

		public static function getSubdomain($depth = 0)
		{
			$subdomain = explode('.', rawurldecode(getenv('HTTP_HOST')));

			return isset($subdomain[$depth]) ? $subdomain[$depth] : null;
		}

		public static function getDomainExt()
		{
			$host = parse_url(getenv('HTTP_HOST'));

			preg_match('/(.*?)((\.co)?.[a-z]{2,4})$/im', $host['host'], $m);

			return isset($m[2]) ? $m[2] : '';
		}

		public static function getURI()
		{
			$uri  = str_replace('index.php', '', getenv('PHP_SELF'));
			$root = explode(DS, dirname(dirname(__DIR__)));

			self::$prefix = str_replace('//', '/', end($root));

			$uri     = str_replace($uri, '', getenv('REQUEST_URI'));
			$request = rawurldecode(trim(parse_url($uri, PHP_URL_PATH), '/'));

			return '/' . $request;
		}

		public static function getParamCount()
		{
			return isset(self::$_routes['params']) ? count(self::$_routes['params']) : 0;
		}

		public static function getParams()
		{
			return isset(self::$_routes['params']) ? self::$_routes['params'] : array();
		}

		public static function addParam($name, $count = 1)
		{
			for ($i = 0; $i < $count; $i++)
				self::$_routes['params'][] = $name;
		}

		public static function addParams($params)
		{
			self::$_routes['params'] += $params;
		}

		public static function assocParams($params = array())
		{
			$parameters  = empty($params) ? self::getParams() : $params;
			$_parameters = array();

			if (count($parameters) > 1)
			{
				$i = 0;

				while (!empty($parameters))
				{
					if (isset($parameters[$i]))
					{
						$_parameters[$parameters[$i]] = isset($parameters[$i + 1]) ? $parameters[$i + 1] : null;

						unset($parameters[$i]);

						if (isset($parameters[$i + 1]))
							unset($parameters[$i + 1]);

						$i += 2;
					}
				}
			}

			return JXP_Format::trimSpaces($_parameters);
		}
	}