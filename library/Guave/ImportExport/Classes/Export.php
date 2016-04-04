<?php

namespace Guave\ImportExport\Classes;

use Contao\BackendUser;
use Guave\ImportExport\Models\ExportModel;
use Haste\Haste;

class Export {

    /**
     * @var null|Export $instance
     */
    private static $instance = null;

    /**
     * @var array
     */
    private $fileIds = array();
    private $tables = array();

    /**
     * @var bool
     */
    private $exportFiles = false;
    private $exportRecursive = false;
    private $debug = true;

    protected function __construct() {


        $strPath = \Config::get('uploadPath') . '/export';
        $objFolder = new \Folder($strPath);
        $objFolder->protect();


    }

    /**
     * @return Export|null
     */
    public static function getInstance()
    {
        if(!self::$instance) {
            self::$instance = new Export();
        }
        return self::$instance;
    }

    /**
     * @param string $table
     * @param null|integer|array $id
     */
    public function registerTableForExport($table, $id = null) {

        //allowed
        $user = BackendUser::getInstance();
        if(!$user->hasAccess($table, 'importTables')) {
            \Message::addError(sprintf('no access to table %s', $table));
            return;
        }


        $this->loadDataContainer($table);

        $ids = null;

        if($id) {
            if(is_numeric($id)) {
                $ids[] = $id;
            } else if(is_array($id)) {
                $ids = $id;
            }
        }
        $ids = array_unique($ids);


        //add table for export
        $this->addTablesForExport($table, $ids);


        //check childs
        if($this->exportRecursive) {
            $childs = $GLOBALS['TL_DCA'][$table]['config']['ctable'];
            if ($childs) {
                $this->debug(sprintf('%s - ids: %s', $table, implode(",",$ids)));
                $this->debug(sprintf('childs: %s', implode(",",$childs)));
                foreach ($childs as $childTable) {
                    if($ids == null) { //register hole table
                        $this->registerTableForExport($childTable, null);
                    } else {
                        $pTable = '';
                        if($childTable == 'tl_content') {
                            $pTable = $table;
                        }
                        $childData = ExportModel::findIdsFromTableByPid($childTable, $ids, $pTable);
                        if ($childData) {
                            $childIds = array();
                            foreach ($childData as $child) {
                                $childIds[] = $child['id'];
                            }
                            if ($childIds) {
                                $this->debug(sprintf('childIds: %s', implode(",",$childIds)));
                                $this->registerTableForExport($childTable, $childIds);
                            }
                        }
                    }
                }
            }
        }

        if ($table == 'tl_content' && $this->exportRecursive) { //export modules and forms

            $data = ExportModel::findAllFromTable($table, $ids);
            $formIds = array();
            $moduleIds = array();

            foreach ($data as $d) {
                if ($d['type'] == 'form') {
                    if ($d['form']) {
                        $formIds[] = $d['form'];
                    }
                }
                if ($d['type'] == 'module') {
                    if ($d['module']) {
                        $moduleIds[] = $d['module'];
                    }
                }
            }

            if (!empty($formIds)) {
                $this->registerTableForExport('tl_form', $formIds);
            }
            if (!empty($moduleIds)) {
                $this->registerTableForExport('tl_module', $moduleIds);
            }

        }

    }

    /**
     * @param string $table
     * @param array|null $ids
     */
    private function addTablesForExport($table, $ids) {

        if(!key_exists($table, $this->tables)) {
            $this->tables[$table] = array();
        }
        if($ids) {
            foreach ($ids as $id) {
                if(!in_array($id, $this->tables[$table])) {
                    $this->tables[$table][] = $id;
                }
            }
        }

    }

    public function runExport()
    {
        $this->deleteAllFiles();
        $this->exportTables();
        $this->exportFiles();

    }

    public function exportTables()
    {
        if(!empty($this->tables)) {
            foreach ($this->tables as $table => $ids) {
                $data = ExportModel::findAllFromTable($table, $ids);
                $this->debug(sprintf('table %s ids: %s', $table, implode(",",$ids)));
                if($data) {
                    $this->exportDataToCsv($table, $data);


                    //check for files
                    if ($this->exportFiles) {
                        $this->loadDataContainer($table);
                        if($GLOBALS['TL_DCA'][$table]['fields']) {
                            $fileFields = array();
                            foreach ($GLOBALS['TL_DCA'][$table]['fields'] as $field => $fieldArray) {
                                if ($fieldArray['inputType'] == 'fileTree') {
                                    $fileFields[] = $field;
                                }
                            }
                            if (!empty($fileFields)) {
                                foreach ($data as $d) {
                                    foreach ($fileFields as $field) {
                                        $files = deserialize($d[$field]);
                                        if ($files) {
                                            if (!is_array($files)) {
                                                $files = array($files);
                                            }

                                            foreach ($files as $file) {
                                                if(!in_array($file, $this->fileIds)) {
                                                    $this->fileIds[] = $file;
                                                }
                                            }

                                        }
                                    }
                                }
                            }
                        }
                    }

                }

            }
        }
    }

    public function exportFiles() {

        if(empty($this->fileIds)) {
            return;
        }


        $filesArray = array();
        if ($this->fileIds) {
            $objFiles = \FilesModel::findMultipleByUuids($this->fileIds);

            if ($objFiles) {
                foreach ($objFiles as $file) {
                    if ($this->exportFile($file)) {
                        $filesArray[] = $file->row();
                    }
                }
            }
        }
        if (!empty($filesArray)) {
            $this->exportDataToCsv('tl_files', $filesArray);

        }
    }

    /**
     * @param string $table
     * @param array $data
     */
    protected function exportDataToCsv($table, $data) {

        if(!$data || !table) {
            return;
        }

        \Message::addConfirmation(sprintf('export %s with %s rows', $table, count($data)));

        $rootDir = $this->getFilesRootDir();

        $createHeader = true;
        $file = $rootDir.'/export/'.$table.'.csv';
        if(!is_file($file)) {
            $createHeader = true;
        }

        $handle = fopen($file, 'a+');

        if($createHeader) {
            $fields = array();
            foreach($data as $d) {
                $fields = array_keys($d);
                break;
            }
            fputcsv($handle, $fields, ';', '"');
        }

        foreach($data as $d) {
            $fields = array_values($d);
            fputcsv($handle, $fields, ';', '"');
        }

        fclose($handle);

    }

    public function deleteAllFiles()
    {

        $rootDir = $this->getFilesRootDir();
        $dir = $rootDir.'/export';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /**
         * @var $fileinfo \SplFileInfo
         */
        foreach ($files as $fileinfo) {
            if($fileinfo->getFilename() == '.htaccess') {
                continue;
            }
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        @unlink($rootDir.'/export.zip');


    }

    public function createZip() {
        $rootDir = $this->getFilesRootDir();
        $filename = \Config::get('uploadPath').'/export.zip';
        $zip = new \ZipWriter($filename);

        $Directory = new \RecursiveDirectoryIterator($rootDir.'/export');
        $Iterator = new    \RecursiveIteratorIterator($Directory);
        /**
         * @var $file \SplFileInfo
         */
        foreach($Iterator as $file) {
            if($file->isFile()) {
                $path = substr(str_replace(TL_ROOT,'',$file->getRealPath()), 1);
                $path = str_replace('\\', '/', $path);
                $zip->addFile($path, str_replace(\Config::get('uploadPath'), '', $path));
            }
        }

        $zip->close();

        return $filename;


    }

    public function getFilesRootDir()
    {
        return str_replace('\\', '/', TL_ROOT).'/'.\Config::get('uploadPath');

    }

    protected function exportFile($file) {

        $rootDir = $this->getFilesRootDir();

//        if(!is_dir($rootDir.'/export/files')) {
//            @mkdir($rootDir.'/export/files', 0777);
//            @chmod($rootDir.'/export/files', 0777);
//        }


        $to = str_replace(\Config::get('uploadPath'), \Config::get('uploadPath').'/export/files',TL_ROOT.'/'.$file->path);
        $pathInfo = pathinfo($to);
        $toDir = str_replace(TL_ROOT.'/', '', $pathInfo['dirname']);
        $folder = new \Folder($toDir);

        try {
            copy(TL_ROOT . '/' . $file->path, $to);
        } catch (Exception $e) {
            return false;
        }

        return true;

    }

    /**
     * @return boolean
     */
    public function isExportFiles()
    {
        return $this->exportFiles;
    }

    /**
     * @param boolean $exportFiles
     */
    public function setExportFiles($exportFiles)
    {
        $this->exportFiles = $exportFiles;
    }

    /**
     * @return boolean
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * @param boolean $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @return boolean
     */
    public function isExportRecursive()
    {
        return $this->exportRecursive;
    }

    /**
     * @param boolean $exportRecursive
     */
    public function setExportRecursive($exportRecursive)
    {
        $this->exportRecursive = $exportRecursive;
    }

    public function debug($msg)
    {
        if($this->debug) {
            \Message::addInfo($msg);
        }
    }

    /**
     * Load a set of DCA files
     *
     * @param string  $strTable   The table name
     * @param boolean $blnNoCache If true, the cache will be bypassed
     */
    public static function loadDataContainer($strTable, $blnNoCache=false)
    {
        Haste::getInstance()->call('loadDataContainer', array($strTable, $blnNoCache));
    }


}