<?php

class ilExamAdminArConnectorDB2 extends arConnectorDB
{
    /**
     * @return ilDBInterface
     */
    protected function returnDB()
    {
        return ilExamAdminConnectorDB2::getInstance();
    }

    /**
     * Register the connector for remote active records
     */
    public static function register()
    {
        arConnectorMap::register(new ilExamAdminOrgaRecord(), new self());
        arConnectorMap::register(new ilExamAdminOrgaLink(), new self());
        arConnectorMap::register(new ilExamAdminOrgaCampusExam(), new self());
    }
}