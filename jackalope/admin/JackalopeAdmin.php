<?php
/**
 * Provides a CMS for modifying the virtual dataobjects.
 */
class JackalopeAdmin extends ModelAdmin {
	public static $managed_models = array(
		'JackalopeClassName',
	);
	static $url_segment = 'jackalope';
	static $menu_title = 'Jackalope';

	// There is no point in an upload form for class structures.
	public $showImportForm = false;
}
