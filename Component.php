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

		$url = $this->_parse($url, $vars);
		$content = file_get_contents($url);
		if ($content) {
			return $content;
		}

		return null;
	}
}