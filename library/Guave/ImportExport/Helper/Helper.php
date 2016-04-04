<?php

namespace Guave\ImportExport\Helper;

use Contao\Input;
use Guave\ImportExport\Classes\Export;

class Helper
{

    private static $instance = null;

    protected function __construct() {

    }

    public static function getInstance()
    {
        if(!self::$instance) {
            self::$instance = new Helper();
        }
        return self::$instance;
    }

    public function registerExportButton($table)
    {

//        echo $table.'<br />';
        //is dc_table
        if ($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] != 'Table') {
            return;
        }


        //check permission
        $objUser  = \BackendUser::getInstance();
        if(!$objUser->hasAccess($table, 'importTables')) {
            return;
        }



        $GLOBALS['TL_DCA'][$table]['list']['global_operations']['export'] = array(
            'label'               => array('Exportieren'),
            'href'                => 'registerTableForExport='.$table.'&act=select',
            'icon'                => 'system/modules/import_export/assets/images/export-icon.png'
        );

        //remove other buttons
        if(\Input::get('act') == 'select' && \Input::get('registerTableForExport')) {
            $GLOBALS['TL_DCA'][$table]['config']['notDeletable'] = true;
            $GLOBALS['TL_DCA'][$table]['config']['notSortable'] = true;
            $GLOBALS['TL_DCA'][$table]['config']['notCopyable'] = true;
            $GLOBALS['TL_DCA'][$table]['config']['notEditable'] = true;

            $GLOBALS['TL_DCA'][$table]['select']['buttons_callback'] = array(
                array('\Guave\ImportExport\Helper\Helper', 'addExportButtons')
            );
        }


//        $GLOBALS['TL_DCA'][$table]['config']['onload_callback'][] = array(
//            'Guave\ImportExport\Classes\Export', 'export'
//        );

    }

    public function addExportButtons($arrButtons, $dc)
    {

        if(\Input::post('export') && \Input::get('registerTableForExport')) {


            $ids = \Input::post('IDS');

            if(empty($ids)) {
                \Message::addError('please choose something for export');
            } else {
                $export = Export::getInstance();
                if(\Input::post('export_recursive')) {
                    $export->setExportRecursive(true);
                }
                if(\Input::post('export_files')) {
                    $export->setExportFiles(true);
                }
                $export->registerTableForExport(\Input::get('registerTableForExport'), $ids);
                $export->runExport();
//            $zip = $export->createZip();
                \Controller::redirect(\Controller::getReferer());
//            exit;
            }


        }

        $arrButtons['export_recursive'] = '<input checked="checked" type="checkbox" value="1" name="export_recursive" /> export recursive';
        $arrButtons['export_files'] = '<input checked="checked" type="checkbox" value="1" name="export_files" /> export files';

        $arrButtons['export'] = '<input type="submit" name="export" id="export" class="tl_submit" accesskey="e" value="Exportieren">';
        return $arrButtons;

    }

    public function getZip()
    {
        $filename = \Config::get('uploadPath').'/export.zip';
        \Controller::sendFileToBrowser($filename);

    }

    public function getDbTables()
    {
        $query = 'SHOW TABLES FROM '.\Config::get('dbDatabase');
        $stm = \Database::getInstance()->prepare($query)->execute();
        $tables = $stm->fetchAllAssoc();


        $return = array();
        foreach ($tables as $k => $v) {
            foreach ($v as $k2 => $v2) {
                $return[$v2] = $v2;
            }
        }

        return $return;
    }

}