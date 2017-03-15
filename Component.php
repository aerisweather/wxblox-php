<?php

namespace Aeris;

require_once dirname(__FILE__) . '/Config.php';

class Component {
	public $type = null;
	public $place = null;
	public $opts = null;

	public function __construct($type, $loc, $opts = array()) {
		$this->type = $type;
		$this->place = $loc;
		$this->opts = $opts;
	}

	public function config() {
		return Config::getInstance();
	}

	public function render() {
		$content = $this->_fetch();
		if (!$content) {
			return null;
		}

		// parse template variables
		$content = $this->_parseTemplate($content, $this->config()->templateVars());

		// find all html links and setup vars based on data attributes on them to use for replacements in the link url
		// <a class="btn btn-more btn-bordered" href="/local/98109/forecast/{{day}}.html" data-date="14" data-month="03" data-year="2017" data-day="Tue" data-monthname="Mar" data-hour="14" data-minutes="00">
		if (preg_match_all('/<a[^>]+>/m', $content, $m)) {
			$links = (count($m) > 0) ? $m[0] : array();
			for ($i = 0; $i < count($links); $i++) {
				$link = $links[$i];
				if (preg_match_all('/((data-([^=]+)=((?:"|\'))([^"\']+)\4))/', $link, $mm)) {
					$data = array();

					if (count($mm) > 0) {
						$keys = $mm[3];
						$values = $mm[5];

						for ($j = 0; $j < count($keys); $j++) {
							$data[$keys[$j]] = urlencode($values[$j]);
						}
					}

					$parsed = $this->_parseTemplate($link, $data);
					$content = str_replace($link, $parsed, $content);
				}
			}
		}

		$content = $this->_parseTemplate($content, array(
			'loc' => urlencode($this->place)
		));

		return $content;
	}

	private function _parseTemplate($tpl, $data = array()) {
		foreach ($data as $var => $val) {
			$tpl = preg_replace('/{{' . $var . '}}/', $val, $tpl);
		}

		return $tpl;
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

		$url = $this->_parseTemplate($url, $vars);
		$content = file_get_contents($url);
		if ($content) {
			return $content;
		}

		return null;
	}
}