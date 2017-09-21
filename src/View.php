<?php

namespace Aeris\WxBlox;

require_once dirname(__FILE__) . '/Config.php';
require_once dirname(__FILE__) . '/Util.php';

class View {
	public $type = null;
	public $place = null;
	public $opts = null;

	private $_content = null;
	private $_doc = null;
	private $_xpath = null;

	public function __construct($type, $loc, $opts = array()) {
		$this->type = $type;
		$this->place = $loc;
		$this->opts = ($opts) ? $opts : array();

		$this->render();
	}

	public function config($path = null) {
		if (!isset($path)) {
			return Config::getInstance();
		}
		return Config::getInstance()->get($path);
	}

	public function format() {
		$format = 'views';

		if (preg_match('/^\/?(views|layouts)\//', $this->type, $m)) {
			$format = $m[1];
		} else if (preg_match('/^view-/', $this->type)) {
			$format = 'layouts';
		}

		return $format;
	}

	public function html() {
		if (!$this->_content) {
			$this->render();
		}

		if ($this->_doc) {
			$content = $this->_doc()->saveHTML();
			$content = preg_replace('/<\/?(html|head|body)>/', '', $content);
			return $content;
		}
		return $this->_content;
	}

	public function render() {
		$content = $this->_fetch();
		if (!$content) {
			return null;
		}

		// grab global place info from view container data attributes to be used in template vars
		$place = null;
		if (preg_match('/<div class="awxb-view"([^>]+)>/', $content, $m)) {
			if (count($m) > 0) {
				$place = Util::getData($m[0]);
			}
		}

		// parse template variables
		$vars = $this->config()->templateVars();
		$content = $this->_parse($content, $vars);

		$locLink = $this->config('links.loc');

		// find all html links and setup vars based on data attributes on them to use for replacements in the link url
		// <a class="btn btn-more btn-bordered" href="/local/98109/forecast/{{day}}.html" data-date="14" data-month="03" data-year="2017" data-day="Tue" data-monthname="Mar" data-hour="14" data-minutes="00">
		if (preg_match_all('/<(a[^>]+|[^>]+ data-href=[^>]+)>/m', $content, $m)) {
			$links = (count($m) > 0) ? $m[0] : array();
			for ($i = 0; $i < count($links); $i++) {
				$link = $links[$i];
				$data = Util::getData($link);

				if ($place) {
					$data = array_merge($place, $data);
				}

				// replace {{loc}} with either links.loc value or $this->place
				if ($locLink && $place) {
					if (is_string($locLink)) {
						$data['loc'] = $this->_parse($locLink, array('place' => $data));
					} else if (is_callable($locLink)) {
						$data['loc'] = $locLink($data);
					}
				}

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
		$content = $this->_parse($content, array(
			'loc' => (isset($place) && isset($locLink)) ? $locLink : urlencode($this->place)
		));

		// replace global place vars
		$content = $this->_parse($content, array('place' => $place));
		$this->_content = $content;
	}

	public function find($selector, $context = null) {
		$sel = '';
		$result = null;
		$prefix = ($context) ? '' : '//';

		// $nodes = explode(' ', $selector);
		$nodes = array();
		if (count($nodes) > 1) {
			$sel = array_shift($nodes);
			$path = $this->_toXPath($sel);
			$result = $this->_xpath()->query("${prefix}${path}", $context);
			if (count($nodes) > 0 && $result->length > 0) {
				$result = $this->find(implode(' ', $nodes), $result->item(0));
			}
		} else {
			// break up `.selector > .selector` direct descendent elements
			$parts = explode('>', $selector);
			$paths = array();

			foreach ($parts as $part) {
				$part = trim($part);

				// break up `.selector .selector` elements
				$descendents = explode(' ', $part);
				if (count($descendents) > 1) {
					$dpaths = array();
					foreach ($descendents as $descendent) {
						$dpath = $this->_toXPath($descendent);
						array_push($dpaths, $dpath);
					}
					array_push($paths, implode('//', $dpaths));
				} else {
					$path = $this->_toXPath($part);
					array_push($paths, $path);
				}
			}
			$sel = implode('/', $paths);
			$result = $this->_xpath()->query("${prefix}${sel}", $context);
		}

		if ($result->length > 0) {
			return $result->item(0);
		}
		return null;
	}

	public function prepend($selector, $html) {
		$target = $this->find($selector);
		if ($target) {
			$node = $this->_nodeFromHTML($html);
			if ($target->hasChildNodes()) {
				$target->insertBefore($node, $target->firstChild);
			} else {
				$target->appendChild($node);
			}
		}
	}

	public function append($selector, $html) {
		$target = $this->find($selector);
		if ($target) {
			$node = $this->_nodeFromHTML($html);
			$target->appendChild($node);
		}
	}

	public function insertBefore($selector, $html) {
		$target = $this->find($selector);
		if ($target) {
			$node = $this->_nodeFromHTML($html);
			$target->parentNode->insertBefore($node, $target);
		}
	}

	public function insertAfter($selector, $html) {
		$target = $this->find($selector);
		if ($target) {
			$node = $this->_nodeFromHTML($html);
			$target->parentNode->insertBefore($node, $target->nextSibling);
		}
	}

	private function _parse($tpl, $data = array()) {
		return Util::parse($tpl, $data);
	}

	private function _doc() {
		if (!$this->_doc) {
			libxml_use_internal_errors(true);
			$doc = new \DOMDocument();
			$doc->loadHTML($this->_content);
			$this->_doc = $doc;
			libxml_use_internal_errors(false);
			libxml_clear_errors();
		}
		return $this->_doc;
	}

	private function _xpath() {
		if (!$this->_xpath) {
			$this->_xpath = new \DOMXPath($this->_doc());
		}
		return $this->_xpath;
	}

	private function _toXPath($selector) {
		$selector = trim($selector);
		$tag = preg_replace('/(\.|#).*$/', '', $selector);
		if (empty($tag)) {
			$tag = '*';
		} else {
			$selector = preg_replace('/^' . $tag . '/', '', $selector);
		}

		$xpath = $tag;
		if (preg_match('/^\./', $selector)) {
			$selector = preg_replace('/^\./', '', $selector);
			$xpath .= "[contains(concat(' ',@class,' '),' $selector ')]";
		} else if (preg_match('/^\#/', $selector)) {
			$xpath .= "[@id='" . preg_replace('/^\#/', '', $selector) . "']";
		}

		return $xpath;
	}

	private function _nodeFromHTML($html) {
		$node = new \DOMDocument();
		// we must encode it correctly or strange characters may appear.
		$node->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
		// move this document element into the scope of the content document
		// created above or the insert/append will be rejected.
		$node = $this->_doc()->importNode($node->documentElement, true);

		return $node;
	}

	private function _fetch() {
		$format = $this->format();
		$type = $this->type;

		$type = preg_replace('/^\/?(views|layouts)\//', '', $type);
		$type = preg_replace('/^view-/', '', $type);
		$type = preg_replace('/-/', '/', $type);

		if (preg_match('/\/?maps\//', $type)) {
			$url = '{{server}}/{{key}}/{{secret}}/{{format}}/{{type}}';
		} else {
			$url = '{{server}}/{{key}}/{{secret}}/{{format}}/{{type}}/{{loc}}';
		}

		$vars = array(
			'server' => $this->config()->get('server'),
			'key' => $this->config()->accessKey,
			'secret' => $this->config()->secretKey,
			'format' => $format,
			'type' => $type,
			'loc' => urlencode($this->place)
		);

		// strip out non-API query string parameters from options to be passed as JSON string
		$apiParams = Util::apiParams();
		$opts = (isset($this->opts['opts'])) ? json_decode($this->opts['opts'], true) : array();
		$query = array();
		foreach ($this->opts as $key => $val) {
			if (in_array($key, $apiParams)) {
				array_push($query, "$key=$val");
			} else if ($key != 'opts') {
				// url params as key paths (e.g. "map.size") have their periods replaced by underscores (e.g. "map_size"), so convert them back
				$key = preg_replace('/_/', '.', $key);

				// check if we need to json_decode the value string for this key if it starts with "[" or "{"
				if (is_string($val) && preg_match('/^(\[|\{)/', $val)) {
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
			array_push($query, 'opts=' . urlencode(json_encode($opts)));
		}

		if (!empty($query)) {
			$url .= '?' . implode('&', $query);
		}

		$url = $this->_parse($url, $vars);
		$url = preg_replace('/\s/', '%20', $url);

		$timeout = $this->config()->get('timeout');
		if (!isset($timeout)) $timeout = 10;

		$ch =  curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		// curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		$result = curl_exec($ch);

		if ($result) {
			return $result;
		}

		return null;
	}
}