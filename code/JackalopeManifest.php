<?php
/**
 * Adds classes stored in the database to the manifest.
 */
class JackalopeManifest extends SS_ClassManifest {
	const DATABSECLASSMODEL_PATH = '../jackalope/code/models/JackalopeDummyClass.php';

	const GENERICCLASS_CODE = <<<'EOF'
class %s extends DataObject {
	// This is used to distinguish between real classes and virtual classes.
	public $JACKALOPEVIRTUALCLASS = true;

	// Define all the variables so they can later be modified.
	public static $db = array();
	public static $many_many = array();
	public static $belongs_many_many = array();
	public static $searchable_fields = array();
	public static $summary_fields = array();
	public static $has_one = array();
	public static $belongs_to = array();
	public static $defaults = array();
	public static $has_many = array();
}
EOF;

	const APPENDCLASS_CODE = <<<'EOF'
class %s extends DataExtension {
	// This is used to distinguish between real classes and virtual classes.
	public $JACKALOPEVIRTUALCLASS = true;

	// Define all the variables so they can later be modified.
	public static $db = array();
	public static $many_many = array();
	public static $belongs_many_many = array();
	public static $searchable_fields = array();
	public static $summary_fields = array();
	public static $has_one = array();
	public static $belongs_to = array();
	public static $defaults = array();
	public static $has_many = array();

	/**
	 * This gets all the static variables and returns them.
	 */
	public static function get_extra_config($class, $extension, $args) {
		return array(
			'db'                => self::$db,
			'many_many'         => self::$many_many,
			'belongs_many_many' => self::$belongs_many_many,
			'searchable_fields' => self::$searchable_fields,
			'summary_fields'    => self::$summary_fields,
			'has_one'           => self::$has_one,
			'belongs_to'        => self::$belongs_to,
			'defaults'          => self::$defaults,
			'has_many'          => self::$has_many,
		);
	}
}
EOF;

	/**
	 * Ensures this class replaces the old manifest.
	 */
	public function __construct($base, $includeTests = false, $forceRegen = false, $cache = true) {
		// Ensure this class is the manifest class.
		$loader = SS_ClassLoader::instance();

		$loader->popManifest();
		$loader->pushManifest($this);

		parent::__construct($base, $includeTests, $forceRegen, $cache);
		$this->regenerate();
	}

	/**
	 * Ensure when regenerated that these classes are added again.
	 */
	public function regenerate($cache = true) {
		parent::regenerate($cache);

		// Add the virtual classes to the manifest.
		foreach ($this->createClasses() as $class) {
			// Prepare name.
			$classlower = strtolower($class);

			// Add to internal set.
			if (!array_key_exists($classlower, $this->classes)) {
				$this->classes[$classlower] = self::DATABSECLASSMODEL_PATH;
				$this->children['dataobject'][] = $class;
				$this->descendants['dataobject'][] = $class;
			}
		}

		// Store the cache again.
		if ($cache) {
			$data = array(
				'classes'      => $this->classes,
				'descendants'  => $this->descendants,
				'interfaces'   => $this->interfaces,
				'implementors' => $this->implementors,
				'configs'      => $this->configs,
				'configDirs'   => $this->configDirs,
			);
			$this->cache->save($data, $this->cacheKey);
		}
	}


	/**
	 * Get a list of models to add to the set.
	 */
	protected function getDatabaseClasses() {
		// Get a list of the virtual classes.
		$classes = JackalopeClassName::get('JackalopeClassName');
		$results = array();

		// Ensure the table exists before attempting to run anything.
		$exists = DB::getConn()->hasTable('JackalopeClassName');
		if (!$exists) {
			// No data to play with?
			return $results;
		}

		// Ensure teh table has all it's columns before starting.
		foreach (JackalopeClassName::$db as $field => $type) {
			if (!DB::getConn()->hasField('JackalopeClassName', $field)) {
				// We're missing a field? Do nothing then.
				return $results;
			}
		}

		if ($classes->Count() > 0) {
			foreach ($classes as $class) {
				// Prepare variables.
				$results[$class->Name] = array(
					'db' => array(),
				);

				// Add all the fields.
				foreach ($class->Fields() as $field) {
					$value = $field->FieldArgs ? $field->FieldType . '(' . $field->FieldArgs . ')' : $field->FieldType;
					$results[$class->Name]['db'][$field->FieldName] = $value;
				}

				// Add all the relationships.
				foreach ($class->Relationships() as $relationship) {
					if (!array_key_exists($relationship->Type, $results[$class->Name])) {
						$results[$class->Name][$relationship->Type] = array();
					}
					$results[$class->Name][$relationship->Type][$relationship->FieldName] = $relationship->FieldValue;
				}
			}
		}

		return $results;
	}

	/**
	 * Creates classes and adds static information to them.
	 */
	protected function createClasses() {
		// Get the virtual classes.
		$classes = $this->getDatabaseClasses();

		// For each new class, create it and add the datas.
		foreach ($classes as $class => $definition) {
			// Change all the fields in the class or create it.
			$this->createOrAppendClass($class, $definition);
		}

		return array_keys($classes);
	}

	/**
	 * This creates a class to add fields to extent (potentially) another class.
	 *
	 * Either a new class will be made or the fields will be added to the existing class.
	 *
	 * @param String $class
	 *   The base name of the class to change, eg 'Member'.
	 * @param array $definition
	 *   The fields to add in the form array('db' => array('fieldname' => 'fieldtype', [...]), [...])
	 */
	protected function createOrAppendClass($class, $definition) {
		$newclass = 'JackalopeVirtualClass_' . $class;
		if (substr($class, 0, 21) == 'JackalopeVirtualClass') {
			// Don't need to create another subclass.
			$newclass = $class;
		}
		// Class doesn't exist? Create it.
		if (!class_exists($class)) {
			// Define the class.
			$createcode = sprintf(self::GENERICCLASS_CODE, $class);
			// @todo Find another way of achieving this.
			eval($createcode);

			// Add fields.
			$this->addFieldsToClass($class, $class, $definition);
		}
		// If the class already exists and has been appended to.
		elseif (class_exists($newclass)) {
			// Sync fields.
			$this->addFieldsToClass($newclass, $class, $definition);
		}
		// If this class has already 
		elseif (property_exists($class, 'JACKALOPEVIRTUALCLASS')) {
			// Don't need to create another subclass.
			$this->addFieldsToClass($class, $class, $definition);
		}
		// If the class already exists, create another class to append to it.
		else {
			// Define the class.
			$createcode = sprintf(self::APPENDCLASS_CODE, $newclass);
			// Create it.
			eval($createcode);

			// Sync fields.
			$this->addFieldsToClass($newclass, $class, $definition);

			// Add the extension.
			Object::add_extension($class, $newclass);
		}
	}

	/**
	 * Syncs the expected variables with actual variables on a class.
	 *
	 * Takes a given field definition and ensures the static variables in the given class contain this.
	 * Also ensures that the configuration is up-to-date with the (potentially new) definition.
	 *
	 * @param string $class
	 *   The actual name of the class to sync.
	 * @param string $cacheclass
	 *   The name of the table/actual class to update in the cache.
	 * @param array $definition
	 *   A key-paired array of variable name => value for the keys.
	 */
	protected function addFieldsToClass($class, $cacheclass, $definition) {
		// Prepare variables.
		$classref = new ReflectionClass($class);
		$allfields = $classref->getStaticProperties();

		// Add data to the class.
		foreach ($definition as $var_name => $var_value) {
			$fields = (!array_key_exists($var_name, $allfields) || !is_array($allfields[$var_name]))
				  ? array() : $allfields[$var_name];
			$class::$$var_name = $this->mergeField($fields, $var_value);

			// Ensure the data is stored in the configuration.
			Config::inst()->update($cacheclass, $var_name, $class::$$var_name);
		}
	}

	/**
	 * Adds new fields to the existing objects.
	 *
	 * @param array $original
	 *   What is currently in the class.
	 * @param array $additions
	 *   New fields to add to the class.
	 *
	 * @return
	 *   The union of these two sets with the bias to the additions.
	 */
	protected function mergeField($original, $additions) {
		return array_unique(array_merge($original, $additions), SORT_STRING);
	}
}
