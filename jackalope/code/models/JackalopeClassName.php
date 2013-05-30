<?php
/**
 * This model stores the new name of the class.
 */
class JackalopeClassName extends DataObject {
	static $db = array(
		'Name' => 'Varchar',
	);
	static $has_many = array(
		'Fields'        => 'JackalopeDBField',
		'Relationships' => 'JackalopeRelationship',
	);
	static $searchable_fields = array(
		'Name' => array(
			'field'  => 'TextField',
			'filter' => 'PartialMatchFilter',
		),
	);

	/**
	 * Modify the configuration options to combine relationships with classes.
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		// Remove the extra tabs.
		$fields->removeByName('Fields');
		$fields->removeByName('Relationships');

		foreach (array(
			// Add the generic DB fields.
			'Fields' => 'JackalopeDBField',
			// Add the relationship fields.
			'Relationships' => 'JackalopeRelationship',
		) as $field => $class) {
			$field = new ComplexTableField($this, $field, $class, array(), 'getPopupFields');
			$field->setParentClass($class);
			$fields->addFieldToTab('Root.Main', $field);
		}

		return $fields;
	}
}
