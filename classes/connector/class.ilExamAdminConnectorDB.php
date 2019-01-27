<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


/**
* MySQL Wrapper for a connection to an external ILIAS database
*
* This class extends the main ILIAS database wrapper ilDB.
*/
class ilExamAdminConnectorDB extends ilDBPdoMySQLInnoDB
{

	/** @var  ilExamAdminConnectorDB $instance */
	private static $instance;


	/**
	 * Get the database connection instance
	 * @return ilExamAdminConnectorDB
     * @throws Exception
	 */
	public static function getInstance()
	{
	    global $DIC;

        // read the client settings if available
        if (isset($DIC) && $DIC->offsetExists('ilClientIniFile'))
        {
            /** @var ilIniFile $ilClientIniFile */
            $ilClientIniFile = $DIC['ilClientIniFile'];
            $settings = $ilClientIniFile->readGroup("exam_admin_db");
        }

        if (!isset(self::$instance))
        {
            $instance = new self;
            $instance->setDBHost($settings['host']);
            $instance->setDBPort($settings['port']);
            $instance->setDBUser($settings['user']);
            $instance->setDBPassword($settings['pass']);
            $instance->setDBName($settings['name']);
            if (!$instance->connect(true))
            {
                return null;
            }
            self::$instance = $instance;
        }

        return self::$instance;
	}

//	/**
//	 * Connect
//	 * set the parameter 'new_link' (allowed by patch in PEAR:MDB2)
//	 * don't set the parameter 'use transactions'
//     *
//	 */
//	function doConnect()
//	{
//		$this->db = MDB2::factory($this->getDSN(), array("new_link" => true));
//	}
}
