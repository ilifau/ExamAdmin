<?php

// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/abstract/class.ilExamAdminUserQuery.php');

/**
 * Class ilExamAdminUsers
 */
class ilExamAdminUsers extends ilExamAdminUserQuery
{
    /** @var ilExamAdminPlugin */
    protected $plugin;

    // user categories
    const CAT_GLOBAL_ADMIN = 'cat_global_admin';
    const CAT_GLOBAL_LECTURER = 'cat_global_lecturer';
    const CAT_GLOBAL_PARTICIPANT = 'cat_global_participant';
    const CAT_GLOBAL_TESTACCOUNT = 'cat_global_testaccount';
    const CAT_GLOBAL_REGISTERED = 'cat_global_registered';

    const CAT_LOCAL_ADMIN_LECTURER = 'cat_local_admin_lecturer';
    const CAT_LOCAL_MEMBER_TESTACCOUNT = 'cat_local_member_testaccount';
    const CAT_LOCAL_MEMBER_STANDARD = 'cat_local_member_standard';
    const CAT_LOCAL_MEMBER_REGISTERED = 'cat_local_member_registered';

    /**
     * constructor.
     * @param ilExamAdminPlugin $plugin
     * @param ilObjGroup $group
     */
    public function __construct($plugin)
    {
        global $DIC;

        $this->plugin = $plugin;
        $this->db = $DIC->database();
    }


    /**
     * Get the user data according to a search pattern
     * @param string $pattern
     * @param bool $with_test_accounts
     * @param string|null $category
     * @return array
     */
    public function getUserDataByPattern($pattern, $with_test_accounts = false, $category = null)
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
        if (isset($category))
        {
            $condition .= ' AND (' . $this->getCategoryCond($category) . ')';
        }

        return $this->queryUserData($condition);
    }


    /**
     * Get the user data of a category
     * @param $category
     * @return array
     */
    public function getCategoryUserData($category)
    {
        $condition = $this->getCategoryCond($category);
        if (!empty($condition))
        {
            return $this->queryUserData($condition);
        }
        return [];
    }

    /**
     * Set the active status of a user given by id
     * @param integer $usr_id
     * @param integer $active
     */
    public function setActiveByUserId($usr_id, $active)
    {
        $this->updateActive('usr_id = '.$this->db->quote($usr_id, 'integer'), $active);
    }

    /**
     * Set the active status of users by category
     * @param string $category
     * @param integer $active
     */
    public function setActiveByCategory($category, $active)
    {
        $condition = $this->getCategoryCond($category);
        if ($condition)
        {
            $this->updateActive($condition, $active);
        }
    }


    /**
     * Synchronize the data of users given by category
     * @param string $category
     * @throws ilException
     */
    public function synchronizeByCategory($category)
    {
        $connObj = $this->plugin->getConnector();
        foreach ($this->getCategoryUserData($category) as $user)
        {
            $data = $connObj->getSingleUserDataByLogin($user['login']);
            if (isset($data))
            {
                $this->applyUserData($user['usr_id'], $data);
            }
        }
    }

    /**
     * Synchronize the data of a user given by user_id
     * @param integer $usr_id
     * @throws ilException
     */
    public function synchronizeByUserId($usr_id)
    {
        $user = $this->getSingleUserDataById($usr_id);
        if (isset($user))
        {
            $connObj = $this->plugin->getConnector();
            $data = $connObj->getSingleUserDataByLogin($user['login']);
            if (isset($data))
            {
                $this->applyUserData($user['usr_id'], $data);
            }
        }
    }


    /**
     * Update the active status of users
     * @param string $condition
     * @param int $active
     */
    protected function updateActive($condition, $active)
    {
        $active =  $this->db->quote($active, 'integer');
        if (!empty($condition))
        {
            $query = "UPDATE usr_data SET active = $active WHERE (" . $condition . ")";
            $this->db->manipulate($query);
        }
    }


    /**
     * Query the number of users for a condition
     * @param string $condition
     * @return mixed
     */
    protected function queryUserOverview($condition)
    {
        $query = "SELECT COUNT(*) AS number, SUM(active) AS active, MAX(last_password_change) AS last_password_change FROM usr_data WHERE (" . $condition . ")";

        $result = $this->db->query($query);
        $row = $this->db->fetchAssoc($result);
        return $row;
    }



    /**
     * Build a query condition
     * @param $category
     * @return string
     */
    protected function getCategoryCond($category)
    {
        switch ($category) {

            case self::CAT_GLOBAL_ADMIN:
                $rol_id = $this->db->quote($this->plugin->getConfig()->get('global_admin_role'), 'integer');
                return "usr_id IN (SELECT usr_id FROM rbac_ua WHERE rol_id = " . $rol_id . ")";

            case self::CAT_GLOBAL_LECTURER:
                $rol_id = $this->db->quote($this->plugin->getConfig()->get('global_lecturer_role'), 'integer');
                return "usr_id IN (SELECT usr_id FROM rbac_ua WHERE rol_id = " . $rol_id. ")";

            case self::CAT_GLOBAL_PARTICIPANT:
                $rol_id = $this->db->quote($this->plugin->getConfig()->get('global_participant_role'), 'integer');
                return "usr_id IN (SELECT usr_id FROM rbac_ua WHERE rol_id = " . $rol_id. ")";

            case self::CAT_GLOBAL_TESTACCOUNT:
                return $this->getTestaccountCond();

            case self::CAT_GLOBAL_REGISTERED:
                return "is_self_registered = 1";
        }
    }


    /**
     * Get the commands available for a category
     * @param $category
     * @return string[]
     */
    public function getCategoryCommands($category)
    {
        switch($category) {
            case self::CAT_GLOBAL_ADMIN:
                return ['synchronizeUsers'];

            case self::CAT_GLOBAL_LECTURER:
                return ['activateUsers', 'deactivateUsers', 'synchronizeUsers'];

            case self::CAT_GLOBAL_PARTICIPANT:
                return ['activateUsers', 'deactivateUsers', 'synchronizeUsers'];

            case self::CAT_GLOBAL_TESTACCOUNT:
                return ['activateUsers', 'deactivateUsers', 'synchronizeUsers'];

            case self::CAT_GLOBAL_REGISTERED:
                return ['activateUsers', 'deactivateUsers'];

            default:
                return [];
        }
    }


    /**
     * Get the commands available for a single user of a category
     * @param $category
     * @return string[]
     */
    public function getUserCommands($category)
    {
        switch($category) {
            case self::CAT_GLOBAL_ADMIN:
                return ['synchronizeUser'];

            case self::CAT_GLOBAL_LECTURER:
                return ['activateUser', 'deactivateUser', 'synchronizeUser'];

            case self::CAT_GLOBAL_PARTICIPANT:
                return ['activateUser', 'deactivateUser', 'synchronizeUser'];

            case self::CAT_GLOBAL_TESTACCOUNT:
                return ['activateUser', 'deactivateUser', 'synchronizeUser'];

            case self::CAT_GLOBAL_REGISTERED:
                return ['activateUser', 'deactivateUser'];

            default:
                return [];
        }
    }


    /**
     * Get data of a user that matches the given data by login
     * @param array $data
     * @param bool $create  create the user if none is found
     * @param int $role global role to be used for the new user
     * @return array|null
     * @throws Exception
     */
    public function getMatchingUser($data, $create = true, $role_id = null)
    {
        $user = $this->getSingleUserDataByLogin($data['login']);
        if (empty($user) && $create)
        {
            $usr_id = $this->createUser($data, $role_id);
            return $this->getSingleUserDataById($usr_id);
        }
        return $user;
    }


    /**
     * Create a user with given data
     * @param array $data
     * @param int $role_id global role to be used for the new user
     * @return int  user_id
     * @throws Exception
     */
    public function createUser($data, $role_id = null)
    {
        global $DIC;

        $userObj = new ilObjUser();
        $userObj->setLogin($data['login']);
        $userObj->setFirstname($data['firstname']);
        $userObj->setLastname($data['lastname']);
        $userObj->setTitle($data['title']);
        $userObj->setActive($role_id == $this->plugin->getConfig()->get('global_lecturer_role'));
        $usr_id = $userObj->create();

        $userObj->updateOwner();
        $userObj->saveAsNew();

        if (!empty($role_id))
        {
            $DIC->rbac()->admin()->assignUser($role_id, $usr_id);
        }

        $this->applyUserData($usr_id, $data);
        return $usr_id;
    }


    /**
     * Apply a data array to a user account
     * @param int $usr_id
     * @param $data
     * @throws ilDateTimeException
     */
    public function applyUserData($usr_id, $data)
    {
        $datetime = (new ilDateTime(time(), IL_CAL_UNIX))->get(IL_CAL_DATETIME);

        $this->db->update('usr_data',
            [
                'login' => ['text', $data['login']],
                'firstname' => ['text', $data['firstname']],
                'lastname' => ['text', $data['lastname']],
                'title' => ['text', $data['title']],
                'gender' => ['text', $data['gender']],
                'email' => ['text', $data['email']],
                'institution' => ['text', $data['institution']],
                'matriculation' => ['text', $data['matriculation']],
                'approve_date' => ['text', $data['approve_date']],
                'agree_date' => ['text', $data['agree_date']],
                'ext_account' => ['text', $data['ext_account']],
                'passwd' => ['text', $data['passwd']],
                'ext_passwd' => ['text', $data['ext_passwd']],
                'passwd_enc_type' => ['text', $data['passwd_enc_type']],
                'passwd_salt' => ['text', $data['passwd_salt']],
                //'active' => ['integer', $data['active']],
                'time_limit_unlimited' => ['integer', $data['time_limit_unlimited']],
                'time_limit_from' => ['integer', $data['time_limit_from']],
                'time_limit_until' => ['integer', $data['time_limit_until']],
                'last_update' => ['text', $datetime],
                'last_password_change' => ['integer', time()],
            ],
            [
                'usr_id' => ['integer', $usr_id]
            ]
        );
    }

}