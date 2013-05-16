<?php
/**
 * This model stores the fields associated with the virtual class.
 */
class JackalopeDBField extends DataObject {
        static $db = array(
                'FieldName' => 'Text',
		// @todo Add validation for this type, ensure it exists.
		'FieldType' => 'Text',
        );
	static $has_one = array(
		'Class' => 'JackalopeClassName',
	);
}

