<?php

// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExamAdminUsers.php');

/**
 * Class ilExamAdminGroup Users
 */
class ilExamAdminGroupUsers extends ilExamAdminUsers
{
    /** @var ilObjGroup */
    protected $group;

    /** @var ilGroupParticipants */
    protected $participants;
    /**
     * constructor.
     * @param ilExamAdminPlugin $plugin
     * @param ilObjGroup $group
     */
    public function __construct($plugin, $group)
    {
        parent::__construct($plugin);
        $this->group = $group;
        $this->participants = $group->getMembersObject();
    }



    /**
     * Get the overviewData
     * @return array
     */
    public function getOverviewData()
    {
        $categories = [
            self::CAT_LOCAL_ADMIN_LECTURER,
            self::CAT_LOCAL_MEMBER_TESTACCOUNT,
            self::CAT_LOCAL_MEMBER_STANDARD,
            self::CAT_LOCAL_MEMBER_REGISTERED
        ];

        $overview = [];
        foreach ($categories as $category)
        {
            $data = $this->queryUserOverview($this->getCategoryCond($category));
            $overview[] = [
                'category' => $category,
                'active' => $data['active'],
                'inactive' => $data['number'] - $data['active'],
                'oldest_password' => $data['oldest_password'],
                'commands' =>   $this->getCategoryCommands($category)
            ];
        }

        return $overview;
    }


    /**
     * Build a query condition
     * @param $category
     * @return string
     */
    public function getCategoryCond($category)
    {
        switch ($category) {

            case self::CAT_LOCAL_ADMIN_LECTURER:
                return
                    $this->db->in('usr_id', $this->participants->getAdmins(), false, 'integer')
                    . " AND (" . parent::getCategoryCond(self::CAT_GLOBAL_LECTURER) .")";

            case self::CAT_LOCAL_MEMBER_STANDARD:
                return
                    $this->db->in('usr_id', $this->participants->getMembers(), false, 'integer')
                    . " AND NOT (" . parent::getCategoryCond(self::CAT_GLOBAL_TESTACCOUNT).")"
                    . " AND NOT (" . parent::getCategoryCond(self::CAT_GLOBAL_REGISTERED) .")";

            case self::CAT_LOCAL_MEMBER_REGISTERED:
                return
                    $this->db->in('usr_id', $this->participants->getMembers(), false, 'integer')
                    . " AND (" . parent::getCategoryCond(self::CAT_GLOBAL_REGISTERED) .")";

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                return
                    $this->db->in('usr_id', $this->participants->getMembers(), false, 'integer')
                    . " AND (" . parent::getCategoryCond(self::CAT_GLOBAL_TESTACCOUNT) .")";

            default:
                return parent::getCategoryCond($category);
        }
    }


    /**
     * Get the commands available for a category
     * @param $category
     * @return string[]
     */
    public function getCategoryCommands($category)
    {
        switch ($category) {

            case self::CAT_LOCAL_ADMIN_LECTURER:
                return ['synchronizeUsers'];

            case self::CAT_LOCAL_MEMBER_STANDARD:
                return ['activateUsers', 'deactivateUsers', 'synchronizeUsers'];

            case self::CAT_LOCAL_MEMBER_REGISTERED:
                return ['activateUsers', 'deactivateUsers'];

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                return ['synchronizeUsers'];

            default:
                return parent::getCategoryCommands($category);
        }
    }

    /**
     * Get the commands available for a single user of a category
     * @param $category
     * @return string[]
     */
    public function getUserCommands($category)
    {
        switch ($category) {

            case self::CAT_LOCAL_ADMIN_LECTURER:
                return ['synchronizeUser'];

            case self::CAT_LOCAL_MEMBER_STANDARD:
                return ['activateUser', 'deactivateUser', 'synchronizeUser'];

            case self::CAT_LOCAL_MEMBER_REGISTERED:
                return ['activateUser', 'deactivateUser'];

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                return ['synchronizeUser'];

            default:
                return parent::getUserCommands($category);
        }
    }
}