<?php

/**
 * Stop Bot
 *
 * sva_stop_bot
 * Copyright (C) 2014 Visman (visman@inbox.ru)
 * License http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

class sva_sb {

	private $fields;
	private $first_chr;
	private $form_error;
	private $notchange_arr;
	private $empty_arr;
	private $pas;
	private $salt;
	
	// Class instance
	private static $instance;


	// Start of life
	private function __construct() {
		$this->notchange_arr = array('form_sent', 'csrf_token');
		$this->empty_arr = array();
	}


	// The end
	public function __destruct() {
	}


	// Singleton
	public static function singleton() {
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}


	// Clone forbiden
	public function __clone() {
		trigger_error('Clone is forbiden.', E_USER_ERROR);
	}


	private function encrypt($String)
	{
		$Seq = $this->pas;
		$Len = strlen($String);
		$Gamma = '';

		while (strlen($Gamma)<$Len)
		{
			$Seq = pack("H*",sha1($Gamma.$Seq.$this->salt));
			$Gamma.=substr($Seq,0,8);
		}

		return $String^$Gamma;
	}


	private function field_parser($matches) {
		if (isset($this->fields[$matches[3]]))
			return $matches[1].$this->fields[$matches[3]];

		if (in_array($matches[3], $this->notchange_arr)) {
			$name = $matches[3];
		} else {
			$name = substr($this->first_chr, (mt_rand() % strlen($this->first_chr)), 1);
			$this->first_chr = str_replace($name, '', $this->first_chr);

			$name .= mt_rand(1, 9);
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
			$len = mt_rand(1, 5);

			for ($i = 0; $i < $len; ++$i) {
				$name .= substr($chars, (mt_rand() % strlen($chars)), 1);
			}

			$name = (substr($matches[3], 0, 4) == 'req_' ? 'req_' : '').$name;
		}

		$this->fields[$matches[3]] = $name;
		return $matches[1].$name;
	}


	private function fields_parser($matches) {
		$matches[0] = preg_replace_callback('%(<(input|select)[^>]*name=[\'"])([^\'"]+)%i', array($this, 'field_parser'), $matches[0]);
		return preg_replace('%(<input[^>]*name=[\'"]form_sent[\'"][^>]*value=[\'"])([^\'"]+)%i', '$1&amp;'.base64_encode($this->encrypt(serialize($this->fields))).'&amp;',	$matches[0]);
	}


	public function form_parser($tpl_temp, $name) {
		$this->fields = array();
		$this->first_chr = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		return preg_replace_callback('%<form[^>]*id=[\'"]'.$name.'[\'"].*<\/form>%ims', array($this, 'fields_parser'), $tpl_temp);
	}


	public function form_verification() {
		$this->form_error = 0;
		$post = array();

		if (!isset($_POST['form_sent']) || !is_string($_POST['form_sent'])) {
			$this->form_error = 1;
			return false;
		}

		$s = $this->encrypt(base64_decode(str_replace('&', '', $_POST['form_sent'])));
		if (!preg_match('%^a:\d+:{(s:\d+:"\w+";s:\d+:"\w+";|s:\d+:"\w+";i:\d+;)+}$%', $s)) {
			$this->form_error = 2;
			return false;
		}

		$fields_arr = unserialize($s);
		$fields_arr[] = 'endofarray';
		reset($fields_arr);

		foreach ($_POST as $key => $data) {
			while ((list($key_orig, $key_post) = each($fields_arr)) && $key_post != $key) {
			}
			if (current($fields_arr) == false) {
				$this->form_error = 3;
				return false;
			} else {
				$post[$key_orig] = $data;
			}
		}

		$_POST = $post;
		foreach ($this->empty_arr as $key) {
			if (!empty($_POST[$key])) {
				$this->form_error = 4;
				return false;
			}
		}
		
		if (empty($_SERVER['HTTP_ACCEPT_CHARSET']) && empty($_SERVER['HTTP_ACCEPT_ENCODING']) && empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT'])) {
			$this->form_error = 5;
			return false;
		}
	}


	public function get_errors($errors, $lang) {
		if (!empty($this->form_error)) {
			$errors[] = isset($lang['Error'.$this->form_error]) ? $lang['Error'.$this->form_error] : 'Error '.$this->form_error;
		}
		return $errors;
	}


	public function add_for_nch($arr) {
		if (is_array($arr)) {
			$this->notchange_arr = array_merge ($this->notchange_arr, $arr);
		}
	}


	public function add_for_empty($arr) {
		if (is_array($arr)) {
			$this->empty_arr = array_merge ($this->empty_arr, $arr);
		}
	}

	
	public function set_encrypt_vars($pas, $salt) {
		$this->pas = $pas;
		$this->salt = $salt;
	}
}