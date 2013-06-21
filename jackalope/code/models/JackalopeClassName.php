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
	static $indexes = array(
		'NameIdx' => array(
			'type'  => 'unique',
			'value' => 'Name',
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

		// List options for this class.
		foreach (array(
			// Add the generic DB fields.
			'Fields' => 'JackalopeDBField',
			// Add the relationship fields.
			'Relationships' => 'JackalopeRelationship',
		) as $field => $class) {
			$field = new ComplexTableField($this, $field, $class, array(), 'getPopupFields');
			$fields->addFieldToTab('Root.Main', $field);
		}

		return $fields;
	}

	/**
	 * After any class changes, update the database.
	 */
	public function onAfterWrite() {
		JackalopeController::rebuild();
	}
}
