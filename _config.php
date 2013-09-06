<?php
/**
 * Attempts to modify the manifest to add in additional classes.
 */

// Placing the code here allows it to be run first(ish) after the database is initialised.
Config::inst()->update('Injector', 'RequestProcessor', 'JackalopeController');

Object::add_extension('More', 'JackalopeExporter');
Object::add_extension('More', 'JackalopeImporter');
