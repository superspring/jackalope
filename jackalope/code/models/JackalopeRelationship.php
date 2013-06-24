<?php
/**
 * This model stores the fields associated with the virtual class.
 */
class JackalopeRelationship extends DataObject {
	static $db = array(
		'Type' => "Enum('many_many, belongs_many_many, searchable_fields, summary_fields, has_one, belongs_to, defaults, has_many')", 
		'FieldName' => 'Varchar',
		'FieldType' => 'Varchar',
		'FieldArgs' => 'Varchar',
	);
	static $has_one = array(
		'Class' => 'JackalopeClassName',
	);
	static $summary_fields = array(
		'FieldName',
		'FieldType',
	);

	/**
	 * A list of potential validation errors.
	 */
	const JACKALOPEERROR_RELATIONSHIP_MISSINGNAME = 6;
	const JACKALOPEERROR_RELATIONSHIP_MISSINGTYPE = 7;
	const JACKALOPEERROR_RELATIONSHIP_MISSINGFIELD = 8;

	/**
	 * Each of these must have:
	 * - A name which is non-empty.
	 * - A type which is a valid object.
	 */
	protected function validate() {
		$validator = parent::validate();

		// @todo Add translations.
		if (!$this->FieldName) {
			// A relationship must have a name.
			$validator->error('A field must have a name', self::JACKALOPEERROR_RELATIONSHIP_MISSINGNAME);
		}
		if (!$this->FieldType) {
			// A relationship must associate to another class.
			$validator->error('A relationship must associate to another class', self::JACKALOPEERROR_RELATIONSHIP_MISSINGFIELD);
		}
		if (!$this->Type) {
			// A relationship must associate to another class.
			$validator->error('A relationship have a type', self::JACKALOPEERROR_RELATIONSHIP_MISSINGTYPE);
		}

		return $validator;
	}

	/**
	 * Modify the FieldType to only show available classes.
	 */
	public function getCMSFields() {
		// Get the old fields.
		$fields = parent::getCMSFields();

		// Create a new dropdown list of classes.
		$classfield = new DropdownField('FieldType', 'Class association', $this->getClasses());

		// Replace it.
		$fields->replaceField('FieldType', $classfield);
		return $fields;
	}

	/**
	 * Get a list of models that can be used for an association.
	 */
	protected function getClasses() {
		return ClassInfo::subclassesFor('DataObject');
	}

	/**
	 * Generates the code to reflect this relationship entry.
	 */
	public function getCode() {
		return $this->quoteString($this->FieldName) . ' => ' . $this->quoteString($this->FieldType);
	}

	/**
	 * Returns a quoted string.
	 */
	protected function quoteString($str) {
		// Does it contain a '?
		if (strpos($str, "'") === false) {
			return "'$str'";
		}

		// Special chars
		foreach (array(
			"\\", "$",
		) as $char) {
			if (strpos($str, $char) !== false) {
				// There is a special char? Don't double quote.
				return "'" . str_replace("$", "\\$", addslashes($str)) . "'";
			}
		}

		// No special chars? Just a '?
		return '"' . $str . '"';
	}

	public function getPopupFields() {
		// List of options.
		$list = array(
			'many_many', 'belongs_many_many', 'searchable_fields', 'summary_fields', 'has_one', 'belongs_to', 'defaults', 'has_many',
		);
		$list = array_combine($list, $list);

		// Type may be one of it's values.
		$type = new DropdownField('Type', 'Field Type', $list);

		// FieldName and FieldType may be anything.
		$fname = new TextField('FieldName', 'Database Field Name');
		$ftype = new TextField('FieldType', 'Database Field Relationship');
		$fargs = new TextField('FieldArgs', 'Any arguments to this relationship?');

		$fields = new FieldList(array(
			$type, $fname, $ftype, $fargs
		));
		return $fields;
	}

	/**
	 * Updates the class models with the new field.
	 */
	public function onAfterWrite() {
		JackalopeController::rebuild();
	}

	/**
	 * Remove this field from the database.
	 */
	public function onAfterDelete() {
		JackalopeController::deleteField($this->ClassID, $this->FieldName);
	}
}
