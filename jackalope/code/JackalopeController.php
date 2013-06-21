<?php
/**
 * Initialises the Jackalope manifest when the first controller is loaded.
 */
class JackalopeController extends RequestProcessor {
	public function preRequest() {
		parent::preRequest();

		// Prepare variables.
		global $manifest, $loader, $flush;

		// Use a new Manifest class.
		$manifest = new JackalopeManifest(BASE_PATH, false, $flush);
	}

	/**
	 * Gets the database to rebuild itself.
	 */
	public static function rebuild() {
		DataObject::reset();
		SS_ClassLoader::instance()->getManifest()->regenerate();
		singleton('DatabaseAdmin')->doBuild(TRUE, FALSE, FALSE);
	}
}
