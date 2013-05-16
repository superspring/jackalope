<?php
/**
 * This model stores the fields associated with the virtual class.
 */
class JackalopeRelationship extends DataObject {
        static $db = array(
		'Type' => 'Text',
                'FieldName' => 'Text',
                'FieldType' => 'Text',
        );
        static $has_one = array(
                'Class' => 'JackalopeClassName',
        );
}
