<?php

namespace Aeris;

class Util {

	static function parse($str, $data = array()) {
		if (preg_match_all('/{{([^}]+)}}/', $str, $m)) {
			$vars = $m[1];
			for ($i = 0; $i < count($vars); $i++) {
				$key = $vars[$i];
				$val = Util::valueForKeyPath($data, $key, false);
				if (!is_array($val)) {
					$str = preg_replace('/{{' . $key . '}}/', $val, $str);
				}
			}
		}

		return $str;
	}

	static function valueForKeyPath($obj, $path, $allowNull = true) {
		if (!isset($obj)) return null;

		$value = null;

		$nextPath = '';
		$splitPath = explode('.', $path);

		if (count($splitPath) > 1) {
			$key = $splitPath[0];
			$nextPath = preg_replace("/^$key\./", '', $path);
			$value = Util::valueForKeyPath($obj[$key], $nextPath, true);
		} else {
			$key = $splitPath[0];
			$value = (isset($obj[$key])) ? $obj[$key] : null;
		}

		if (!isset($value) && !$allowNull) {
			$value = '{{' . $path . '}}';
		}

		return $value;
	}

	static function setValueForKeyPath(&$obj, $path, $value) {
		if (!isset($obj)) return false;

		$splitPath = explode('.', $path);
		$key = array_shift($splitPath);

		if (count($splitPath) > 0) {
			$obj[$key] = (isset($obj[$key])) ? $obj[$key] : array();
			Util::setValueForKeyPath($obj[$key], implode('.', $splitPath), $value);
		} else {
			$obj[$key] = $value;
		}
	}

	static function getData($str) {
		$data = array();

		if (preg_match_all('/((data-([^=]+)=((?:"|\'))([^"\']+)\4))/', $str, $m)) {
			if (count($m) > 0) {
				$keys = $m[3];
				$values = $m[5];

				for ($j = 0; $j < count($keys); $j++) {
					$data[$keys[$j]] = $values[$j];
				}
			}
		}

		return $data;
	}
}