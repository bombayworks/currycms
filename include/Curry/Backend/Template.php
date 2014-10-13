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
 * Create and update templates.
 * 
 * @package Curry\Controller\Backend
 */
class Curry_Backend_Template extends Curry_Backend_FileEditor
{
	/** {@inheritdoc} */
	public function getGroup()
	{
		return "Appearance";
	}

	/** {@inheritdoc} */
	public function __construct()
	{
		parent::__construct();
		$this->root = \Curry\App::getInstance()->config->curry->template->root;
	}

	/** {@inheritdoc} */
	public function showMain()
	{
		$this->addMenu();
	}

	/**
	 * Get template select options.
	 *
	 * @return array
	 */
	public static function getTemplateSelect()
	{
		$backend = new self();
		$items = array_keys($backend->getFileList());
		return array_combine($items, $items);
	}

}
