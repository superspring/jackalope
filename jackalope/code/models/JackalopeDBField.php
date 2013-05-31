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
	const JACKALOPEERROR_DBFIELD_NOARGS = 1;
	const JACKALOPEERROR_DBFIELD_EXPECTLENGTH = 2;
	const JACKALOPEERROR_DBFIELD_BADENUMFORMAT = 3;
	const JACKALOPEERROR_DBFIELD_MISSINGNAME = 4;

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

		return $validator;
	}

	/**
	 * Renders how this field looks in code.
	 */
	public function toCode() {
		// 'abc' => 'Text',
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
			'Boolean', 'Currency', 'Date', 'Decimal', 'Enum', 'HTMLText', 'HTMLVarchar', 'Int', 'Percentage', 'SS_Datetime', 'Text', 'Time', 'Varchar',
		);
		$list = array_combine($list, $list);

		// FieldName and FieldType may be anything.
		$fname = new TextField('FieldName', 'Database Field Name');
		$ftype = new DropdownField('FieldType', 'Field Type', $list);
		$fargs = new TextField('FieldArgs', 'Arguments to the relationship');

		$fields = new FieldList(
			$fname, $ftype, $fargs
		);
		return $fields;
	}
}
