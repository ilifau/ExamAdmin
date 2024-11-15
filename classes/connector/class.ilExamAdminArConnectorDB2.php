<?php

class ilExamAdminArConnectorDB2 extends arConnectorDB
{
    /**
     * @return ilDBInterface
     */
    protected function returnDB(): ilDBInterface
    {
        return ilExamAdminConnectorDB2::getInstance();
    }

    /**
     * Register the connector for remote active records
     */
    public static function register()
    {
    }
}