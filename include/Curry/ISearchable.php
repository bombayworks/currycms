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
 * Marks the Model Indexable by Curry_Backend_Indexer.
 * 
 * If you implement this and would like it compatible with the standard search module
 * you should place the following fields on the model: title, body, description, url
 * 
 * @package Curry
 */
interface Curry_ISearchable {
	/**
	 * This should return a document to index. It should propably include the same tags
	 * you get with the automatic Html indexer, title, body, description, url
	 *
	 * @return Zend_Search_Lucene_Document
	 */
	public function getSearchDocument();
}
