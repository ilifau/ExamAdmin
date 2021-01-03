<?php

class ilExamAdminArConnectorDB extends arConnectorDB
{
    /**
     * @return ilDBInterface
     */
    protected function returnDB()
    {
        require_once(__DIR__ . '/class.ilExamAdminConnectorDB.php');
        return ilExamAdminConnectorDB::getInstance();
    }

    /**
     * Register the connector for remote active records
     */
    public static function register()
    {
        require_once(__DIR__ . '/../orga/class.ilExamAdminOrgaRecord.php');
        arConnectorMap::register(new ilExamAdminOrgaRecord(), new self());
    }
}