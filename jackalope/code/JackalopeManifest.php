<?php
/**
 * Adds classes stored in the database to the manifest.
 */
class JackalopeManifest extends SS_ClassManifest {
	const DATABSECLASSMODEL_PATH = '../jackalope/code/models/JackalopeDummyClass.php';
	// @todo This is missing many fields, add them.
	const GENERICCLASS_CODE = <<<'EOF'
class %s extends DataObject {
	static $db = array();
	static $has_one = array();
	static $has_many = array();
	static $allowed_children = array();
	static $belongs_many_many = array();
}
EOF;

	/**
	 * Ensures this class replaces the old manifest.
	 */
	public function __construct($base, $includeTests = false, $forceRegen = false, $cache = true) {
		// Ensure this class is the manifest class.
		global $loader;
		$loader->popManifest();
		$loader->pushManifest($this);

		parent::__construct($base, $includeTests, $forceRegen, $cache);
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
			$this->classes[$classlower] = self::DATABSECLASSMODEL_PATH;
			$this->children['dataobject'][] = $class;
			$this->descendants['dataobject'][] = $class;
		}

		// Store the cache again.
		if ($cache) {
			$data = array(
				'classes'      => $this->classes,
				'descendants'  => $this->descendants,
				'interfaces'   => $this->interfaces,
				'implementors' => $this->implementors,
				'configs'      => $this->configs
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

		if ($classes->Count() > 0) {
			foreach ($classes as $class) {
				// Prepare variables.
				$results[$class->Name] = array(
					'db' => array(),
				);

				// Add all the fields.
				foreach ($class->Fields() as $field) {
					$results[$class->Name]['db'][$field->FieldName] = $field->FieldType;
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

		// Ensure this isn't run multiple times.
		static $createdclasses;
		if (!isset($createdclasses)) {
			$createdclasses = array();
		}

		// For each new class, create it.
		foreach ($classes as $class => $definition) {
			if (!array_key_exists($class, $createdclasses)) {
				// Define the class.
				$createcode = sprintf(self::GENERICCLASS_CODE, $class);
				// @todo Find another way of achieving this.
				eval($createcode);

				// Add data to the class.
				foreach ($definition as $var_name => $var_value) {
					$class::$$var_name = $var_value;
				}
				// Remember it's been created.
				$createdclasses[$class] = true;
			}
		}

		return array_keys($classes);
	}
}
