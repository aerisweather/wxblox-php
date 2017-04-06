<?php

namespace Aeris;

require_once dirname(__FILE__) . '/Config.php';
require_once dirname(__FILE__) . '/Util.php';

class Component {
	public $type = null;
	public $place = null;
	public $opts = null;

	public function __construct($type, $loc, $opts = array()) {
		$this->type = $type;
		$this->place = $loc;
		$this->opts = $opts;
	}

	public function config($path = null) {
		if (!isset($path)) {
			return Config::getInstance();
		}
		return Config::getInstance()->get($path);
	}

	public function format() {
		$format = (preg_match('/^view-/', $this->type)) ? 'layouts' : 'components';
		return $format;
	}

	public function render() {
		$content = $this->_fetch();
		if (!$content) {
			return null;
		}

		// grab global place info from view container data attributes to be used in template vars
		$place = null;
		if ($this->format() == 'layouts') {
			if (preg_match('/<div class="awxs-view"([^>]+)>/', $content, $m)) {
				if (count($m) > 0) {
					$place = Util::getData($m[0]);
				}
			}
		}

		// parse template variables
		$vars = $this->config()->templateVars();
		$content = $this->_parse($content, $vars);

		// find all html links and setup vars based on data attributes on them to use for replacements in the link url
		// <a class="btn btn-more btn-bordered" href="/local/98109/forecast/{{day}}.html" data-date="14" data-month="03" data-year="2017" data-day="Tue" data-monthname="Mar" data-hour="14" data-minutes="00">
		if (preg_match_all('/<a[^>]+>/m', $content, $m)) {
			$links = (count($m) > 0) ? $m[0] : array();
			for ($i = 0; $i < count($links); $i++) {
				$link = $links[$i];
				$data = Util::getData($link);

				if (!empty($data)) {
					$parsed = $this->_parse($link, $data);

					if (preg_match('/href="([^"]+)"/', $parsed, $mm)) {
						if (count($mm) > 1) {
							$url = preg_replace('/\s+/', '+', $mm[1]);
							$parsed = str_replace($mm[1], $url, $parsed);
						}
					}

					$content = str_replace($link, $parsed, $content);
				}
			}
		}

		// replace {{loc}} with either links.loc value or $this->place
		$locLink = $this->config('links.loc');
		$content = $this->_parse($content, array(
			'loc' => (isset($place) && isset($locLink)) ? $locLink : urlencode($this->place)
		));

		// replace global place vars
		$content = $this->_parse($content, array('place' => $place));

		return $content;
	}

	private function _parse($tpl, $data = array()) {
		return Util::parse($tpl, $data);
	}

	private function _fetch() {
		$url = 'http://localhost:3000/{{key}}/{{secret}}/{{format}}/{{type}}/{{loc}}.html';

		$format = (preg_match('/^view-/', $this->type)) ? 'layouts' : 'components';
		$type = preg_replace('/^view-/', '', $this->type);

		$vars = array(
			'key' => $this->config()->accessKey,
			'secret' => $this->config()->secretKey,
			'format' => $format,
			'type' => $type,
			'loc' => $this->place
		);

		// strip out non-API query string parameters from options to be passed as JSON string
		$apiParams = array('p','limit','radius','filter','fields','query','sort','skip','from','to','plimit','psort','pskip','callback','metric');
		$opts = (isset($this->opts['opts'])) ? json_decode($this->opts['opts'], true) : array();
		$query = array();
		foreach ($this->opts as $key => $val) {
			if (in_array($key, $apiParams)) {
				array_push($query, "$key=$val");
			} else if ($key != 'opts') {
				// url params as key paths (e.g. "map.size") have their periods replaced by underscores (e.g. "map_size"), so convert them back
				$key = preg_replace('/_/', '.', $key);

				// check if we need to json_decode the value string for this key if it starts with "[" or "{"
				if (preg_match('/^(\[|\{)/', $val)) {
					$val = json_decode($val, true);
				}

				// if parameter key is a key path, step through the path to set the value on the $opts array
				if (preg_match('/\./', $key)) {
					Util::setValueForKeyPath($opts, $key, $val);
				} else {
					$opts[$key] = $val;
				}
			}
		}

		if (!empty($opts)) {
			array_push($query, 'opts=' . json_encode($opts));
		}

		if (!empty($query)) {
			$url .= '?' . implode('&', $query);
		}

		$url = $this->_parse($url, $vars);

		$ch =  curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		// curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		$result = curl_exec($ch);

		if ($result) {
			return $result;
		}

		return null;
	}
}