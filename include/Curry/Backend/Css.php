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
 * Create and update css files.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_Css extends Curry_Backend_FileEditor
{
	/** {@inheritdoc} */
	public function getGroup()
	{
		return "Appearance";
	}

	/** {@inheritdoc} */
	public function __construct(\Curry\App $app)
	{
		parent::__construct($app);
		$this->root = $this->app->config->curry->wwwPath.DIRECTORY_SEPARATOR.'css';
	}

	/** {@inheritdoc} */
	public function showMain()
	{
		$this->addMenu();
	}

	/**
	 * Overridden to set codemirror mode.
	 *
	 * @param SplFileInfo $file
	 * @return Curry_Form
	 */
	protected function getEditForm(SplFileInfo $file)
	{
		$form = parent::getEditForm($file);
		$form->content->setCodeMirrorOptions(array('mode' => 'css'));
		return $form;
	}
}