<?php

namespace Curry\Form\Field;

class File extends Field {
	protected $multiple = false;
	protected $defaultWidget = '\\Curry\\Form\\Widget\\File';

	/**
	 * @return boolean
	 */
	public function getMultiple()
	{
		return $this->multiple;
	}

	/**
	 * @param boolean $multiple
	 */
	public function setMultiple($multiple)
	{
		$this->multiple = $multiple;
	}

	public function isArray()
	{
		return $this->multiple;
	}

	protected function getNamePath()
	{
		$field = $this;
		$names = array();
		while (true) {
			if (!$field->parent)
				break;
			$names[] = $field->getName();
			$field = $field->parent;
		}
		return array_reverse($names);
	}

	/**
	 * Get upload info from $_FILES.
	 *
	 * @return array|null
	 */
	protected function getFiles()
	{
		try {
			$path = $this->getNamePath();
		}
		catch (\Exception $e) {
			return null;
		}

		$topLevel = array_shift($path);
		if (!isset($_FILES[$topLevel]['name']))
			return null;

		$values = array();
		$fields = array('name','type','tmp_name','error','size');
		foreach($fields as $field) {
			$ptr = $_FILES[$topLevel][$field];
			foreach($path as $p) {
				if (!is_array($ptr) || !array_key_exists($p, $ptr))
					return null;
				$ptr = $ptr[$p];
			}
			if ($this->isArray()) {
				foreach($ptr as $k => $value) {
					if (!isset($values[$k]))
						$values[$k] = array();
					$values[$k][$field] = $value;
				}
			} else {
				$values[$field] = $ptr;
			}
		}
		return $values;
	}

	public function clean($value)
	{
		$files = $this->getFiles();
		if ($this->isArray() && $files !== null) {
			foreach($files as $k => $file) {
				if ($file['error'] === UPLOAD_ERR_NO_FILE) {
					unset($files[$k]);
				}
			}
			if (!count($files))
				return null;
		} else if ($files && $files['error'] === UPLOAD_ERR_NO_FILE) {
			return null;
		}
		return $files;
	}
}