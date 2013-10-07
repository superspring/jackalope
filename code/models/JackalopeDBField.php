<?php
/**
 * This model stores the fields associated with the virtual class.
 */
class JackalopeDBField extends DataObject {
        static $db = array(
                'FieldName' => 'Varchar',
		'FieldType' => "Enum('Boolean, Currency, Date, Decimal, Enum, HTMLText, HTMLVarchar, Int, Percentage, SS_Datetime, Text, Time, Varchar')",
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
	const JACKALOPEERROR_DBFIELD_NOARGS = 'noargs';
	const JACKALOPEERROR_DBFIELD_EXPECTLENGTH = 'badlength';
	const JACKALOPEERROR_DBFIELD_BADENUMFORMAT = 'badnumber';
	const JACKALOPEERROR_DBFIELD_MISSINGNAME = 'badname';
	const JACKALOPEERROR_DBFIELD_DUPLICATE = 'duplicate';

	/**
	 * Certain field types can have arguments.
	 *
	 * These arguments must be numbers.
	 */
	protected function validate() {
		$validator = parent::validate();

		// @todo Add translations.
		switch ($this->FieldType) {
			// Fields that don't have arguments.
			case 'Boolean':
			case 'Currency':
			case 'Date':
			case 'Decimal':
			case 'HTMLText':
			case 'Int':
			case 'Percentage':
			case 'SS_Datetime':
			case 'Text':
			case 'Time':
				if ($this->FieldArgs) {
					// It's not empty? Problem.
					$validator->error(sprintf(
						'A %s does not have arguments', $this->FieldType
					), self::JACKALOPEERROR_DBFIELD_NOARGS);
				}
				break;

			case 'Varchar':
			case 'HTMLVarchar':
				// Expecting a number or blank.
				if (!empty($this->FieldArgs) && !is_numeric($this->FieldArgs)) {
					// It's not empty? Problem.
					$validator->error(
						"A varchar expects a maximum length for it's argument",
						self::JACKALOPEERROR_DBFIELD_EXPECTLENGTH
					);
				}
				break;

			case 'Enum':
				// @todo Run this through the Enum class to guarantee a result. Many possibilities ignored here.
				// Expecting 'abc,def,hij', 'abc'
				if (!preg_match('/([\'"])([^\1]+)\1,\s*([\'"])(.*?)\3/', $this->FieldArgs)) {
					$validator->error(
						"Expected the format 'a, b, c', 'b' - Values and default.",
						self::JACKALOPEERROR_DBFIELD_BADENUMFORMAT
					);
				}
		}

		// Expecting a field name.
		if (!$this->FieldName) {
			$validator->error(
				'Expecting a field name',
				self::JACKALOPEERROR_DBFIELD_MISSINGNAME
			);
		}

		// Does this field already exist? Can't have duplicates.
		$class = $this->Class();
		$thisfield = strtolower($this->FieldName);
		$thisclass = strtolower($this->Class()->Name);
		foreach ($class->Fields() as $field) {
			// Make this case insensitive.
			if (strtolower($field->FieldName) == $thisfield && $field->ID != $this->ID) {
				$validator->error(
					sprintf("There is already a field named '%s'", $this->FieldName),
					self::JACKALOPEERROR_DBFIELD_DUPLICATE
				);
			}
		}

		// The name is different to one of the four primary fields?
		$fields = array(
			// Lowercased.
			'id', 'classname', 'lastedited', 'created',
		);
		if (in_array($thisfield, $fields)) {
			// This is a reserved field name. Don't use it.
			$validator->error(
				sprintf("You can't use the name '%s' for a field. Try another one", $this->FieldName),
				self::JACKALOPEERROR_DBFIELD_DUPLICATE
			);
		}

		// Is this field defined by another sub class? Can't add it again.
		$classes = SS_ClassLoader::instance()->getManifest()->getDescendants();
		// If there are subclasses, they'll be on this list.
		$otherclasses = array_key_exists($thisclass, $classes) ? $classes[$thisclass] : array();
		foreach ($otherclasses as $class) {
			// Get the db and has_one fields from the class.
			$fields = DataObject::custom_database_fields($class);

			// Convert these to lower case.
			$fields = array_map(function($str) {
				return strtolower($str);
			}, array_keys($fields));

			// Is there overlap with any subclass?
			if (in_array($thisfield, $fields)) {
				// It's already been defined? Can't duplicate it.
				$validator->error(
					sprintf("The '%s' field is already defined by the '%s' class.", $this->FieldName, $class),
					self::JACKALOPEERROR_DBFIELD_DUPLICATE
				);
			}
		}

		return $validator;
	}

	/**
	 * Renders how this field looks in code.
	 */
	public function toCode() {
		// 'abc' => 'Text',
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
	 * Ensure that after a field is created it is updated immediately.
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
