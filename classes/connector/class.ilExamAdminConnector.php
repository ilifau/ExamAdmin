<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/../abstract/class.ilExamAdminUserQuery.php');

/**
 * Connector to the main LMS
 *
 * @author Fred Neumann <fred.neumann@fim.uni-erlangen.de>
 */
class ilExamAdminConnector extends ilExamAdminUserQuery
{
    /** @var  self $instance */
    private static $instance;

    /** @var ilExamAdminPlugin */
    protected $plugin;

    /** @var ilExamAdminConnectorDB | null */
    protected $db;


    /**
     * ilExamAdminConnector constructor.
     * @param ilExamAdminPlugin $plugin
     * @throws Exception
     */
    protected function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->plugin->includeClass('connector/class.ilExamAdminConnectorDB.php');
        $this->db = ilExamAdminConnectorDB::getInstance();
    }

    /**
     * Get the connector instance
     * @param ilExamAdminPlugin $plugin
     * @return self
     * @throws Exception
     */
    public static function getInstance($plugin)
    {
        if (!isset(self::$instance))
        {
            self::$instance = new self($plugin);
        }
        return self::$instance;
    }


    /**
     * Get the user data according to a search pattern
     * @param string $pattern
     * @param bool $with_test_accounts
     * @return array
     */
    public function getUserDataByPattern($pattern, $with_test_accounts = false)
    {
        $condition = $this->getSearchCond($pattern);
        if (empty ($condition) )
        {
            return [];
        }

        $condition = '(' . $condition . ')';
        if ($with_test_accounts == false)
        {
            $condition .= 'AND NOT (' . $this->getTestaccountCond() . ')';
        }

        return $this->queryUserData($condition);
    }



    /**
     * Get the data of users by their login
     * @param string[] $logins
     * @return array[]
     * @throws ilDatabaseException
     */
    public function getUserDataByLogins($logins = [])
    {
        $condition = $this->db->in('login', $logins, false, 'string');
        return $this->queryUserData($condition);
    }

    /**
     * Get the data of users by their matriculation number
     * @param string[] $matriculations
     * @return array[]
     * @throws ilDatabaseException
     */
    public function getUserDataByMatriculations($matriculations = [])
    {
        $condition = $this->db->in('matriculation', $matriculations, false, 'string');
        return $this->queryUserData($condition);
    }


    /**
     * get the user data of course, group or session members
     * @param int $ref_id
     *
     * @return array[]
     * @throws ilDatabaseException
     */
    public function getMemberData($ref_id)
    {
        return array();
    }


    /**
     * @param $ref_id
     */
    protected function getObjectType($ref_id)
    {
        $query = "";
    }
}