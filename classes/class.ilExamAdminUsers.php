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
     * Set the active status of users by category
     * @param string $category
     */
    public function setActiveByCategory($category, $active)
    {
        $condition = $this->getCategoryCond($category);
        $this->updateActive($this->getCategoryCond($condition), $active);
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
        $query = "SELECT COUNT(*) AS number, COUNT(active) AS active, MAX(last_password_change) AS oldest_password FROM usr_data WHERE (" . $condition . ")";

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
     * Get data if a user that matches the given data by login
     * @param array $data
     * @param bool $create  create the user if none is found
     * @return array|null
     */
    public function getMatchingUser($data, $create = true)
    {
        $user = $this->getSingleUserDataByLogin($data['login']);
        if (empty($user) && $create)
        {
            $usr_id  = $this->createUser($data);
            return $this->getSingleUserDataById($usr_id);
        }
        return $user;
    }


    /**
     * Create a user with given data
     * @param array $data
     * @return int  user_id
     */
    public function createUser($data)
    {
        $userObj = new ilObjUser();
        $userObj->setLogin($data['login']);
        $usr_id = $userObj->create();
        $userObj->updateOwner();
        $userObj->saveAsNew();
        $this->applyUserData($usr_id, $data);

        return $usr_id;
    }


    /**
     * Apply a data array to a user account
     * @param $usr_id
     * @param $data
     */
    public function applyUserData($usr_id, $data)
    {
        $this->db->update('usr_data',
            [
                'firstname' => ['text', $data['firstname']],
                'lastname' => ['text', $data['lastname']],
                'title' => ['text', $data['title']],
                'gender' => ['text', $data['gender']],
                'email' => ['text', $data['email']],
                'institution' => ['text', $data['institution']],
                'matriculation' => ['text', $data['matriculation']],
                'approve_date' => ['text', $data['approve_date']],
                'agree_date' => ['text', $data['agree_date']],
                'passwd' => ['text', $data['passwd']],
                'ext_passwd' => ['text', $data['ext_passwd']],
                'passwd_enc_type' => ['text', $data['passwd_enc_type']],
                'passwd_salt' => ['text', $data['passwd_salt']]
            ],
            [
                'usr_id' => $usr_id
            ]
        );
    }

}