<?php
/**
 * Initialises the Jackalope manifest when the first controller is loaded.
 */
class JackalopeController extends RequestProcessor {
	public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model) {
		parent::preRequest($request, $session, $model);

		// Prepare variables.
		global $manifest, $loader, $flush;

		// Use a new Manifest class.
		$manifest = new JackalopeManifest(BASE_PATH, FALSE, $flush);
	}

	/**
	 * Gets the database to rebuild itself.
	 *
	 * @param String $class
	 *   The name of the class being rebuilt.
	 */
	public static function rebuild($class) {
		// Do a generic rebuild for everything.
		DataObject::reset();
		SS_ClassLoader::instance()->getManifest()->regenerate();
		singleton('DatabaseAdmin')->doBuild(TRUE, FALSE, FALSE);

		// Specifically ensure that the new table exists.
		if ($class) {
			$sng = singleton($class);
			$sng->requireTable();
		}
	}

	/**
	 * Takes a delete event and removes the field from the database.
	 *
	 * @param int $classid
	 *   The ID of the JackalopeClassName record.
	 * @param string $fieldname
	 *   The name of the field that was deleted.
	 * @param boolean $quick
	 *   (Optional) If true this skips the rebuild step.
	 */
	public static function deleteField($classid, $fieldname, $quick = false) {
		// Which table are we addressing?
		$class = JackalopeClassName::get_by_id('JackalopeClassName', $classid);
		if (!$class) {
			// The class was deleted first? Don't know which table to reference then.
			return FALSE;
		}
		$table = $class->Name;

		// Rebuild the database to ensure the field is no longer referenced in classes.
		self::rebuild($table);

		// Remove the field from the live model set.
		$refclass = new ReflectionClass($table);
		$fields = array();
		foreach ($refclass->getProperties(ReflectionProperty::IS_PUBLIC) as $field) {
			$fields[] = $field->name;
		}
		if (in_array($fieldname, $fields)) {
			unset($table::$db[$fieldname]);
		}

		// Silverstripe doesn't currently have any 'DROP Field' commands, so lets add one.
		$sql = sprintf(
			'ALTER TABLE "%s" DROP COLUMN %s',
			Convert::raw2sql($table), Convert::raw2sql($fieldname)
		);
		return DB::query($sql);
	}

	/**
	 * Takes a delete event and removes the table associated with the given class.
	 * ...unless this is defined outside of the Jackalope module.
	 *
	 * @param JackalopeClassName $class
	 *   The class being deleted
	 */
	public static function deleteTable($class) {
		// Prepare variables.
		$table = $class->Name;

		// Ensure the table isn't core.
		if (!property_exists($table, 'JACKALOPEVIRTUALCLASS')) {
			// This is a core class or from another module? Leave it.
			return FALSE;
		}

		// Ensure the table already exists.
		if (!DB::getConn()->hasTable($table)) {
			// The table never existed? So it's gone now.
			return TRUE;
		}

		// Silverstripe doesn't currently have any 'DROP TABLE' commands, so lets add one.
		$sql = sprintf(
			'DROP TABLE "%s"',
			Convert::raw2sql($table)
		);
		return DB::query($sql);
	}
}
