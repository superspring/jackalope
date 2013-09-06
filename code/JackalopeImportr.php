<?php
/**
 * Provides a functionality to import code to a virtual class.
 */
class JackalopeImporter extends DataExtension {
	const CMD_IMPORT = 'import';

	/**
	 * Tell sake more about this command.
	 */
	public function commands(&$list) {
		$list[self::CMD_IMPORT] = array($this, 'prepareImport', true);
	}

	/**
	 * Gives sake a brief for the help section.
	 */
	public function help_brief(&$details) {
		$details[self::CMD_IMPORT] = 'Provides the ability to import code to a virtual class.';
	}

	/**
	 * Gives sake a list of the parameters used.
	 */
	public function help_parameters(&$details) {
		$details[self::CMD_IMPORT] = array(
			'Each argument can be a class to import. Virtual fields may be deleted to reflect the code.',
			'--all imports all Jackalope classes found in code.',
		);
	}

	/**
	 * Gives sake a list of examples of how to use this command.
	 */
	public function help_examples(&$examples) {
		$examples[self::CMD_IMPORT] = array(
			self::CMD_IMPORT . " MyExampleClass - finds the 'MyExampleClass' and adds it to the database",
			self::CMD_IMPORT . ' --all - Adds all classes found in code',
		);
	}

	/**
	 * Get Jackalope Classes from code.
	 *
	 * @param array<string>
	 *   Returns a list of classnames.
	 */
	protected function getClasses() {
		// Prepare variables.
		$results = array();

		// Go through all the classes to find Jackalope ones.
		foreach(get_declared_classes() as $classname) {
			// Find the extended classes.
			if (strpos($classname, 'JackalopeVirtualClass') === 0) {
				$results[substr($classname, 22)] = $this->getFields($classname);
			}
			// A new class?
			else if (isset($classname::$JACKALOPEVIRTUALCLASS) && $classname::$JACKALOPEVIRTUALCLASS === true) {
				$results[$classname] = $this->getFields($classname);
			}
		}

		return $results;
	}

	/**
	 * Extracts the fields from a given class.
	 *
	 * @param string $classname
	 *   The name of the class
	 *
	 * @return array<string => array<string => string>>
	 *   A mapping of field type to field name to field value.
	 */
	protected function getFields($classname) {
		// Prepare variables.
		$types = array(
			'db', 'many_many', 'belongs_many_many', 'searchable_fields',
			'summary_fields', 'has_one', 'belongs_to', 'defaults', 'has_many',
		);
		$results = array();

		// Extract the given fields from the class.
		foreach ($types as $type) {
			if (isset($classname::$$type)) {
				$results[$type] = $classname::$$type;
			}
		}

		// Done.
		return $results;
	}

	/**
	 * Reads the commands to determine how to output the classes.
	 */
	public function prepareImport($args) {
		// Prepare variables.
		$useclasses = array();

		// Which classes are we looking for?
		$allclasses = $this->getClasses();

		foreach ($args as $class => $fields) {
			if ($class == '--all') {
				foreach ($allclasses as $class => $fields) {
					$useclasses[] = $class;
				}
			}
			else if (array_key_exists($class, $allclasses)) {
				$useclasses[] = $class;
			}
			else {
				// Unable to find $class?
				printf("Error finding class '%s'\n", $class);
				die();
			}
		}

		// Is this going into a module or stdout?
		if (empty($args)) {
			return $this->showError('Expected either --stdout or a module for the first argument');
		}
		$module = array_shift($args);
		$display_output = $module == '--stdout';

		// Which classes to import?
		$classes = $args;
		if (reset($classes) == '--all') {
			$classes = array_keys($allclasses);
		}

		// Find the differences between the database and the filesystem.
		foreach ($classes as $class) {
			if (!array_key_exists($class, $allclasses)) {
				continue;
			}

			// Load the Jackalope class.
			$classobj = JackalopeClassName::get(sprintf(
				"Name = '%s'", Convert::raw2sql($class)
			));
			if (!$classobj) {
				$classobj = new JackalopeClassName();
				$classobj->Name = $class;
				$classobj->write();
			}

			$dbs = array_key_exists('db', $allclasses[$class]) ? $allclasses[$class]['db'] : array();
			unset($allclasses[$class]['db']);
			// Sync the database fields.
			$this->syncFields($classobj, $classobj->Fields(), 'JackalopeDBField', array('db' => $dbs));
			$this->syncFields($classobj, $classobj->Relationships(), 'JackalopeRelationship', $allclasses[$class]);
		}
	}

	/**
	 * Diffs the two sets of fields.
	 * Adds and subtracts fields until the sets are equal.
	 *
	 * @param JackalopeClassName $classobj
	 *   The class to create/delete against.
	 * @param ArrayList $fieldsobj
	 *   The list of fields in the database.
	 * @param string $classname
	 *   The name of the class to create for additions.
	 * @param array<string => array<string => string>> $fields
	 *   A list of fields groups found in the code.
	 */
	protected function syncFields($classobj, $fieldsobj, $classname, $fieldgroup) {
		// Any new fields in the code?
		foreach ($fieldgroup as $group => $fields ) {
			foreach ($fields as $codefield => $type) {
				// Do the fields match?
				$match = false;
				foreach ($fieldsobj as $dbfield) {
					if ($dbfield->Name == $codefield) {
						$match = true;
						break;
					}
				}

				if (!$match) {
					// No? Copy it into the database.
					$field = new $classname();
					$field->Type = $group;
					$field->FieldName = $codefield;
					list($field->FieldType, $field->FieldArg) = $this->splitBrackets($type);
					$field->ClassID = $classobj->ID;
					$field->write();
				}
			}
		}

		// Any there any fields in the database not in code?
// @todo check this and finish it.
		foreach ($fieldsobj as $refield) {
			// Do the fields match?
			$match = false;
			foreach ($fieldgroup as $group => $fields ) {
				foreach ($fields as $codefield => $type) {
					if ($refield->Name == $codefield && $group == $refield->Type) {
						$match = true;
						break;
					}
				}
			}

			if (!$match) {
				// No? Delete the old field.
				$field = new $classname();
				$field->Type = $group;
				$field->FieldName = $codefield;
				list($field->FieldType, $field->FieldArg) = $this->splitBrackets($type);
				$field->ClassID = $classobj->ID;
				$field->write();
			}
		}
	}

	/**
	 * Takes a string like "abc(def)" and splits it into it's components.
	 */
	protected function splitBrackets($str) {
		if (preg_match('/^([^\(]+)\(([^\{]+)\)$/', $str, $matches)) {
			return array($matches[1], $matches[2]);
		}
		return array($str, null);
	}

	/**
	 * Displays errors.
	 */
	protected function showError($msg) {
		printf("%s\n", $msg);
	}

	/**
	 * Generates the code for a virtual class.
	 *
	 * @param string $class
	 *   The name of a virtual class to generate code for.
	 *
	 * @return string
	 *   PHP code for the given class.
	 */
	protected function generateCode($class) {
		// Is this a purely virtual class or adding on to an existing one?
		if (property_exists($class, 'JACKALOPEVIRTUALCLASS')) {
			return $this->generateVirtualCode($class);
		}
		else {
			return $this->generateAdditionalCode($class);
		}
	}

	/**
	 * Gets the variables required for a given class.
	 *
	 * @param string $class
	 *   The name of the class to query.
	 */
	protected function getClassVariables($class) {
		// Load the variables.
		$classobj = JackalopeClassName::get_one(
			'JackalopeClassName',
			"Name = '" . Convert::raw2sql($class) . "'"
		);
		$variables = array();

		// Add the $db fields.
		foreach ($classobj->Fields() as $dbfield) {
			if (!array_key_exists('db', $variables)) {
				$variables['db'] = array();
			}
			$variables['db'][] = $dbfield->toCode();
		}

		// Add the relationship fields.
		foreach ($classobj->Relationships() as $field) {
			if (!array_key_exists($field->Type, $variables)) {
				$variables[$field->Type] = array();
			}
			$variables[$field->Type][] = $field->toCode();
		}

		return $variables;
	}

	/**
	 * Generates a virtual class from scratch.
	 */
	protected function generateVirtualCode($class) {
		// Define the class.
		$code = sprintf("class %s extends DataObject {\n", $class);

		// Get the variables associated with the class.
		$variables = $this->getClassVariables($class);

		// Generate the code.
		foreach ($variables as $key => $values) {
			$code .= sprintf("\tpublic static \$%s = array(\n", $key);
			foreach ($values as $line_code) {
				$code .= "\t\t" . $line_code . ",\n";
			}
			$code .= "\t);\n";
		}

		// End of class.
		$code .= "}\n";

		return $code;
	}

	/**
	 * Generates additional code to append an existing class.
	 */
	protected function generateAdditionalCode($class) {
		// Define the class.
		$newclass = 'JackalopeVirtualClass_' . $class;
		$code = sprintf("class %s extends DataExtension {\n", $newclass);

		// Get the variables associated with the class.
		$variables = $this->getClassVariables($class);

		// Generate the code.
		foreach ($variables as $key => $values) {
			$code .= sprintf("\tpublic static \$%s = array(\n", $key);
			foreach ($values as $line_code) {
				$code .= "\t\t" . $line_code . ",\n";
			}
			$code .= "\t);\n";
		}

		// End of class.
		$code .= "}\n";

		// Add the extra line to config.
		$this->configlines[] = sprintf("Object::add_extension('%s', '%s');", addslashes($class), addslashes($newclass));

		return $code;
	}

	/**
	 * Gets the extra lines to go into _config.php.
	 */
	protected $configlines = array();
	protected function getExtraConfig() {
		return implode("\n", $this->configlines);
	}
}
