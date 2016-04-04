<?php

$GLOBALS['TL_DCA']['tl_user_group']['palettes']['default'] .= ';{import_export_legend},importTables;';

$GLOBALS['TL_DCA']['tl_user_group']['fields']['importTables'] = array(
    'label'                   => array('allowed import tables'),
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'options_callback'        => array('Guave\ImportExport\Helper\Helper', 'getDbTables'),
    'eval'                    => array('multiple'=>true, 'size'=>36, 'tl_class' => 'w50 clr'),
    'sql'                     => "blob NULL"
);

