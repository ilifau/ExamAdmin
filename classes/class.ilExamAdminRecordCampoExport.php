<?php

/**
 * Class ilExamAdminRecordCampoExport
 */
class ilExamAdminRecordCampoExport
{
    private $ref_id="";

    /**
     * Export to excel file
     * @param $ref_id
     */
    public function exportToExcel(string $ref_id) {
        $this->ref_id = $ref_id;
    }

    public function getExportString()
    {
        return "kgksökgjsö";
    }

    public function getExportFileName()
    {
        return "tst_".$this->ref_id.".zip";
    }

}

class ExamAdminCampoExportException extends Exception{}