<?php
/**
 * @file
 * This test modifies internal Silverstripe models to ensure rebuilds work.
 */
class JackalopeInternalModel extends SapphireTest {
	protected static $internalClasses = array(
		'Member' => array(
			'testField1' => array(
				'Type' => 'Text',
				'Arg'  => NULL,
			),
		),
		'Group' => array(
			'testField2' => array(
				'Type' => 'Varchar',
				'Arg'  => '56',
			),
		),
		'SiteTree' => array(
			'testField3' => array(
				'Type' => 'Enum',
				'Arg'  => '"ab,cd,ef,11", "ef"',
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
		$_SERVER['HTTP_HOST'] = 'http://localhost/dev/build';
		$this->helperUseManifest();

		// Add all the classes to the database.
		foreach (self::$internalClasses as $class => $fields) {
			$model = new JackalopeClassName();
			$model->Name = $class;
			$model->write();

			foreach ($fields as $name => $type) {
				// Create the new field.
				$field = new JackalopeDBField();
				$field->FieldName = $name;
				$field->Type = $type['Type'];
				$field->Args = $type['Arg'];

				// Link it to the new model.
				$field->ClassID = $model->ID;
				$field->write();
			}
		}

		// Ensure the fields exist inside the database.
		foreach (self::$internalClasses as $class => $fields) {
			foreach ($fields as $name => $type) {
				$sql = sprintf(
					'SELECT %s FROM "%s" LIMIT 1',
					Convert::raw2sql($name), Convert::raw2sql($class)
				);
				DB::query($sql);
			}
		}
	}
}
