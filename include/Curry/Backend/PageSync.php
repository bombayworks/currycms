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
 * Synchronize pages across sites.
 *
 * @package Curry\Backend
 */
class Curry_Backend_PageSync extends Curry_Backend
{
	const INTRO = <<<HTML
<p>This module allows you to synchronize pages with a remote source. It will compare the published revisions. If you
choose to synchronize a page, a new revision will be created with the content from the remote source. Be aware though,
if you chose to sync a page that has been deleted, it will be permanently deleted after sync.</p>
<p>Synchronizing pages can be very useful if you have multiple copies of the same site (e.g. production, staging or
development) that you want to keep synchronized. Keep in mind that this module doesn't really know whether pages have
been added or deleted, it will only show the status relative to this site.</p>
HTML;

	/** {@inheritdoc} */
	public static function getGroup()
	{
		return 'System';
	}

	/** {@inheritdoc} */
	public static function getName()
	{
		return 'Synchronize pages';
	}

	/** {@inheritdoc} */
	public function showMain()
	{
		$rootPage = PageQuery::create()->findRoot();
		$code = Curry_Backend_PageSyncHelper::getPageCode($rootPage);
		$localChecksum = sha1(serialize($code));

		if (isPost('fetch')) {
			Curry_Application::returnJson($code);
		}

		$form = new Curry_Form(array(
			'csrfCheck' => false, // because of the checksum this should be CSRF safe.
			'action' => (string)url('', $_GET),
			'method' => 'post',
			'elements' => array(
				'url' => array('text', array(
					'label' => 'URL',
					'placeholder' => 'http://example.com/admin.php',
					'value' => isset($_COOKIE['curry:remote_url']) ? $_COOKIE['curry:remote_url'] : '',
				)),
				'user' => array('text', array(
					'label' => 'User',
					'value' => isset($_COOKIE['curry:remote_user']) ? $_COOKIE['curry:remote_user'] : 'admin',
				)),
				'password' => array('password', array(
					'label' => 'Password',
					'value' => '',
				)),
				'submit' => array('submit', array(
					'class' => 'btn btn-primary',
					'label' => 'Fetch',
				)),
			),
		));

		if (isPost('code')) {
			// we have page-code
			if ($localChecksum !== $_POST['local_checksum']) {
				throw new Exception('Local pages were changed during synchronization process, aborting!');
			}
			$remoteCode = json_decode($_POST['code'], true);

			// Update selected pages
			if (isset($_POST['page'])) {
				$updatedPages = Curry_Backend_PageSyncHelper::restorePages($rootPage, $remoteCode, array_keys($_POST['page']));
				$this->addMessage(count($updatedPages).' pages updated!', self::MSG_SUCCESS);
			}

			// Delete selected pages
			if(isset($_POST['delete'])) {
				$pagesToDelete = array_keys($_POST['delete']);
				foreach($pagesToDelete as $pageId) {
					$page = PageQuery::create()->findPk($pageId);
					if (!$page)
						throw new Exception('Unable to find page to delete.');
					if (!$page->isLeaf()) {
						$this->addMessage('Unable to delete page "'.$page->getName().'" because it has subpages.', self::MSG_ERROR);
						continue;
					}
					$dependantPages = $page->getDependantPages();
					if (count($dependantPages)) {
						$this->addMessage('Unable to delete page "'.$page->getName().'" because other pages depend on it.', self::MSG_ERROR);
						continue;
					}
					$page->delete();
					$this->addMessage('Deleted page "'.$page->getName().'"', self::MSG_WARNING);
				}
			}

		} else if(isPost() && $form->isValid($_POST)) {
			// have user/password
			try {
				$context = stream_context_create(array('http' => array(
						'method'  => 'POST',
						'header'  => 'Content-type: application/x-www-form-urlencoded',
						'content' => http_build_query(array(
								'login_username' => $form->user->getValue(),
								'login_password' => $form->password->getValue(),
								'fetch' => '1',
							))
					)));
				$remote = (string)url($form->url->getValue(), array('module'=>'Curry_Backend_PageSync'));
				$remoteResponse = file_get_contents($remote, null, $context);
				if ($remoteResponse === false)
					throw new Exception('Invalid response');
				$remoteCode = json_decode($remoteResponse, true);
				if ($remoteCode === null)
					throw new Exception('Invalid json: '.$remoteResponse);

				setcookie('curry:remote_url', $form->url->getValue(), time() + 86400 * 365);
				setcookie('curry:remote_user', $form->user->getValue(), time() + 86400 * 365);
				$this->addMainContent('<form action="'.url('', $_GET).'" method="post" class="well">');
				$this->addMainContent('<input type="hidden" name="code" value="'.htmlspecialchars($remoteResponse).'" />');
				$this->addMainContent('<input type="hidden" name="local_checksum" value="'.htmlspecialchars($localChecksum).'" />');
				$this->addMainContent('<ul>');
				$this->comparePageCode($code, $remoteCode);
				$this->addMainContent('</ul>');
				$this->addMainContent('<button type="submit" class="btn btn-primary">Sync</button>');
				$this->addMainContent('</form>');
			}
			catch(Exception $e) {
				$this->addMainContent($form);
				$this->addMessage($e->getMessage(), self::MSG_ERROR);
			}
		} else {
			$this->addMainContent(self::INTRO);
			$this->addMainContent($form);
		}
	}

	/**
	 * Compare pages and show controls to take action.
	 *
	 * @param array|null $local
	 * @param array|null $remote
	 */
	protected function comparePageCode($local, $remote)
	{
		$name = $local ? $local['name'] : $remote['name'];

		$this->addMainContent('<li>');
		if(!$remote) {
			$checkbox = '<input type="checkbox" name="delete['.$local['id'].']" value="1" /> ';
			$this->addMainContent('<label class="text-error">'.$checkbox.$name.' (Deleted)</label>');
		} else if(!$local) {
			$checkbox = '<input type="checkbox" name="page['.$remote['id'].']" value="1" /> ';
			$this->addMainContent('<label class="text-success">'.$checkbox.$name.' (Added)</label>');
		} else {
			$diff = self::compareCode($local, $remote);
			if(count($diff)) {
				$status = 'Diff';
				if ($local['modified'] !== $remote['modified']) {
					$status .= ', '.($local['modified'] > $remote['modified'] ? 'local' : 'remote').
						' is newer';
				}
				$status .= ': '.join(', ', $diff);
				$checkbox = '<input type="checkbox" name="page['.$remote['id'].']" value="1" /> ';
				$this->addMainContent('<label class="text-info">'.$checkbox.$name.' ('.$status.')</label>');
			} else {
				$this->addMainContent($name);
			}
		}

		// Compare subpages
		$this->addMainContent('<ul>');
		$localPages = array();
		$remotePages = array();
		if($local) {
			foreach($local['pages'] as $page) {
				$localPages[$page['name']] = $page;
			}
		}
		if($remote) {
			foreach($remote['pages'] as $page) {
				$remotePages[$page['name']] = $page;
			}
		}
		// Compare local pages with remote
		foreach($localPages as $name => $page) {
			$remote = isset($remotePages[$name]) ? $remotePages[$name] : null;
			$this->comparePageCode($page, $remote);
		}
		// Show new pages on remote
		$remoteNew = array_diff_key($remotePages, $localPages);
		foreach($remoteNew as $page) {
			$this->comparePageCode(null, $page);
		}
		$this->addMainContent('</ul>');
		$this->addMainContent('</li>');
	}

	/**
	 * @param array $a
	 * @param array $b
	 * @return array
	 */
	protected static function compareCode(array $a, array $b)
	{
		$diff = array();
		if (self::differ($a['revision']['modules'], $b['revision']['modules']))
			$diff[] = 'modules';
		if (self::differ($a['revision'], $b['revision'], array('modules','description')))
			$diff[] = 'revision';
		if (self::differ($a, $b, array('revision','pages','id','uid','modified')))
			$diff[] = 'page';
		return $diff;
	}

	/**
	 * @param $a
	 * @param $b
	 * @param array $exclude
	 * @return bool
	 */
	protected static function differ($a, $b, $exclude = array())
	{
		if (count($exclude) && is_array($a) && is_array($b)) {
			$a = array_diff_key($a, array_combine($exclude, $exclude));
			$b = array_diff_key($b, array_combine($exclude, $exclude));
		}
		return $a !== $b;
	}
}