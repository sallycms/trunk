<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Mail implements sly_Mail_Interface {
	protected $tos;
	protected $from;
	protected $subject;
	protected $body;
	protected $contentType;
	protected $charset;
	protected $headers;

	/**
	 * @return sly_Mail_Interface
	 */
	public static function factory() {
		$className = 'sly_Mail';
		$className = sly_Core::dispatcher()->filter('SLY_MAIL_CLASS', $className);
		$instance  = new $className();

		if (!($instance instanceof sly_Mail_Interface)) {
			throw new sly_Mail_Exception('Mail instance does not implement sly_Mail_Interface.');
		}

		return $instance;
	}

	public function __construct() {
		$this->tos         = array();
		$this->from        = '';
		$this->subject     = '';
		$this->body        = '';
		$this->contentType = 'text/plain';
		$this->charset     = 'UTF-8';
		$this->headers     = array();
	}

	public function addTo($mail, $name = null) {
		$this->tos[] = self::parseAddress($mail, $name);
	}

	public function clearTo() {
		$this->tos = array();
	}

	public function setFrom($mail, $name = null) {
		$this->from = self::parseAddress($mail, $name);
	}

	public function setSubject($subject) {
		$this->subject = self::clean($subject);
	}

	public function setBody($body) {
		$this->body = self::clean($body);
	}

	public function setContentType($contentType) {
		$this->contentType = strtolower(trim($contentType));
	}

	public function setCharset($charset) {
		$this->charset = strtoupper(trim($charset));
	}

	public function setHeader($field, $value) {
		$field = strtolower(trim($field));

		if ($value === false || $value === null || strlen(trim($value)) === 0) {
			unset($this->headers[$field]);
		}
		else {
			$this->headers[$field] = self::clean($value);
		}
	}

	public function send() {
		$params = '';

		// do nothing if no one would read it
		if (empty($this->tos)) {
			throw new sly_Mail_Exception('No recipients given.');
		}

		// build recipient
		$to = array_map(array($this, 'buildAddress'), $this->tos);
		$to = implode(', ', array_unique($to));

		// build sender if available
		if (!empty($this->from)) {
			$this->setHeader('From', $this->buildAddress($this->from));
			$params = '-f'.$this->from[0]; // -fmy@sender.com
		}

		// encode subject
		$subject = $this->encode($this->subject);

		// set content type
		$this->setHeader('Content-Type', $this->contentType.'; charset='.$this->charset);

		// prepare headers
		$headers = array();

		foreach ($this->headers as $field => $value) {
			$headers[] = $field.': '.$value;
		}

		$headers = implode("\r\n", $headers);

		// and here we go
		if (!mail($to, $subject, $this->body, $headers, $params)) {
			throw new sly_Mail_Exception('Error sending mail.');
		}

		return true;
	}

	protected function buildAddress($address) {
		list($mail, $name) = $address;

		if (strlen($name) === 0) {
			return $mail;
		}

		return $this->encode($name).' <'.$mail.'>';
	}

	protected static function parseAddress($mail, $name) {
		$mail = self::clean($mail);
		$name = $name === null ? null : self::clean($name);

		if (!self::isValid($mail)) {
			throw new sly_Mail_Exception('Address "'.$mail.'" is not valid.');
		}

		return array($mail, $name);
	}

	protected static function clean($str) {
		return preg_replace('#[\x00-\x1F]#', '', trim($str));
	}

	public function encode($str) {
		return '=?'.strtoupper($this->charset).'?B?'.base64_encode($str).'?=';
	}

	public static function isValid($address) {
		$no_ws_ctl = "[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x7f]";
		$alpha     = "[\\x41-\\x5a\\x61-\\x7a]";
		$digit     = "[\\x30-\\x39]";
		$cr        = "\\x0d";
		$lf        = "\\x0a";
		$crlf      = "($cr$lf)";

		$obs_char    = "[\\x00-\\x09\\x0b\\x0c\\x0e-\\x7f]";
		$obs_text    = "($lf*$cr*($obs_char$lf*$cr*)*)";
		$text        = "([\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f]|$obs_text)";
		$obs_qp      = "(\\x5c[\\x00-\\x7f])";
		$quoted_pair = "(\\x5c$text|$obs_qp)";

		$wsp      = "[\\x20\\x09]";
		$obs_fws  = "($wsp+($crlf$wsp+)*)";
		$fws      = "((($wsp*$crlf)?$wsp+)|$obs_fws)";
		$ctext    = "($no_ws_ctl|[\\x21-\\x27\\x2A-\\x5b\\x5d-\\x7e])";
		$ccontent = "($ctext|$quoted_pair)";
		$comment  = "(\\x28($fws?$ccontent)*$fws?\\x29)";
		$cfws     = "(($fws?$comment)*($fws?$comment|$fws))";
		$cfws     = "$fws*";

		$atext = "($alpha|$digit|[\\x21\\x23-\\x27\\x2a\\x2b\\x2d\\x2f\\x3d\\x3f\\x5e\\x5f\\x60\\x7b-\\x7e])";
		$atom  = "($cfws?$atext+$cfws?)";

		$qtext         = "($no_ws_ctl|[\\x21\\x23-\\x5b\\x5d-\\x7e])";
		$qcontent      = "($qtext|$quoted_pair)";
		$quoted_string = "($cfws?\\x22($fws?$qcontent)*$fws?\\x22$cfws?)";
		$word          = "($atom|$quoted_string)";

		$obs_local_part = "($word(\\x2e$word)*)";
		$obs_domain     = "($atom(\\x2e$atom)*)";

		$dot_atom_text = "($atext+(\\x2e$atext+)*)";
		$dot_atom      = "($cfws?$dot_atom_text$cfws?)";

		$dtext          = "($no_ws_ctl|[\\x21-\\x5a\\x5e-\\x7e])";
		$dcontent       = "($dtext|$quoted_pair)";
		$domain_literal = "($cfws?\\x5b($fws?$dcontent)*$fws?\\x5d$cfws?)";

		$local_part = "($dot_atom|$quoted_string|$obs_local_part)";
		$domain     = "($dot_atom|$domain_literal|$obs_domain)";
		$addr_spec  = "($local_part\\x40$domain)";

		$done = false;

		while (!$done) {
			$new = preg_replace("!$comment!", '', $address);

			if (strlen($new) === strlen($address)) {
				$done = true;
			}

			$address = $new;
		}

		return preg_match("!^$addr_spec$!", $address) ? true : false;
	}
}