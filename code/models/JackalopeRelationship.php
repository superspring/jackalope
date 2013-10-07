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
	const JACKALOPEERROR_RELATIONSHIP_MISSINGNAME = 'missingname';
	const JACKALOPEERROR_RELATIONSHIP_MISSINGTYPE = 'missingtype';
	const JACKALOPEERROR_RELATIONSHIP_MISSINGFIELD = 'missingfield';
	const JACKALOPEERROR_RELATIONSHIP_DUPLICATEFIELD = 'duplicate';

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

		// Does this field already exist? Can't have duplicates.
		$class = $this->Class();
		$thisfield = strtolower($this->FieldName);
		$thisclass = strtolower($this->Class()->Name);
		foreach ($class->Relationships() as $field) {
			// Make this case insensitive.
			if (strtolower($field->FieldName) == $thisfield && $field->ID != $this->ID) {
				$validator->error(
					sprintf("There is already a field named '%s'", $this->FieldName),
					self::JACKALOPEERROR_RELATIONSHIP_DUPLICATEFIELD
				);
			}
		}

		// @todo Add in more validations for the other mix of bad names.

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
	public function toCode() {
		$type = $this->FieldType;
		if ($this->FieldArgs) {
			// Add the optional arguments.
			$type .= '(' . $this->FieldArgs . ')';
		}

		return $this->quoteString($this->FieldName) . ' => ' . $this->quoteString($type);
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

	/**
	 * Updates the class models with the new field.
	 */
	public function onAfterWrite() {
		parent::onAfterWrite();

		JackalopeController::rebuild($this->Class()->Name);
	}

	/**
	 * Remove this field from the database.
	 */
	public function onAfterDelete() {
		parent::onAfterDelete();

		JackalopeController::deleteField($this->ClassID, $this->FieldName);
	}
}
