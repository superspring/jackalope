<?php
/**
 * This model stores the new name of the class.
 */
class JackalopeClassName extends DataObject {
	static $db = array(
		'Name' => 'Text',
	);
	static $has_many = array(
		'Fields'        => 'JackalopeDBField',
		'Relationships' => 'JackalopeRelationship',
	);
}
