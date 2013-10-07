<?php
/**
 * @file
 * This test ensures that fields are not duplicated so cannot delete the wrong one.
 */
class JackalopeDuplicateField extends SapphireTest {
	protected static $duplicateClasses = array(
		'Member' => array(
			'ID' => array(
				'Type' => 'Int',
				'Arg'  => NULL,
			),
		),
		'Group' => array(
			'ParentID' => array(
				'Type' => 'Int',
				'Arg'  => NULL,
			),
		),
	);

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
	public function testCreateModel() {
		// Use the jackalope manifest.
		$this->helperUseManifest();

		// All fields should be queriable.
		$testFields = function() {
			foreach (self::$duplicateClasses as $class => $fields) {
				// @todo Escape the field names.
				$fieldsql = '"' . implode('", "', array_keys($fields)) . '"';
				$sql = sprintf(
					// @todo Make this database generic.
					'SELECT %s FROM `%s` LIMIT 1',
					$fieldsql, Convert::raw2sql($class)
				);
				DB::query($sql);
			}
		};
		// The fields should be queriable.
		$testFields();

		// Add all the classes to the database.
		foreach (self::$duplicateClasses as $class => $fields) {
			$model = new JackalopeClassName();
			$model->Name = $class;
			$model->write();

			foreach ($fields as $name => $type) {
				$threw = false;
				try {
					// Create the new field.
					$field = new JackalopeDBField();
					$field->FieldName = $name;
					$field->Type = $type['Type'];
					$field->Args = $type['Arg'];

					// Link it to the new model.
					$field->ClassID = $model->ID;
					// This will fail validation, ideally.
					$field->write();
				}
				catch (Exception $ex) {
					// This is the expected path.
					$threw = true;
				}
				$this->assertTrue($threw);
			}
		}
		// The fields should still be queryable.
		$testFields();

		// Delete the fields from the database.
		foreach (self::$duplicateClasses as $class => $fields) {
			// Load the class.
			$class = JackalopeClassName::get()->filter(array(
				'Name' => $class,
			))->first();

			// Ensure it exists.
			$classid = $class->ID;

			// Delete it.
			$class->delete();

			// Ensure the fields are deleted with it.
			$count = JackalopeDBField::get()->filter(array(
				'ClassID' => $classid,
			))->count();
			// There should be none left.
			$this->assertEquals($count, 0);
		}

		// The fields should still be queryable.
		$testFields();
	}
}
