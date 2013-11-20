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
 * An extension of subform that makes it possibly to have a list of subforms.
 * 
 * @package Curry\Form
 */
class Curry_Form_MultiForm extends Curry_Form_SubForm
{
	/**
	 * Subform to clone.
	 *
	 * @var Zend_Form_Subform
	 */
	protected $cloneTarget;
	
	/**
	 * Index for the next element.
	 *
	 * @var int
	 */
	protected $nextIndex = 1;
	
	/**
	 * Set the subform that will be automatically duplicated.
	 *
	 * @param Zend_Form_SubForm $cloneTarget
	 * @return Curry_Form_MultiForm
	 */
	public function setCloneTarget($cloneTarget)
	{
		$this->cloneTarget = $cloneTarget;
		return $this;
	}
	
	/**
	 * Get clone target, ie the subform 
	 *
	 * @return Zend_Form_SubForm|null
	 */
	public function getCloneTarget()
	{
		return $this->cloneTarget;
	}
	
	/** {@inheritdoc} */
	public function setDefaults(array $defaults)
	{
		$keys = array_keys($defaults);
		if(!is_numeric(reset($keys)) && count($defaults) > 0) {
			$defaults = array_values($defaults);
			$defaults = reset($defaults);
		}

		if(!$defaults)
			$defaults = array();

		parent::setDefaults($defaults);
		$this->_add($defaults);
		return $this;
	}
	
	/**
	 * Get index for next subform element.
	 *
	 * @return int
	 */
	public function getNextIndex()
	{
		return $this->nextIndex;
	}
	
	/**
	 * Initialize subforms from data.
	 *
	 * @param array $data
	 * @param bool $reindex
	 */
	private function _add($data, $reindex = true)
	{
		if(!$this->cloneTarget)
			return;
		
		$this->clearSubForms();
		
		$i = 1;
		foreach($data as $index => $value) {
			$sf = clone $this->cloneTarget;
			$this->addSubForm($sf, $reindex ? $i : $index);
			if(is_array($value))
				$sf->setDefaults($value);
			++$i;
		}
		$this->nextIndex = $reindex ? $i : ( count($data) ? max(array_keys($data)) + 1 : 1);
	}
	
	/** {@inheritdoc} */
	public function isValid($data)
	{
		if ($this->isArray()) {
			$data2 = $this->_dissolveArrayValue($data, $this->getElementsBelongTo());
			foreach($data2 as $k => $v) {
				if(!is_int($k))
					unset($data2[$k]);
			}
			$this->_add($data2, false);
		}
		return parent::isValid($data);
	}
	
	/** {@inheritdoc} */
	public function loadDefaultDecorators()
	{
		if ($this->loadDefaultDecoratorsIsDisabled()) {
			return;
		}

		$decorators = $this->getDecorators();
		if (empty($decorators)) {
			$this->addDecorator('FormElements')
				 ->addDecorator('Cloner')
				 //->addDecorator('HtmlTag', array('tag' => 'ul'))
				 ->addDecorator('Sortable')
				 ->addDecorator('Fieldset')
				 ->addDecorator('DtDdWrapper');
		}
	}
}
