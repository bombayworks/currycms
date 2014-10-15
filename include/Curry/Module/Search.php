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
namespace Curry\Module;

/**
 * Search in the search-index and show matching results.
 *
 * Requires a template, available variables:
 *
 * * Query (int): Input query.
 * * NumHits (int): Number of matching documents.
 * * Total (int): Total number of searched documents.
 * * hits (array of Documents): List of matching documents.
 *   * Title (string): Document title.
 *   * Description (string): Document description.
 *   * Url (string): URL to document.
 *   * Snippet (string): Snippet of description highlighting the search terms.
 *   * Score (number): A matching score calculated by lucene search.
 *   * Fields (array): An array of all stored fields for this document.
 *   * RelatedObject (object): The object related to this document. For page hits, this would be the Page object.
 *
 * @package Curry\Module
 */
class Search extends AbstractModule {
	/**
	 * Snippet max character length.
	 *
	 * @var int
	 */
	protected $snippetLength = 255;
	
	/**
	 * Only include matches for this language?
	 *
	 * @var bool
	 */
	protected $onlyThisLanguage = true;
	
	/**
	 * Append wildcard to query if it results in 0 hits.
	 *
	 * @var bool
	 */
	protected $alwaysWildcard = false;
	
	/** {@inheritdoc} */
	public function getCacheProperties()
	{
		return new CacheProperties($this->app->request->query->all());
	}

	/** {@inheritdoc} */
	public static function getPredefinedTemplates()
	{
		return array(
			'HTML definition list' => <<<TPL
<h2>Search results</h2>
<dl>
	{% for hit in hits %}
	<dt><a href="{{hit.Url}}">{{hit.Title}}</a></dt>
	<dd>{{hit.Snippet|raw}}</dd>
	{% endfor %}
</dl>
TPL
		);
	}

	/** {@inheritdoc} */
	public function toTwig()
	{
		$r = $this->app->request;
		$luceneIndex = $this->app->index;
		$vars = array();
		$vars['Total'] = $luceneIndex->numDocs();
		
		if (($query = $r->query->get('query'))) {
			$query = trim($query);
			$hits = $luceneIndex->find($query);
			
			if($this->alwaysWildcard && count($hits) === 0) {
				$hits = $luceneIndex->find($query.'*');
			}

			$tmp = array();
			if($this->onlyThisLanguage) {
				foreach($hits as $hit) {
					try{
						if($hit->locale == \Curry_Language::getLangCode())
							$tmp[] = $hit;
					} catch(\Zend_Search_Lucene_Exception $e) {
						$tmp[] = $hit;
					}
				}
				$hits = $tmp;
			}
			$vars['Query'] = $query;
			$vars['NumHits'] = count($hits);
			$vars['hits'] = new \Curry_Twig_CollectionWrapper($hits, array($this, 'getHitProperties'));
		}

		return $vars;
	}
	
	/**
	 * Get twig properties for matching search document.
	 *
	 * @param \Zend_Search_Lucene_Search_QueryHit $hit
	 * @return array
	 */
	public function getHitProperties(\Zend_Search_Lucene_Search_QueryHit $hit)
	{
		$r = $this->app->request;
		$snippet = \Curry\Util\StringHelper::toInternalEncoding($hit->body, 'utf-8');
		$snippet = self::createSearchSnippet($snippet, $r->query->get('query'), $this->snippetLength);
		
		$relatedObject = null;
		$model = \Curry\Util\StringHelper::toInternalEncoding($hit->model, 'utf-8');

		$fields = array();
		foreach($hit->getDocument()->getFieldNames() as $fieldName)
			$fields[$fieldName] = $hit->{$fieldName};

		return array(
			'Title' => \Curry\Util\StringHelper::toInternalEncoding($hit->title, 'utf-8'),
			'Description' => \Curry\Util\StringHelper::toInternalEncoding($hit->description, 'utf-8'),
			'Url' => \Curry\Util\StringHelper::toInternalEncoding($hit->url, 'utf-8'),
			'Snippet' => $snippet,
			'Score' => $hit->score,
			'Fields' => $fields,
			'RelatedObject' => new \Curry\Util\OnDemand(array($this, 'getRelatedObject'), $model, unserialize($hit->model_id)),
		);
	}
	
	/**
	 * Twig callback to fetch related object.
	 *
	 * @param string $model
	 * @param mixed $id
	 * @return array|null
	 */
	public function getRelatedObject($model, $id)
	{
		$object = \PropelQuery::from($model)->findPk($id);
		if($object) {
			if(method_exists($object, 'toTwig'))
				return $object->toTwig();
			return $object->toArray();
		}
		return null;
	}
	
	/**
	 * Create snippet from string with specified length.
	 *
	 * @param string $content
	 * @param string $query
	 * @param int $maxlength
	 * @return string
	 */
	protected static function createSearchSnippet($content, $query, $maxlength)
	{
		$keywords = array();
		foreach(preg_split('/\s+/i', $query) as $keyword)
			$keywords[] = preg_quote($keyword);
		
		$content = preg_replace('/\s+/', ' ', $content);
		$parts = preg_split('/('.join('|', $keywords).')/i', $content, null, PREG_SPLIT_DELIM_CAPTURE);
		if(count($parts) <= 1)
			return substr($content, 0, $maxlength);
		
		$maxMatches = ceil($maxlength / 60);
		$matches = max(1, min($maxMatches, ceil( (count($parts) - 1) / 2 )));
		$lengthPerMatch = floor($maxlength / $matches);
		
		$c = 0;
		$snippet = "";
		reset($parts);
		$pre = current($parts);
		while(($keyword = next($parts))) {
			$post = next($parts);
			if($post === false) {
				$l = $lengthPerMatch - strlen($keyword);
				$preLength = floor($l * 0.25);
				$preText = preg_match('/\s.{0,' . $preLength . '}$/mu', $pre, $m) ? (empty($snippet)?'...':'') . $m[0] : substr($pre, -$preLength);
				
				$snippet .= $preText . "<strong>" . $keyword . "</strong>";
				break;
			} else {
				$l = $lengthPerMatch - strlen($keyword);
				
				// pre-text
				$preLength = floor($l * 0.25);
				if(strlen($pre) < $preLength)
					$preText = $pre;
				else
					$preText = preg_match('/\s.{0,' . $preLength . '}$/mu', $pre, $m) ? (empty($snippet)?'...':'') . $m[0] : substr($pre, -$preLength);
				
				// post-text
				$postLength = $l - strlen($preText);
				if(strlen($post) < $postLength)
					$postText = $post;
				else
					$postText = preg_match('/^.{0,' . $postLength . '}\s/mu', $post, $m) ? $m[0] . '...' : substr($post, 0, $postLength);
				
				$snippet .= $preText . "<strong>" . $keyword . "</strong>" . $postText;
				
				$pre = substr($post, strlen($postText));
			}
			if(++$c >= $maxMatches)
				break;
		}
		
		return $snippet;
	}

	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new \Curry_Form_SubForm(array(
			'elements' => array(
				'snippet_length' => array('text', array(
					'label' => 'Snippet length',
					'value' => $this->snippetLength,
				)),
				'only_this_language' => array('checkbox', array(
					'label' => 'Results from page-language only',
					'value' => $this->onlyThisLanguage,
				)),
				'always_wildcard' => array('checkbox', array(
					'label' => 'Always wildcard',
					'description' => 'Always append a wildcard (*) to the search',
					'value' => $this->alwaysWildcard,
				))
			)
		));
		
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->snippetLength = $values['snippet_length'];
		$this->onlyThisLanguage = $values['only_this_language'];
		$this->alwaysWildcard = $values['always_wildcard'];
	}
}
