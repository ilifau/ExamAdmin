<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


/**
 * Connector to the main LMS
 *
 * @author Fred Neumann <fred.neumann@fim.uni-erlangen.de>
 */
class ilExamAdminConnector2 extends ilExamAdminUserQuery
{
    /** @var  self $instance */
    private static $instance;

    /** @var ilExamAdminPlugin */
    protected $plugin;

    /** @var ilExamAdminConfig */
    protected $config;

    /** @var ilExamAdminConnectorDB2 | null */
    protected $db;


    /**
     * ilExamAdminConnector constructor.
     * @param ilExamAdminPlugin $plugin
     * @throws Exception
     */
    protected function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->config = $this->plugin->getConfig();
        $this->db = ilExamAdminConnectorDB2::getInstance();
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
     * Get the data of an external object
     */
    public function getObjectDataByRefId($ref_id)
    {
        $query = "SELECT o.* FROM object_data o INNER JOIN object_reference r ON o.obj_id = r.obj_id "
            ." WHERE r.deleted IS NULL AND r.ref_id = " . $this->db->quote($ref_id, 'integer');

        $result = $this->db->query($query);
        if ($row = $this->db->fetchAssoc($result))
        {
            return $row;
        }
        return null;
    }


    /**
     * Get User data of members of a course or group
     * @param int $ref_id
     * @param string $type
     * @return array
     * @throws ilDatabaseException
     */
    public function getUserDataByMembership($ref_id, $type)
    {
        $condition = $this->getMembershipCond($ref_id, $type);
        if ($condition)
        {
            return $this->queryUserData($condition);
        }
        return [];
    }


    /**
     * get the user data of course, group or session members
     * @param int $ref_id
     * @param string $type
     * @return string
     * @throws ilDatabaseException
     */
    public function getMembershipCond($ref_id, $type)
    {
        switch ($type)
        {
            case 'crs':
            case 'grp':
                $query = "SELECT obj_id FROM object_data WHERE type = 'role' AND title = "
                    . $this->db->quote('il_'.$type.'_member_'. $ref_id, 'text');
                $result = $this->db->query($query);
                if ($row = $this->db->fetchAssoc($result))
                {
                    return "usr_id IN (SELECT usr_id FROM rbac_ua WHERE rol_id = ".$this->db->quote($row['obj_id'], 'integer') .")";
                }
        }

        return '';
    }


    /**
     * Get the relevant orga objects from the learning platform
     * @return array    obj_id => title
     */
    public function getOrgaObjects()
    {
        $query = "
            SELECT o.obj_id, o.title, 
            d1.param_value AS `online`, 
            d2.param_value AS `testdata`,
            d3.param_value as `semester`
            FROM object_data o
            INNER JOIN object_reference r ON r.obj_id = o.obj_id AND r.deleted IS NULL
            LEFT JOIN xamo_data d1 ON d1.obj_id = o.obj_id AND d1.param_name = 'online'
            LEFT JOIN xamo_data d2 ON d2.obj_id = o.obj_id AND d2.param_name = 'testdata'
            LEFT JOIN xamo_data d3 ON d3.obj_id = o.obj_id AND d3.param_name = 'semester'
            WHERE o.`type` = 'xamo'
            ";

        $result = $this->db->query($query);

        $testdata = (bool) $this->config->get('testdata');
        $semester = (string) $this->config->get('semester');
        
        $objects = [];
        while ($row = $this->db->fetchAssoc($result)) {
            if (!isset($row['online']) || !$row['online']) {
                continue;
            }
            if (!isset($row['testdata']) || $row['testdata'] != $testdata) {
                continue;
            }
            if (!isset($row['semester']) || $row['semester'] != $semester) {
                continue;
            }
            $objects[$row['obj_id']] = $row['title'];

        }
        return $objects;
    }

}