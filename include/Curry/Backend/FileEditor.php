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
use Curry\Controller\Backend;
use Curry\Controller\Frontend;

/**
 * Simple backend to create and edit files.
 */
abstract class Curry_Backend_FileEditor extends \Curry\AbstractLegacyBackend {
	/**
	 * Directory to edit files in.
	 * @var string
	 */
	protected $root = '.';

	/** {@inheritdoc} */
	public function getGroup()
	{
		return "";
	}

	/**
	 * Add menu and commands.
	 *
	 * @param null $selection
	 */
	protected function addMenu($selection = null)
	{
		$this->addCommand("New file", url('', array('module', 'view'=>'NewFile')), 'icon-plus-sign', array('class' => 'dialog'));
		$this->addCommand("New folder", url('', array('module', 'view'=>'NewFolder')), 'icon-folder-open', array('class' => 'dialog'));

		$tree = $this->getTree();
		$cookieId = $tree->getOption('cookieId');
		setcookie($cookieId."-focus", $selection ? $selection : null);
		setcookie($cookieId."-select", $selection ? $selection : null);
		$this->addMenuContent($tree);
	}

	/**
	 * Get folder tree used by addMenu.
	 *
	 * @return Curry_Tree_Filesystem
	 */
	protected function getTree()
	{
		return new Curry_Tree_Filesystem($this->root, array(
			'ajaxUrl' => (string)url('', $_GET)->add(array('json'=>1)),
			'nodeCallback' => array($this, 'getNodeJson'),
		));
	}

	/**
	 * Get file node properties.
	 *
	 * @param string $path
	 * @param Curry_Tree $tree
	 * @param int $depth
	 * @return array
	 */
	public function getNodeJson($path, $tree, $depth)
	{
		$p = $tree->objectToJson($path, $tree, $depth);
		if(!$p['isFolder']) {
			$p['href'] = (string)url('', array('module','view'=>'Edit','file'=>$path));
		} else {
			$p['expand'] = true;
		}
		return $p;
	}

	/**
	 * Get iterator for files in directory.
	 *
	 * @return Iterator
	 */
	protected function getFileIterator()
	{
		$rdit = new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS);
		return new RecursiveIteratorIterator($rdit, RecursiveIteratorIterator::SELF_FIRST);
	}

	/**
	 * List of templates
	 *
	 * @return array
	 */
	public function getFileList()
	{
		$files = array();
		$dit = $this->getFileIterator();
		foreach($dit as $entry) {
			$filename = $entry->getFilename();
			if($entry->isFile() && $filename{0} !== '.') {
				$path = $dit->getSubPathname();
				$files[$path] = $entry;
			}
		}
		return $files;
	}

	/**
	 * Edit file view.
	 *
	 * @throws Exception
	 */
	public function showEdit()
	{
		$files = self::getFileList();
		$file = $_GET['file'];
		if(!isset($files[$file]))
			throw new Exception('File not found');
		$file = $files[$file];

		$form = $this->getEditForm($file);
		if (isPost()) {
			$ajax = array('success' => 1);
			if ($form->isValid($_POST)) {
				// Save content and preserve EOL style
				$values = $form->getValues();
				$code = $values['content'];
				$eol = Curry_Util::getStringEol($code);
				$targetEol = urldecode($values['eol']);
				if($eol !== $targetEol)
					$code = str_replace($eol, $targetEol, $code);
				file_put_contents($file->getPathname(), $code);
				$form = $this->getEditForm($file);
			} else {
				$error = "Validation error!";
				foreach($form->getMessages() as $element => $messages) {
					$error .= "\n$element: ".join(', ', $messages);
				}
				$ajax['success'] = 0;
				$ajax['error'] = $error;
			}
			if ($_POST['_ajaxsubmit']) {
				$form->render(); // fixes issue with csrf-token
				$ajax['values'] = $form->getValues();
				self::returnJson($ajax);
			}
		}

		$this->addBodyClass('tpl-fullscreen');
		$this->addBodyClass('tpl-fileeditor');
		$this->addMenu($_GET['file']);
		$this->addMainContent($form);
	}

	/**
	 * Get edit file form.
	 *
	 * @param SplFileInfo $file
	 * @return Curry_Form
	 */
	protected function getEditForm(SplFileInfo $file)
	{
		$content = file_get_contents($file->getPathname());
		$eol = Curry_Util::getStringEol($content);
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'class' => 'ctrlsave ajaxsubmit',
			'elements' => array(
				'eol' => array('hidden', array(
					'value' => urlencode($eol),
				)),
				'content' => array('codeMirror', array(
					'label' => 'Content',
					'value' => $content,
					'cols' => 40,
					'rows' => 25,
				)),
				'save' => array('submit', array(
					'class' => 'btn btn-large',
					'label' => 'Save',
				)),
			)
		));
		return $form;
	}

	/**
	 * Create new file view.
	 */
	public function showNewFile()
	{
		$form = $this->getNewForm();
		if (isPost() && $form->isValid($_POST)) {
			$values = $form->getValues();
			$path = $values['location'] ? Curry_Util::path($values['location'], $values['name']) : $values['name'];
			$target = Curry_Util::path($this->root, $path);
			touch($target);
			self::redirect(url('', array('module','view'=>'Edit','file'=>$path)));
		} else {
			$this->addMainContent($form);
		}
	}

	/**
	 * Create new folder view.
	 */
	public function showNewFolder()
	{
		$form = $this->getNewForm();
		if (isPost() && $form->isValid($_POST)) {
			$values = $form->getValues();
			$target = Curry_Util::path($this->root, $values['location'], $values['name']);
			mkdir($target);
			self::redirect(url('', array('module','view'=>'Main')));
		} else {
			$this->addMainContent($form);
		}
	}

	/**
	 * Create new form.
	 *
	 * @return Curry_Form
	 */
	protected function getNewForm()
	{
		$dirs = array('' => basename($this->root));
		$rii = $this->getFileIterator();
		foreach($rii as $entry) {
			if($entry->isDir())
				$dirs[$rii->getSubPathname()] = str_repeat(Curry_Core::SELECT_TREE_PREFIX, $rii->getDepth() + 1) . $entry->getFilename();
		}
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'class' => 'dialog-form',
			'elements' => array(
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'validators' => array(
						array('regex', false, array(
							'pattern'   => '/^[a-z0-9 \._-]+$/i',
							'messages'  =>  'Invalid filename',
						)),
					),
				)),
				'location' => array('select', array(
					'label' => 'Location',
					'multiOptions' => $dirs,
				)),
				'save' => array('submit', array(
					'label' => 'Save',
				)),
			)
		));
		return $form;
	}
}