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
 * Creates Zend Forms automagically from The TableMap of the model.
 * 
 * The form can be prefilled with values from a model instance and a
 * model instance can be filled from the Form values.
 * 
 * @package Curry\Form
 */
class Curry_Form_ModelForm extends Curry_Form
{
	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected $modelClass;

	/**
	 * Model table map.
	 *
	 * @var TableMap
	 */
	protected $modelMap;

	/**
	 * Ignore primary keys?
	 *
	 * @var bool
	 */
	protected $ignorePks = true;

	/**
	 * Ignore foreign keys?
	 *
	 * @var bool
	 */
	protected $ignoreFks = true;

	/**
	 * Specify type and options for column elements.
	 *
	 * @var array
	 */
	protected $columnElements = array();

	/**
	 * Create elements from relations.
	 *
	 * @var array
	 */
	protected $withRelations = array();

	/**
	 * Callback to trigger when calling fillModel().
	 *
	 * @var callable
	 */
	protected $onFillModel = null;


	/**
	 * Callback to trigger when calling fillForm().
	 *
	 * @var callable
	 */
	protected $onFillForm = null;

	/**
	 * Creates the form with all the elements.
	 *
	 * Options are:
	 *
	 * * ignorePks:boolean    If the primary-key fields should be present in the form
	 * * ignoreFks:boolean    If the foreign-key fields should be present in the form
	 *
	 * The rest are passed to the Curry Form.
	 *
	 * @param string $modelClass
	 * @param mixed $options
	 */
	public function __construct($modelClass, $options = null)
	{
		parent::__construct($options);
		$this->modelClass = $modelClass;
		$this->modelMap = PropelQuery::from($this->modelClass)->getTableMap();
		$this->createElements();
	}

	/**
	 * @param callable $onFillForm
	 */
	public function setOnFillForm($onFillForm)
	{
		$this->onFillForm = $onFillForm;
	}

	/**
	 * @return callable
	 */
	public function getOnFillForm()
	{
		return $this->onFillForm;
	}

	/**
	 * @param callable $onFillModel
	 */
	public function setOnFillModel($onFillModel)
	{
		$this->onFillModel = $onFillModel;
	}

	/**
	 * @return callable
	 */
	public function getOnFillModel()
	{
		return $this->onFillModel;
	}

	/**
	 * Fills the model with the values from the form. Doesn't save the model
	 * 
	 * @param BaseObject $instance
	 */
	public function fillModel(BaseObject $instance) {
		$values = $this->getValues();
		foreach($this->getElementColumns() as $column) {
			$name = strtolower($column->getName());
			$element = $this->getElement($name);
			if($element && array_key_exists($name, $values))
				$this->setColumnValue($instance, $column, $values[$name]);
		}
		foreach($this->modelMap->getRelations() as $relation) {
			$name = 'relation__'.strtolower($relation->getName());
			$element = $this->getElement($name);
			if($element && array_key_exists($name, $values))
				$this->setRelationValue($instance, $relation, $values[$name]);
		}
		if (is_callable($this->onFillModel))
			call_user_func($this->onFillModel, $instance, $this, $values);
	}

	/**
	 * Fills the form with the values from a model instance.
	 * 
	 * @param BaseObject $instance
	 */
	public function fillForm(BaseObject $instance) {
		foreach($this->getElementColumns() as $column) {
			$element = $this->getElement(strtolower($column->getName()));
			if($element)
				$element->setValue($this->getColumnValue($instance, $column));
		}
		foreach($this->modelMap->getRelations() as $relation) {
			$name = 'relation__'.strtolower($relation->getName());
			$element = $this->getElement($name);
			if($element)
				$element->setValue($this->getRelationValue($instance, $relation));
		}
		if (is_callable($this->onFillForm))
			call_user_func($this->onFillForm, $instance, $this);
	}

	/**
	 * Get column value from instance.
	 *
	 * @param BaseObject $instance
	 * @param ColumnMap $column
	 * @return mixed
	 */
	protected function getColumnValue(BaseObject $instance, ColumnMap $column) {
		$value = $instance->{'get'.$column->getPhpName()}();
		if(is_resource($value))
			$value = stream_get_contents($value);
		if($column->getType() == PropelColumnTypes::PHP_ARRAY && $value !== null)
			$value = join("\n", $value);
		return $value;
	}

	/**
	 * Set column value from instance.
	 *
	 * @param BaseObject $instance
	 * @param ColumnMap $column
	 * @param mixed $value
	 */
	protected function setColumnValue(BaseObject $instance, ColumnMap $column, $value) {
		if($column->getType() === PropelColumnTypes::PHP_ARRAY && !is_array($value)) {
			if(is_string($value) && !empty($value))
				$value = explode("\n", $value);
			else if($value !== null)
				$value = array();
		}
		$instance->{'set'.$column->getPhpName()}($value);
	}

	/**
	 * Get relation value from instance.
	 *
	 * @param BaseObject $instance
	 * @param RelationMap $relation
	 * @return mixed
	 */
	protected function getRelationValue(BaseObject $instance, RelationMap $relation) {
		switch($relation->getType()) {
			case RelationMap::ONE_TO_MANY:
			case RelationMap::MANY_TO_MANY:
				$getter = 'get'.$relation->getPluralName();
				$value = $instance->{$getter}();
				return $value->toKeyValue('PrimaryKey','PrimaryKey');
				break;
			case RelationMap::MANY_TO_ONE:
			case RelationMap::ONE_TO_ONE:
				$getter = 'get'.$relation->getName();
				$value = $instance->{$getter}();
				return $value ? $value->getPrimaryKey() : null;
				break;
		}
	}

	/**
	 * Set relation value from instance.
	 *
	 * @param BaseObject $instance
	 * @param RelationMap $relation
	 * @param mixed $value
	 */
	protected function setRelationValue(BaseObject $instance, RelationMap $relation, $value) {
		switch($relation->getType()) {
			case RelationMap::ONE_TO_MANY:
			case RelationMap::MANY_TO_MANY:
				$setter = 'set'.$relation->getPluralName();
				$otherTable = $relation->getRightTable()->getPhpName();
				$instance->{$setter}(PropelQuery::from($otherTable)->findPks($value));
				break;
			case RelationMap::MANY_TO_ONE:
			case RelationMap::ONE_TO_ONE:
				$setter = 'set'.$relation->getName();
				$otherTable = $relation->getRightTable()->getPhpName();
				$instance->{$setter}(PropelQuery::from($otherTable)->findPk($value));
				break;
		}
	}

	public function getModelClass()
	{
		return $this->modelClass;
	}

	/**
	 * @param array $value
	 */
	public function setColumnElements(array $value)
	{
		$this->columnElements = $value;
	}

	/**
	 * @return array
	 */
	public function getColumnElements()
	{
		return $this->columnElements;
	}

	/**
	 * @param array $withRelations
	 */
	public function setWithRelations(array $withRelations)
	{
		$this->withRelations = $withRelations;
	}

	/**
	 * @return array
	 */
	public function getWithRelations()
	{
		return $this->withRelations;
	}

	/**
	 * Ignore primary keys?
	 *
	 * @param bool $value
	 */
	public function setIgnorePks($value) {
		$this->ignorePks = $value;
	}

	/**
	 * Ignore primary keys?
	 *
	 * @return bool
	 */
	public function getIgnorePks() {
		return $this->ignorePks;
	}

	/**
	 * Ignore foreign keys?
	 *
	 * @param bool $value
	 */
	public function setIgnoreFks($value) {
		$this->ignoreFks = $value;
	}

	/**
	 * Ignore foreign keys?
	 *
	 * @return bool
	 */
	public function getIgnoreFks() {
		return $this->ignoreFks;
	}

	public function createElement($type, $name, $options = null)
	{
		$columnElement = isset($this->columnElements[$name]) ? $this->columnElements[$name] : null;
		if($columnElement === false)
			return null;

		if(is_string($columnElement)) {
			$type = $columnElement;
		} else if(is_array($columnElement)) {
			list($type, $o) = $columnElement;
			$options = array_merge((array)$options, $o);
		}

		return parent::createElement($type, $name, $options);
	}

	protected function getColumnNameAndOptions(ColumnMap $column)
	{
		$name = strtolower($column->getName());
		$relation = $column->getRelation();
		$options = array(
			'label' => $relation ? $relation->getName() : ucfirst(str_replace("_", " ", $name)),
			'id' => 'table-'.str_replace('_', '-', $column->getTableName()).'-column-'.str_replace("_", "-", $name).rand(),
		);
		return array($name, $options);
	}

	/**
	 * Creates a form element from a columnMap.
	 * 
	 * @param ColumnMap $column
	 * @return Zend_Form_Element
	 */
	public function createElementFromColumn(ColumnMap $column) {
		$type = 'text';
		list($name, $options) = $this->getColumnNameAndOptions($column);

		switch($column->getType()) {
		case PropelColumnTypes::PHP_ARRAY; // array contains one element per row
			$type = 'textarea';
			$options['wrap'] = 'off';
			$options['rows'] = 6;
			$options['cols'] = 50;
			break;
		case PropelColumnTypes::LONGVARCHAR:
			$type = 'textarea';
			$options['rows'] = 6;
			$options['cols'] = 50;
			break;
		case PropelColumnTypes::DATE:
			$type = 'date';
			break;
		case PropelColumnTypes::TIMESTAMP:
			$type = 'dateTime';
			break;
		case PropelColumnTypes::BOOLEAN:
			$type = 'checkbox';
			break;
		case PropelColumnTypes::ENUM;
			$type = 'select';
			$options['multiOptions'] = array_combine($column->getValueSet(), $column->getValueSet());
			break;
		case PropelColumnTypes::DOUBLE:
		case PropelColumnTypes::FLOAT:
		case PropelColumnTypes::INTEGER:
		case PropelColumnTypes::VARCHAR:
		default:
			break;
		}
		return $this->createElement($type, $name, $options);
	}

	/**
	 * Create form element from foreign key.
	 *
	 * @param ColumnMap $column
	 * @return Zend_Form_Element
	 */
	public function createElementFromForeignKey(ColumnMap $column) {
		list($name, $options) = $this->getColumnNameAndOptions($column);
		// Find the related elements in another table
		$related = array();
		$relColName = $column->getRelatedColumn()->getPhpName();
		$query = PropelQuery::from($column->getRelatedTable()->getPhpName())
			->setFormatter(ModelCriteria::FORMAT_ON_DEMAND);
		foreach($query->find() as $obj) {
			$k = (string)$obj->{'get'.$relColName}();
			$v = method_exists($obj, "__toString") ? (string)$obj : $k;
			$related[$k] = $v;
		}
		$options['multiOptions'] = $related;
		return $this->createElement('select', $name, $options);
	}

	/**
	 * Create form element from relation.
	 *
	 * @param RelationMap $relation
	 * @return Zend_Form_Element
	 */
	public function createElementFromRelation(RelationMap $relation) {
		$name = 'relation__'.strtolower($relation->getName());
		$options = array(
			'id' => $name.rand(),
			'multiOptions' => $this->getMultiOptsFromRelation($relation),
		);
		switch($relation->getType()) {
		case RelationMap::ONE_TO_MANY:
		case RelationMap::MANY_TO_MANY:
			$options['label'] = $relation->getName().'s';
			$element = $this->createElement('multiselect', $name, $options);

			break;
		case RelationMap::MANY_TO_ONE:
		case RelationMap::ONE_TO_ONE:
			$options['label'] = $relation->getName();
			$element = $this->createElement('select', $name, $options);
			break;
		}
		return $element;
	}

	/**
	 * Create array of options for relation.
	 *
	 * @param RelationMap $relation
	 * @return array
	 */
	public function getMultiOptsFromRelation(RelationMap $relation) {
		$otherTable = $relation->getRightTable();
		$objs = PropelQuery::from($otherTable->getPhpName())
			->setFormatter(ModelCriteria::FORMAT_ON_DEMAND)
			->find();
		$opts = array();
		foreach($objs as $obj) {
			if(method_exists($obj, '__toString')) {
				$opts[$obj->getPrimaryKey()] = $obj->__toString();
			} else {
				$opts[$obj->getPrimaryKey()] = $obj->getPrimaryKey();
			}
		}
		return $opts;
	}

	/**
	 * Get columns to create elements from.
	 *
	 * @return array
	 */
	public function getElementColumns() {
		$columns = array();
		foreach($this->modelMap->getColumns() as $column) {
			if($column->isForeignKey() && $this->ignoreFks) {
				continue;
			}
			if($column->isPrimaryKey() && $this->ignorePks) {
				continue;
			}
			$columns[strtolower($column->getName())] = $column;
		}
		if(array_key_exists('sortable', ($tmp = $this->modelMap->getBehaviors()))) {
			unset($columns[strtolower($tmp['sortable']['rank_column'])]);
		}
		return $columns;
	}

	/**
	 * Create elements for columns.
	 */
	protected function createElements() {
		foreach($this->getElementColumns() as $column) {
			if($column->isForeignKey())
				$element = $this->createElementFromForeignKey($column);
			else
				$element = $this->createElementFromColumn($column);
			if($element)
				$this->addElement($element);
		}
		foreach($this->withRelations as $relation) {
			$this->withRelation($relation);
		}
	}

	/**
	 * Create element from relation.
	 *
	 * @param $name
	 */
	public function withRelation($name) {
		$this->addElement($this->createElementFromRelation($this->modelMap->getRelation($name)));
	}
}

