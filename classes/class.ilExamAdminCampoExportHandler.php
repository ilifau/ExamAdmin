<?php

/**
 * Remote access handler for Campo Export 
 *
 * @author Christina Fuchs <christina.fuchs@ili.fau.de>
 */
class ilExamAdminCampoExportHandler{
    /** @var string */
    private $token;
    /** @var string */
    private $ref_id;  

    /**
     * Handle remote calendar request
     * @return
     */
    public function handleRequest()
    {    
        global $DIC;

        $this->ref_id = $_GET["ref_id"];

        require_once(__DIR__ . '/class.ilExamAdminRecordCampoExport.php');
        $export = new ilExamAdminRecordCampoExport();
        $export->exportToExcel($this->ref_id);

        ilUtil::deliverData($export->getExportString(), $export->getExportFileName(), 'application/zip', 'utf-8');
        exit;        
    }
}
