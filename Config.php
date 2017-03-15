<?php

namespace Aeris;

class Config {
	public $accessKey = null;
	public $secretKey = null;

	public $links = array(
		'local' => array(
			'main' => '/local/{{loc}}.html',
			'radar' => '/local/{{loc}}/radar.html',
			'history' => array(
				'day' => '/local/{{loc}}/history/{{year}}/{{month}}/{{date}}.html',
				'month' => '/local/{{loc}}/history/{{year}}/{{month}}.html'
			),
			'forecast' => array(
				'day' => '/local/{{loc}}/forecast/{{year}}/{{month}}/{{date}}.html'
			),
			'advisory' => '/local/{{loc}}/advisories.html',
			'calendar' => '/local/{{loc}}/calendar/{{year}}/{{month}}.html'
		),
		'maps' => array(
			'main' => '/maps.html'
		)
	);
	private $_linkVars = null;

	public static function getInstance() {
		static $inst = null;
		if ($inst === null) {
			$inst = new Config();
		}
		return $inst;
	}

	public function __construct() {
		$this->_linkVars = $this->_valuesByKeyPath('links', $this->links);
	}

	public function setAccess($key, $secret) {
		$this->accessKey = $key;
		$this->secretKey = $secret;
	}

	public function templateVars() {
		return $this->_linkVars;
	}

	private function _valuesByKeyPath($key, $obj, &$dest = null) {
		if (!isset($dest)) $dest = array();
		foreach ($obj as $k => $val) {
			$vkey = ((!empty($key)) ? "$key." : '') . $k;
			if (is_array($val)) {
				$this->_valuesByKeyPath($vkey, $val, $dest);
			} else {
				$dest[$vkey] = $val;
			}
		}

		return $dest;
	}
}