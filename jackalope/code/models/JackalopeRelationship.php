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
	const JACKALOPEERROR_RELATIONSHIP_MISSINGNAME = 1;

	/**
	 * Each of these must have:
	 * - A name which is non-empty.
	 * - A type which is a valid object.
	 */
	protected function validate() {
		$validator = parent::validate();

		// @todo Add translations.
		if (!$this->FieldName) {
			$validator->error('A field must have a name', self::JACKALOPEERROR_RELATIONSHIP_MISSINGNAME);
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
		return new FieldList();
	}
}