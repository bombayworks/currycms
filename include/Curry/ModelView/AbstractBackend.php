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
namespace Curry\ModelView;

/**
 *
 * @package Curry\ModelView
 */
abstract class AbstractBackend extends \Curry\Backend\AbstractBackend {
	abstract public function getModelClass();

	public function getSelection()
	{
		$pk = isset($this['id']) ? json_decode($this['id'], true) : null;
		if ($pk && $this->parent instanceof AbstractBackend) {
			return \PropelQuery::from($this->parent->getModelClass())->findPk($pk);
		}
		return null;
	}
}
