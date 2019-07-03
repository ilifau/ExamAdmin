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
                'last_password_change' => $data['last_password_change'],
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

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                return ['synchronizeUsers'];

			case self::CAT_LOCAL_MEMBER_REGISTERED:
				return ['activateUsers', 'deactivateUsers', 'synchronizeUsers'];

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

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                return ['synchronizeUser'];

			case self::CAT_LOCAL_MEMBER_REGISTERED:
				return ['activateUser', 'deactivateUser', 'rewriteUser'];

			default:
                return parent::getUserCommands($category);
        }
    }

	/**
	 * Add lecturers
	 * @param int[] $usr_ids
	 * @param bool $local
	 * @return string[] list of logins
	 * @throws Exception
	 */
	public function addLecturers($usr_ids, $local)
	{
		$added = [];
		if ($local)
		{
			$users = $this->getUserDataByIds($usr_ids);
			foreach ($users as $user)
			{
				$this->group->getMembersObject()->add($user['usr_id'], IL_GRP_ADMIN);
				$added[] = $user['login'];
				foreach ($this->getTestaccountData($user['login']) as $test)
				{
					$this->group->getMembersObject()->add($test['usr_id'], IL_GRP_MEMBER);
					$added[] = $test['login'];
				}
			}
		}
		else
		{
			$connObj = $this->plugin->getConnector();
			$users = $connObj->getUserDataByIds($usr_ids);
			foreach ($users as $user)
			{
				$user = $this->getMatchingUser($user, true, $this->plugin->getConfig()->get('global_lecturer_role'));
				$this->group->getMembersObject()->add($user['usr_id'], IL_GRP_ADMIN);
				$added[] = $user['login'];
				foreach ($connObj->getTestaccountData($user['login']) as $test)
				{
					$test = $this->getMatchingUser($test, true, $this->plugin->getConfig()->get('global_lecturer_role'));
					$this->group->getMembersObject()->add($test['usr_id'], IL_GRP_MEMBER);
					$added[] = $test['login'];
				}
			}
		}
		return $added;
	}

	/**
	 * Add  members
	 * @param int[] $usr_ids
	 * @param bool $local
	 * @return string[] list of logins
	 * @throws Exception
	 */
	public function addMembers($usr_ids, $local)
	{
		$added = [];
		if ($local)
		{
			$users = $this->getUserDataByIds($usr_ids);
			foreach ($users as $user)
			{
				$this->group->getMembersObject()->add($user['usr_id'], IL_GRP_MEMBER);
				$added[] = $user['login'];
			}
		}
		else
		{
			$users = $this->plugin->getConnector()->getUserDataByIds($usr_ids);
			foreach ($users as $user)
			{
				$user = $this->getMatchingUser($user, true, $this->plugin->getConfig()->get('global_participant_role'));
				$this->group->getMembersObject()->add($user['usr_id'], IL_GRP_MEMBER);
				$added[] = $user['login'];
			}
		}
		return $added;
	}

	/**
	 * Rewrite a self-registered user
	 * A new user is created, if it does not exist in the local platform
	 * The test passes of tests in this group are moved from the self registered user to the new one
	 * The new user is added to the group
	 * The original user is kept but removed from the group
	 * Both users get comments about their relationship
	 *
	 * @param int $orig_id		local user id of the self-registered user
	 * @param int $new_id		user id of the new user in the local or remote platform
	 * @param bool $local		new user is in the local platform
	 * @param bool $rewrite		the rewriting should be done
	 * @return array [ orig => array, new => array, done=> bool, conflicts => [ref_id => title] ]
	 * @throws Exception
	 */
	public function rewriteUser($orig_id, $new_id, $local, $rewrite)
	{
		$done = false;
		$conflicts = [];

		// find the original and new user
		$orig = $this->getSingleUserDataById($orig_id);
		if ($local) {
			$new = $this->getSingleUserDataById($new_id);
		}
		else {
			$new = $this->plugin->getConnector()->getSingleUserDataById($new_id);
			$matching = $this->getMatchingUser($new, false);
			if ($matching) {
				$new = $matching;
				$new_id = $matching['usr_id'];
				$local = true;
			}
		}

		// check for conflicting tests in the current group
		if ($local) {
			$orig_tests = $this->getTestsOfUser($orig_id, $this->group->getRefId());
			$new_tests = $this->getTestsOfUser($new_id, $this->group->getRefId());
			foreach ($orig_tests as $ref_id => $test) {
				if (isset($new_tests[$ref_id])) {
					$conflicts[$ref_id] = $test['title'];
				}
			}
		}

		// do the rewriting
		if ($rewrite && !empty($new) && empty($conflicts)) {

			// create a new local account for the remote user
			if (!$local) {
				$new = $this->getMatchingUser($new, true, $this->plugin->getConfig()->get('global_participant_role'));
				$new_id = $new['usr_id'];
			}

			// transfer tests of the original user (will be logged)
			$this->changeTestsOfUser($orig, $new, $this->group->getRefId());

			// switch the group membership
			$this->group->getMembersObject()->delete($orig_id);
			$this->group->getMembersObject()->add($new_id, IL_GRP_MEMBER);

			// deactivate the old user
			$this->setActiveByUserId($orig_id, false);
			$done = true;
		}

		return [
			'orig' => $orig,
			'new' => $new,
			'done' => $done,
			'conflicts' => $conflicts
		];
	}
}