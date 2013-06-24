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

	const JACKALOPEERROR_DUPLICATE_NAME = 5;
	const JACKALOPEERROR_BAD_NAME = 6;

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

	/**
	 * Before this object is deleted, remove it's fields and table.
	 */
	public function onBeforeDelete() {
		// Ensure all dependancies are deleted too.
		foreach ($this->Fields() as $field) {
			$field->delete();
		}
		foreach ($this->Relationships() as $relationship) {
			$relationship->delete();
		}

		// Delete the table as a whole.
		JackalopeController::deleteTable($this);
	}

	/**
	 * Ensure that this Jackalope class does not already exist.
	 */
	protected function validate() {
		$validator = parent::validate();

		// @todo Add translations.
		$other = JackalopeClassName::get('JackalopeClassName', sprintf(
			"Name = '%s'", Convert::raw2sql($this->Name)
		));
		if ($other->count() !== 0) {
			// There are other ones? No.
			$validator->error('There is already a class with this name', self::JACKALOPEERROR_DUPLICATE_NAME);
		}

		// Ensure the characters are valid.
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->Name)) {
			// Invalid PHP class name.
			$validator->error('Class name may only contain alphanumeric characters and underscores', self::JACKALOPEERROR_BAD_NAME);
		}

		return $validator;
	}
}
