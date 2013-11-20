<?php
/**
 * Curry CMS
 *
 * LICENSE
 *
 * This source file is subject to the GPL license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://currycms.com/license
 *
 * @category   Curry CMS
 * @package    Curry
 * @copyright  2011-2012 Bombayworks AB (http://bombayworks.se)
 * @license    http://currycms.com/license GPL
 * @link       http://currycms.com
 */

/**
 * Form element for both date and time.
 * 
 * @package Curry\Form
 */
class Curry_Form_Element_DateTime extends Zend_Form_Element_Xhtml
{
	/**
	 * Date output format.
	 *
	 * @var string
	 */
	protected $_dateFormat = 'Y-m-d';
	
	/**
	 * Time output format.
	 *
	 * @var string
	 */
	protected $_timeFormat = 'H:i:s';
	
	/**
	 * Current time/date value.
	 *
	 * @var int
	 */
	protected $_timestamp;
	
	/**
	 * Override default decorators.
	 *
	 * @return Curry_Form_Element_DateTime
	 */
	public function loadDefaultDecorators()
	{
		if ($this->loadDefaultDecoratorsIsDisabled()) {
			return $this;
		}

		$decorators = $this->getDecorators();
		if (empty($decorators)) {
			$this->addDecorator('DateTime')
				->addDecorator('Errors')
				->addDecorator('Description', array('tag' => 'p', 'class' => 'description'))
				->addDecorator('HtmlTag', array('tag' => 'dd',
												'id'  => $this->getName() . '-element'))
				->addDecorator('Label', array('tag' => 'dt'));
		}
		return $this;
	}
	
	/**
	 * Set value, ie the current timestamp.
	 *
	 * @param mixed $value
	 * @return Curry_Form_Element_DateTime self
	 */
	public function setValue($value)
	{
		if (is_int($value)) {
			$this->_timestamp = $value;
		} elseif ($value === null) {
			$this->_timestamp = null;
		} elseif (is_string($value)) {
			$this->_timestamp = strtotime($value);
		} elseif (is_array($value) && isset($value['date']) && isset($value['time'])) {
			$this->_timestamp = strtotime($value['date'] . ' ' . $value['time']);
		} elseif ($value instanceof DateTime) {
			$this->_timestamp = $value->format('U');
		} else {
			throw new Exception('Invalid date value provided');
		}
		return $this;
	}
	
	/**
	 * Get date, using the date formatting.
	 *
	 * @return string
	 */
	public function getDate()
	{
		if($this->_timestamp === null)
			return null;
		return date($this->_dateFormat, $this->_timestamp);
	}
	
	/**
	 * Get time, using the time formatting.
	 *
	 * @return string
	 */
	public function getTime()
	{
		if($this->_timestamp === null)
			return null;
		return date($this->_timeFormat, $this->_timestamp);
	}
 
	/**
	 * Get value of element.
	 *
	 * @return int
	 */
	public function getValue()
	{
		return $this->_timestamp;
	}
}