<?php

namespace Curry\Util;

class ClassEnumerator {
	public static function findClasses($path)
	{
		$rdi = new \RecursiveDirectoryIterator($path, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);
		$rii = new \RecursiveIteratorIterator($rdi);
		$ri = new \RegexIterator($rii, '/^.+\.php$/i');

		$classes = array();
		foreach($ri as $file) {
			$tokens = token_get_all(file_get_contents($file->getPathname()));
			$ignore = array(T_WHITESPACE, T_COMMENT);
			$namespace = '';
			$state = false;
			foreach($tokens as $token) {
				if ($state === T_NAMESPACE) {
					if (in_array($token[0], $ignore)) {
						continue;
					} else if ($token[0] == T_STRING || $token[0] == T_NS_SEPARATOR) {
						$namespace .= $token[1];
					} else if ($token === ';' || $token === '{') {
						$state = false;
					} else {
						throw new \Exception('Unexpected token: '.print_r($token, true).' in '.$file->getPathname());
					}
				} else if ($state === T_CLASS) {
					if (in_array($token[0], $ignore)) {
						continue;
					} else if ($token[0] == T_STRING) {
						$classes[] = $namespace.'\\'.$token[1];
						$state = false;
					} else {
						throw new \Exception('Unexpected token: '.print_r($token, true).' in '.$file->getPathname());
					}
				} else if ($token[0] === T_CLASS || $token[0] === T_NAMESPACE) {
					$state = $token[0];
					if ($token[0] === T_NAMESPACE)
						$namespace = '';
				}
			}
		}

		return $classes;
	}
}