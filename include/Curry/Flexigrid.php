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
 * Wrapper class for the flexigrid javascript.
 *
 * @link http://flexigrid.info/
 * @package Curry
 */
class Curry_Flexigrid {
	/**
	 * ID of element.
	 *
	 * @var string
	 */
	protected $id;
	
	/**
	 * Flexigrid options.
	 *
	 * @var array
	 */
	protected $options = array("dataType" => "json", "usepager" => true, "useRp" => true, "rp" => 25, "rpOptions" => array(10,25,50,100), "showTableToggleBtn" => false, "height" => 'auto', "width" => 'auto');
	
	/**
	 * Column specification.
	 *
	 * @var array
	 */
	protected $columns = array();
	
	/**
	 * Buttons to be added to the toolbar.
	 *
	 * @var array
	 */
	protected $buttons = array();
	
	/**
	 * Specifies searchable items.
	 *
	 * @var array
	 */
	protected $search = array();
	
	/**
	 * Default dialog options.
	 *
	 * @var array
	 */
	private $defaultDialogOptions = array('width'=>600, 'minHeight'=>150/*, 'show' => 'scale', 'hide' => 'scale'*/);
	
	/**
	 * Primary key column used to identify row.
	 *
	 * @var string
	 */
	protected $primaryKey;
	
	/**
	 * Creates a new flexigrid.
	 *
	 * @param string $id
	 * @param string $title
	 * @param string $url
	 * @param array $options
	 */
	public function __construct($id, $title, $url, array $options = array())
	{
		$this->id = $id;
		$this->options['title'] = (string)$title;
		$this->options['url'] = (string)$url;
		$this->options = array_merge($this->options, $options);
	}

	/**
	 * Add new column.
	 *
	 * @param string $name
	 * @param string $display
	 * @param array $options
	 * @param string|null $before
	 */
	public function addColumn($name, $display, array $options = array(), $before = null)
	{
		$options = array_merge(array("display" => $display, "name" => $name, "sortable" => true, "align" => "left"), $options);

		if($before)
			Curry_Array::insertBefore($this->columns, array($name => $options), $before);
		else // add to end
			$this->columns[$name] = $options;

		if(!isset($this->options['sortname']))
			$this->setDefaultSort($name);
	}
	
	/**
	 * Move column.
	 *
	 * @param string $name
	 * @param int $position
	 */
	public function moveColumn($name, $position)
	{
		$options = $this->columns[$name];
		Curry_Array::insertAt($this->columns, array($name => $options), $position);
	}

	/**
	 * Remove column by name.
	 *
	 * @param string $name
	 */
	public function removeColumn($name)
	{
		unset($this->columns[$name]);
	}

	/**
	 * Set column options.
	 * 
	 * Supported by flexigrid: width, hide, sortable, display, align.
	 *
	 * @param string $name	Identifier of the column.
	 * @param string|array $option	Array of options to set or string with the name of the option to set.
	 * @param string|null $value	Value of the option or null if array was given.
	 */
	public function setColumnOption($name, $option, $value = null)
	{
		if(is_array($name)) {
			foreach($name as $n)
				$this->setColumnOption($n, $option, $value);
		} else if(is_string($name) && isset($name, $this->columns)) {
			if(is_array($option))
				$this->columns[$name] = array_merge($this->columns[$name], $option);
			else
				$this->columns[$name][$option] = $value;
		} else {
			throw new Exception('No column with name: '.$name);
		}
	}
	
	/**
	 * Get option for column.
	 *
	 * @param string $name
	 * @param string $option
	 * @return mixed
	 */
	public function getColumnOption($name, $option)
	{
		return $this->columns[$name][$option];
	}
	
	/**
	 * Specify flexigrid options.
	 * 
	 * * height (int)
	 * * width (int|'auto')
	 * * striped (bool) apply odd/even stripes
	 * * novstripe (bool)
	 * * minwidth (int) min width of columns
	 * * minheight (int) min height of columns
	 * * resizable (bool) resizable table
	 * * url (bool)
	 * * method (string)
	 * * dataType (string) type of data loaded
	 * * errormsg (string)
	 * * usepager (bool)
	 * * nowrap (bool)
	 * * page (int) current page
	 * * total (int) total pages
	 * * useRp (bool) Use results per page select box.
	 * * rp (int) Results per page
	 * * rpOptions (array) Options in results per page select box.
	 * * title (string)
	 * * pagestat (string)
	 * * procmsg (string)
	 * * query (string)
	 * * qtype (string)
	 * * nomsg (string)
	 * * minColToggle (int) minimum allowed column to be hidden
	 * * showToggleBtn (bool) show or hide column toggle popup
	 * * hideOnSubmit (bool)
	 * * autoload (bool)
	 * * blockOpacity (float)
	 * * onToggleCol (function)
	 * * onChangeSort (function)
	 * * onSuccess (function)
	 * * onSubmit (function)
	 *
	 * @param array|string $option
	 * @param mixed $value
	 */
	public function setOption($option, $value = null)
	{
		if(is_array($option))
			$this->options = array_merge($this->options, $option);
		else
			$this->options[$option] = $value;
	}

	/**
	 * Get all columns and their options.
	 *
	 * @return array
	 */
	public function getColumns() {
		return $this->columns;
	}
	
	/**
	 * Get flexigrid option.
	 *
	 * @param string $option
	 * @return mixed
	 */
	public function getOption($option)
	{
		return $this->options[$option];
	}

	/**
	 * Specify what column to use as "primary key".
	 * 
	 * The value of this column will be passed to callback functions.
	 *
	 * @param string $primaryKey
	 */
	public function setPrimaryKey($primaryKey)
	{
		$this->primaryKey = $primaryKey;
	}
	
	/**
	 * Get column name of primary-key.
	 *
	 * @return string
	 */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}

	/**
	 * Calculate column widths.
	 */
	private function setAutoWidth()
	{
		$totalWidth = is_numeric($this->options['width']) ? (int)$this->options['width'] : 770;
		$numCols = 0;
		$setColumnWidth = 0;
		foreach($this->columns as $columnOptions) {
			if(!isset($columnOptions['hide']) || !$columnOptions['hide']) {
				if(!isset($columnOptions['width']))
					++$numCols;
				else
					$setColumnWidth += $columnOptions['width'] + 12;
			}
		}

		$totalWidth -= $setColumnWidth + 2 + 12 * $numCols; // adjust for the padding and border
		foreach($this->columns as &$columnOptions) {
			if(!isset($columnOptions['width'])) {
				if(!isset($columnOptions['hide']) || !$columnOptions['hide']) {
					$columnWidth = floor($totalWidth / $numCols);
					$totalWidth -= $columnWidth;
					--$numCols;
					$columnOptions['width'] = max($columnWidth, 40);
				} else {
					$columnOptions['width'] = 40;
				}
			}
		}
	}

	/**
	 * Set default column to sort on, and in what order.
	 *
	 * @param string $name
	 * @param string $order
	 */
	public function setDefaultSort($name, $order = "asc")
	{
		$this->options['sortname'] = $name;
		$this->options['sortorder'] = $order;
	}

	/**
	 * Add toolbar button.
	 *
	 * @param string $name
	 * @param array $options
	 */
	public function addButton($name, array $options = array())
	{
		$this->buttons[] = array_merge(array("name" => $name), $options);
	}

	/**
	 * Remove toolbar button
	 *
	 * @param mixed $nameOrIndex
	 */
	public function removeButton($nameOrIndex)
	{
		if(is_numeric($nameOrIndex)) {
			array_splice($this->buttons, $nameOrIndex, 1);
		} else {
			foreach($this->buttons as $k => $v) {
				if($v['name'] == $nameOrIndex) {
					array_splice($this->buttons, $k, 1);
					return;
				}
			}
		}
	}

	/**
	 * Add toolbar separator.
	 *
	 */
	public function addSeparator()
	{
		$this->buttons[] = array("separator" => true);
	}

	/**
	 * Add search item.
	 *
	 * @param string $name
	 * @param string $display
	 */
	public function addSearchItem($name, $display)
	{
		$this->search[] = array("name" => $name, "display" => $display);
	}
	
	/**
	 * Creates button which will do a GET-request to the given url when clicked. On completion it will reload the flexigrid.
	 *
	 * @param string $name		Name of column.
	 * @param string $bclass	CSS-class (mainly used to set icon).
	 * @param string $url	URL to request.
	 * @param array $buttonOptions
	 */
	public function addActionButton($name, $bclass, $url, $buttonOptions = array()) {
		$this->addButton($name, array_merge($buttonOptions, array('bclass' => $bclass, 'onpress' => new Zend_Json_Expr("function(com, grid) {
			var items = $('.trSelected',grid);
			var url = '$url';
			items.each(function(i) {
				url += '&{$this->primaryKey}=' + $.data(this, '{$this->primaryKey}');
			});
			$.get(url, [], function() { $('#{$this->id}').flexReload(); });
		}"))));
	}

	/**
	 * Creates a button that will reload the flexigrid and post a command along with the reload-request.
	 *
	 * @param string $name
	 * @param string $bclass
	 * @param string $cmd
	 * @param int $forcePrimaryKey
	 * @param array $buttonOptions
	 */
	public function addCommandButton($name, $bclass, $cmd, $forcePrimaryKey = 0, $buttonOptions = array())
	{
		if($forcePrimaryKey < 0) {
			$onPress = "function(com, grid) {
				$('#{$this->id}').flexReload({params: {cmd: '$cmd'}});
			}";
		} else {
			$onPress = "function(com, grid) {
				var items = $('.trSelected', grid);
				".($forcePrimaryKey ? "if(items.length == {$forcePrimaryKey}) {" : "")."
					var ids = $.map(items, function(item) { return $.data(item, '{$this->primaryKey}'); });
					$('#{$this->id}').flexReload({params: {cmd: '$cmd', 'id': ids.length == 1 ? ids[0] : ids}});
				".($forcePrimaryKey ? "}" : "")."
			}";
		}
		$this->addButton($name, array_merge($buttonOptions, array('bclass' => $bclass, 'onpress' => new Zend_Json_Expr($onPress))));
	}

	/**
	 * Creates a button which will redirect the browser to the given url.
	 *
	 * @param string $name		Name of column.
	 * @param string $bclass	CSS-class (mainly used to set icon)
	 * @param string $url		URL to navigate to.
	 * @param int $forcePrimaryKey	Add selection to url?
	 * @param array $buttonOptions
	 */
	public function addLinkButton($name, $bclass, $url, $forcePrimaryKey = 0, $buttonOptions = array())
	{
		if($forcePrimaryKey < 0) {
			$onPress = "function(com, grid) {
				window.location.href = '$url';
			}";
		} else {
			$onPress = "function(com, grid) {
				var items = $('.trSelected', grid);
				".($forcePrimaryKey ? "if(items.length == {$forcePrimaryKey}) {" : "")."
					var ids = $.map(items, function(item) { return $.data(item, '{$this->primaryKey}'); });
					window.location.href = '$url&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids});
				".($forcePrimaryKey ? "}" : "")."
			}";
		}
		$this->addButton($name, array_merge($buttonOptions, array('bclass' => $bclass, 'onpress' => new Zend_Json_Expr($onPress))));
	}

	/**
	 * Add a button which opens a dialog when clicked. Will reload the flexigrid once the dialog is closed.
	 *
	 * @param string $name	Name of the button
	 * @param string $bclass	class of the button
	 * @param string $dialogId	id for the dialog
	 * @param string $dialogTitle	title of the dialog
	 * @param string $dialogUrl		url to load the dialog from
	 * @param array $dialogOptions		options for the dialog (width, height, etc)
	 * @param integer $forcePrimaryKey	-1 = do not append, 0 = do not force, but send if set, 1 = force at least one selected
	 * @param bool $reloadOnClose
	 * @param array $buttonOptions
	 */
	public function addDialogButton($name, $bclass, $dialogId, $dialogTitle, $dialogUrl, array $dialogOptions = array(), $forcePrimaryKey = 0, $reloadOnClose = true, $buttonOptions = array())
	{
		$opts = array_merge($this->defaultDialogOptions, $reloadOnClose ? array('close' => new Zend_Json_Expr("function() { $('#{$this->id}').flexReload(); }")) : array(), $dialogOptions);
		$opts = self::json_encode($opts, Curry_Core::$config->curry->internalEncoding); // keep the internal encoding

		if($forcePrimaryKey < 0) {
			$onPress = "function(com, grid) {
				$.util.openDialog('$dialogId', '$dialogTitle', '$dialogUrl', $opts);
			}";
		} else {
			$onPress = "function(com, grid) {
				var items = $('.trSelected', grid);
				".($forcePrimaryKey ? "if(items.length == {$forcePrimaryKey}) {" : "")."
					var ids = $.map(items, function(item) { return $.data(item, '{$this->primaryKey}'); });
					$.util.openDialog('$dialogId', '$dialogTitle', '$dialogUrl&' + $.param({'{$this->primaryKey}': ids.length == 1 ? ids[0] : ids}), $opts);
				".($forcePrimaryKey ? "}" : "")."
			}";
		}

		$this->addButton($name, array_merge($buttonOptions, array('bclass' => $bclass, 'onpress' => new Zend_Json_Expr($onPress))));
	}

	/**
	 * Create "Add" button to add new element.
	 *
	 * @param string $url
	 * @param array $dialogOptions
	 * @param array $buttonOptions
	 */
	public function addAddButton($url, array $dialogOptions = array(), $buttonOptions = array())
	{
		$this->addDialogButton('Add', 'icon-plus-sign', 'add_dialog', 'Create new '.$this->options['title'], $url, $dialogOptions, -1, true, $buttonOptions);
	}

	/**
	 * Create "Edit" button to edit selected row.
	 *
	 * @param string $url
	 * @param array $dialogOptions
	 * @param array $buttonOptions
	 */
	public function addEditButton($url, array $dialogOptions = array(), $buttonOptions = array())
	{
		$this->addDialogButton('Edit', 'icon-edit', 'edit_dialog', 'Edit '.$this->options['title'], $url, $dialogOptions, 1, true, $buttonOptions);
	}

	/**
	 * Create "Delete" button to delete selected row(s).
	 *
	 * @param array $options
	 */
	public function addDeleteButton($options = array())
	{
		$this->addButton("Delete", array_merge((array) $options, array("bclass" => "icon-minus-sign", "onpress" => new Zend_Json_Expr("function(com, grid) {
			var items = $('.trSelected', grid);
			if(items.length && confirm('Delete ' + items.length + ' {$this->options['title']}? \\nWARNING: This cannot be undone.')) {
				var ids = $.map(items, function(item) { return $.data(item, '{$this->primaryKey}'); });
				$('#{$this->id}').flexReload({params: {cmd: 'delete', 'id[]': ids}});
			}
		}"))));
	}

	/**
	 * Make flexigrid sortable.
	 */
	public function makeSortable()
	{
		$this->options["onSuccess"] = new Zend_Json_Expr("function () {
			$('#{$this->id} tbody').sortable({axis: 'y', tolerance: 'pointer', delay: 250, forceHelperSize: true, helper: 'clone', forcePlaceholderSize: true, scroll: false,
				update: function() {
					$('#{$this->id}').flexReload({params: {cmd: 'reorder', reorder: $('#{$this->id} tbody').sortable('serialize')}});
				}
			});
		}");
	}

	/**
	 * Get HTML code for flexigrid.
	 *
	 * @return string
	 */
	public function getHtml()
	{
		$this->setAutoWidth();
		return
			'<table id="'.$this->id.'"><tr><td>You need javascript enabled to view this grid.</td></tr></table>'.
			'<script type="text/javascript">'.
			"\n//<![CDATA[\n".
			'$("#'.$this->id.'").find("td").text("Loading...");'."\n".
			"$.require('flexigrid', function() {\n".
			'$("#'.$this->id.'").flexigrid('.$this->getJavaScript().");\n".
			"});\n".
			"\n//]]>\n".
			'</script>';
	}

	/**
	 * Get javascript parameters.
	 *
	 * @return array
	 */
	public function getJavaScript()
	{
		$json = array_merge(
			$this->options,
			array(
				"colModel" => array_values($this->columns),
				"buttons" => count($this->buttons) ? $this->buttons : null,
				"searchitems" => count($this->search) ? $this->search : null,
			)
		);
		return self::json_encode($json);
	}
	
	/**
	 * Json encode data. Handles encoding and functions.
	 *
	 * @param mixed $value
	 * @param string $encoding
	 * @return string
	 */
	private static function json_encode($value, $encoding = 'utf-8')
	{
		$value; // if I remember correctly this had to go here to prevent a crash in some php-version :)
		// make sure we convert all strings to utf8
		array_walk_recursive($value, create_function('&$value', '
			if(is_string($value))
				$value = Curry_String::toEncoding($value, "utf-8");
			if($value instanceof Zend_Json_Expr)
				$value = new Zend_Json_Expr(Curry_String::toEncoding((string)$value, "utf-8"));
		'));
		// return string in proper encoding
		return iconv('utf-8', $encoding, Zend_Json::encode($value, false, array('enableJsonExprFinder' => true)));
	}
	
	/**
	 * Get JSON data.
	 *
	 * @return array
	 */
	public function getJSON()
	{
		return array();
	}
}
