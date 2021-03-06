<?php
/**
 * Provides a functionality to export virtual classes to code.
 */
class JackalopeExporter extends DataExtension {
	const CMD_EXPORT = 'export';
	const COMMENTBLOCK = <<<EOF
<?php
/**
 * This code has been automatically generated by the Jackalope module.
 */

EOF;

	/**
	 * Tell sake more about this command.
	 */
	public function commands(&$list) {
		$list[self::CMD_EXPORT] = array($this, 'prepareExport', true);
	}

	/**
	 * Gives sake a brief for the help section.
	 */
	public function help_brief(&$details) {
		$details[self::CMD_EXPORT] = 'Provides the ability to export virtual classes to code.';
	}

	/**
	 * Gives sake a list of the parameters used.
	 */
	public function help_parameters(&$details) {
		$details[self::CMD_EXPORT] = array(
			'The first argument must be the module to export the code to',
			'--stdout - If this is the first argument code will go to stdout instead of a module',
			'Next argument/s may be the virtual class names to export',
			'--all - All virtual classes in the database',
		);
	}

	/**
	 * Gives sake a list of examples of how to use this command.
	 */
	public function help_examples(&$examples) {
		$examples[self::CMD_EXPORT] = array(
			self::CMD_EXPORT . ' --stdout --all - Outputs all virtual classes to stdout',
			self::CMD_EXPORT . ' mysite testClass1 testClass2 - Outputs the testClasses into the mysite module',
		);
	}

	/**
	 * Reads the commands to determine how to output the classes'.
	 */
	public function prepareExport($args) {
		// Is this going into a module or stdout?
		if (empty($args)) {
			return $this->showError('Expected either --stdout or a module for the first argument');
		}
		$module = array_shift($args);
		$display_output = $module == '--stdout';

		// Which classes to export?
		$classes = $args;
		if (reset($classes) == '--all') {
			$classes = JackalopeClassName::get('JackalopeClassName')->map('Name');
		}

		$output = array();
		foreach ($classes as $class) {
			// Generate a file for each class.
			$code = $this->generateCode($class);

			// Put the output somewhere.
			if ($display_output) {
				printf("%s\n", $code);
			}
			else {
				$output[$class] = $code;
			}
		}

		// Extra lines to go into the _config.php file?
		$configcode = $this->getExtraConfig();
		if ($display_output) {
			printf("%s\n", $configcode);
		}
		else {
			// Sake is run inside the framework directory, so go one up.
			$prefix = '..';

			// Create the module directory.
			$module = strtolower($module);
			$paths = array(
				sprintf('%s/%s', $prefix, $module),
				sprintf('%s/%s/code', $prefix, $module),
				sprintf('%s/%s/code/jackalope', $prefix, $module),
			);
			foreach($paths as $path) {
				// Does this path already exist?
				if (!file_exists($path)) {
					$success = @mkdir($path);
					if (!$success) {
						printf("Unable to write directory: %s\n", $path);
						return FALSE;
					}
				}
			}
			
			// Take all the classes and render them in their files.
			foreach ($output as $class => $code) {
				// A class name is already PHP escaped/safe.
				$path = sprintf('%s/%s/code/jackalope/%s.php', $prefix, $module, $class);
				$success = @file_put_contents($path, self::COMMENTBLOCK . $code);
				if ($success === FALSE) {
					printf("Unable to write file: %s\n", $path);
					return FALSE;
				}
			}

			// Write the configuration.
			$path = sprintf('%s/%s/_config.php', $prefix, $module);
			$success = @file_put_contents($path, self::COMMENTBLOCK . $configcode);
			if ($success === FALSE) {
				printf("Unable to write file: %s\n", $path);
				return FALSE;
			}
		}
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
		$classobj = JackalopeClassName::get()->filter(array(
			'Name' => $class,
		))->first();
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
		$code = sprintf(
			"class %s extends DataExtension {\n" .
			"\tpublic \$JACKALOPEVIRTUALCLASS = true;\n",
			$newclass
		);

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
