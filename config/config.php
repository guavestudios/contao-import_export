<?php

//models
$GLOBALS['TL_MODELS']['tl_press'] = 'Guave\ImportExport\Models\ExportModelds';

//hooks
$GLOBALS['TL_HOOKS']['loadDataContainer'][] = array('Guave\ImportExport\Helper\Helper', 'registerExportButton');

//permissions
$GLOBALS['TL_PERMISSIONS'][] = 'importTables';