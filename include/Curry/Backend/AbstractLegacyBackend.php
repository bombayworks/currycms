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
namespace Curry\Backend;
use Curry\Exception\ResponseException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for backend modules.
 *
 * @package Curry\Backend
 */
abstract class AbstractLegacyBackend extends \Curry\Backend\AbstractBackend {
	/**
	 * Redirect to URL or close dialog.
	 *
	 * @param string $url
	 * @param bool $dialogRedirect If true, this will redirect dialogs as well, otherwise just close the dialog.
	 */
	public static function redirect($url, $dialogRedirect = true)
	{
		$url = (string)$url;
		$redirectJs = '<script type="text/javascript">window.location.href = '.json_encode($url).';</script>';
		if(isAjax()) // we're in a dialog, use javascript to redirect
			self::returnPartial($dialogRedirect ? $redirectJs : '');
		else
			url($url)->redirect();
	}

	/**
	 * This function will be called before the view (by default showMain) function.
	 * 
	 */
	public function preShow()
	{
	}
	
	/**
	 * This function will be called after the view (by default showMain) function.
	 * 
	 */
	public function postShow()
	{
	}
	
	/**
	 * The default view function.
	 *
	 */
	abstract public function showMain();
	
	/**
	 * This is the main function called by Curry\Controller\Backend.
	 * 
	 * This will call the show{X}() function, where X is specified
	 * by the GET-variable 'view'. It will then render the backend using
	 * the render() function, and return the content.
	 *
	 * @return string
	 */
	public function show(Request $request)
	{
		$result = null;
		try {
			$this->preShow();
			$view = $request->query->get('view', 'Main');
			$func = 'show' . $view;
			if(method_exists($this, $func)) {
				$result = $this->$func();
			} else {
				throw new \Exception('Invalid view');
			}
			$this->postShow();
		}
		catch (ResponseException $re) {
			return $re->getResponse();
		}
		catch (\Exception $e) {
			if(!headers_sent())
				header("HTTP/1.0 500 Internal server error: ".str_replace("\n", "  ", $e->getMessage()));
			\Curry\App::getInstance()->logger->error($e->getMessage());
			$this->addMessage($e->getMessage(), self::MSG_ERROR);
			if(\Curry\App::getInstance()->config->curry->developmentMode)
				$this->addMainContent("<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>");
		}

		if (is_object($result) && $result instanceof Response)
			return $result;
		
		return $this->render();
	}
	
	/**
	 * Use template to render the backend.
	 *
	 * @return string
	 */
	public function render()
	{
		if (isset($_GET['curry_context']) && $_GET['curry_context'] == 'main') {
			return new Response($this->mainContent);
		}
		return parent::render();
	}

	public function addEvent($event)
	{
		header('X-Trigger-Events: '.json_encode($event), false);
	}

	public function createModelUpdateEvent($modelClass, $primaryKey, $action = 'update')
	{
		$this->addEvent(array(
			'type' => 'model-update',
			'params' => array($modelClass, $primaryKey, $action),
		));
	}

	/**
	 * Add trace (aka breadcrumb) item.
	 *
	 * @deprecated Use addBreadcrumb() instead.
	 * @param string $name
	 * @param string $url
	 */
	public function addTrace($name, $url)
	{
		$this->addBreadcrumb($name, $url);
	}

	/**
	 * Add a command item which opens in a dialog.
	 *
	 * @param string $name
	 * @param string $url
	 * @param string $bclass
	 * @param string $dialogTitle
	 * @param array $dialogOptions
	 */
	public function addDialogCommand($name, $url, $bclass, $dialogTitle = null, $dialogOptions = array())
	{
		if($dialogTitle === null)
			$dialogTitle = $name;
		$this->addCommand($name, $url, $bclass, array('title' => $dialogTitle, 'class' => 'dialog', 'data-dialog' => json_encode($dialogOptions)));
	}
	
	/**
	 * Return json-data to browser and exit. Will set content-type header and encode the data.
	 *
	 * @param mixed $content	Data to encode with json_encode. Note that this must be utf-8 encoded. Strings will not be encoded.
	 */
	public static function returnJson($content)
	{
		throw new ResponseException(new JsonResponse($content));
	}
	
	/**
	 * Return partial html-content to browser and exit. Will set content-type header and return the content.
	 *
	 * @param mixed $content
	 */
	public static function returnPartial($content)
	{
		throw new ResponseException($content);
	}

	/**
	 * Return data as file attachment.
	 *
	 * @param resource|string $data	A string or resource containing the data to send.
	 * @param string $contentType	The content-type header to send.
	 * @param string $filename		Filename to send to browser.
	 * @param bool $exit			Terminate the script after sending the data.
	 */
	public static function returnData($data, $contentType = 'application/octet-stream', $filename = 'file.dat', $exit = true)
	{
		header('Content-Description: File Transfer');
		header('Content-Transfer-Encoding: binary');
		header("Content-Disposition: attachment; filename=". \Curry\Util\StringHelper::escapeQuotedString($filename));
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header("Content-type: $contentType");
		if(is_string($data)) {
			header('Content-Length: ' . strlen($data));
			echo $data;
		} else if(is_resource($data) && (get_resource_type($data) === 'stream' || get_resource_type($data) === 'file')) {
			// save current
			$current = ftell($data);

			//Seek to the end
			fseek($data, 0, SEEK_END);

			//Get the size value
			$size = ftell($data) - $current;

			fseek($data, $current, SEEK_SET);
			header('Content-Length: ' . $size);
			fpassthru($data);
			if ($exit)
				fclose($data);
		} else
			throw new \Curry_Exception('Data is of unknown type.');
		if($exit)
			exit;
	}

	/**
	 * Return a file to browser and exit. Will set appropriate headers and return the content.
	 *
	 * @param string $file			Path to file
	 * @param string $contentType	The content-type header to send.
	 * @param string $filename		Filename to send to browser, uses the basename of $file if not specified.
	 * @param bool $exit			Terminate the script after sending the data.
	 * @param bool $disableOutputBuffering	Disable output buffering.
	 */
	public static function returnFile($file, $contentType = 'application/octet-stream', $filename = '', $exit = true, $disableOutputBuffering = true)
	{
		if(!$filename)
			$filename = basename($file);
		header('Content-Description: File Transfer');
		header('Content-Transfer-Encoding: binary');
		header('Content-Disposition: attachment; filename='. \Curry\Util\StringHelper::escapeQuotedString($filename));
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-type: '.$contentType);
		header('Content-Length: '.filesize($file));

		if($disableOutputBuffering) {
			while(@ob_end_flush())
				;
		}

		readfile($file);

		if($exit)
			exit;
	}
}
