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
}
