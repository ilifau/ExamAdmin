<?php

class ilExamAdminArConnectorDB extends arConnectorDB
{
    /**
     * @return ilDBInterface
     */
    protected function returnDB(): ilDBInterface
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
        require_once(__DIR__ . '/../orga/class.ilExamAdminOrgaLink.php');
        require_once(__DIR__ . '/../orga/class.ilExamAdminOrgaCampusExam.php');
        arConnectorMap::register(new ilExamAdminOrgaRecord(), new self());
        arConnectorMap::register(new ilExamAdminOrgaLink(), new self());
        arConnectorMap::register(new ilExamAdminOrgaCampusExam(), new self());
    }
}