<?php
/**
 * @file
 * This test ensures that models are cleaned up after they're deleted.
 */
class JackalopeDeleteModel extends SapphireTest {
	protected static $className = 'DeleteModelTestClass';
	protected static $fieldNames = array(
		// Name => array(Type, Args).
		'TestDBField1' => array('Type' => 'Text',    'Arg' => null),
		'TestDBField2' => array('Type' => 'Varchar', 'Arg' => 255),
		'TestDBField3' => array('Type' => 'Enum',    'Arg' => "'abc,def,hij', 'def'"),
	);

	/**
	 * Helper method to: Ensure the model does/not currently exist.
	 */
	protected function helperTestNoModel($doesexist) {
		// Prepare assertion direction.
		$true  = $doesexist ? 'assertTrue'  : 'assertFalse';
		$false = $doesexist ? 'assertFalse' : 'assertTrue';

		// Ensure the class does not exist.
		$this->$true(class_exists(self::$className, $doesexist));

		// Ensure the table does not exist.
		$tables = DB::tableList();
		$this->$true(in_array(self::$className, $tables));

		// Ensure the virtual table does not exist.
		$classes = JackalopeClassName::get('JackalopeClassName', sprintf(
			"Name = '%s'", Convert::raw2sql(self::$className)
		));
		$this->$false($classes->count() == 0);
	}

	/**
	 * Helper method to: Tell SapphireTest to use Jackalope's manifest.
	 */
	protected function helperUseManifest() {
		// Create a new instance of the manifest.
		global $flush;
		$manifest = new JackalopeManifest(BASE_PATH, false, $flush);

		// Overwrite the test one.
		SS_ClassLoader::instance()->pushManifest($manifest);
		SapphireTest::set_test_class_manifest($manifest);
	}

	/**
	 * Create the model.
	 */
	public function testDeleteModel() {
		// Ensure the database is ready.
		$this->helperTestNoModel(FALSE);
		// Use Jackalope's manifest.
		$this->helperUseManifest();

		// Add the class to the database.
		$class = new JackalopeClassName();
		$class->Name = self::$className;
		$class->write();

		// Add fields to the class.
		foreach (self::$fieldNames as $fieldname => $fieldvals) {

			// Create the field.
			$dbfield = new JackalopeDBField();
			$dbfield->FieldName = $fieldname;
			$dbfield->FieldType = $fieldvals['Type'];
			if ($fieldvals['Arg']) {
				$dbfield->FieldArgs = $fieldvals['Arg'];
			}
			$dbfield->ClassID = $class->ID;
			$dbfield->write();
		}

		// Ensure only the corret fields were added.
		$className = self::$className;
var_dump($className, $className::$db);
		foreach ($className::$db as $name => $type) {
			$this->assertTrue(array_key_exists($name, self::$fieldNames));
		}
		$this->assertTrue(count($className::$db) === count(self::$fieldNames));

		// Initialise the class.
		$this->assertTrue(class_exists(self::$className));
		$testobj = new self::$className();

		// Check it worked properly.
		$this->helperTestNoModel(TRUE);

		// Delete the model.
		$class->delete();

		// This SQL should fail.
		$sql = sprintf(
			'SELECT 1 FROM "%s"',
			Convert::raw2sql(self::$className)
		);
		$result = DB::query($sql);
	}
}
