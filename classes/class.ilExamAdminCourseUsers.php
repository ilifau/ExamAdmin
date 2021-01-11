<?php

// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExamAdminUsers.php');

/**
 * Class ilExamAdminGroup Users
 */
class ilExamAdminCourseUsers extends ilExamAdminUsers
{
    /** @var ilObjCourse */
    protected $course;

    /** @var ilCourseParticipants */
    protected $participants;

    /** @var int */
    protected $testaccount_role;

    /**
     * constructor.
     * @param ilExamAdminPlugin $plugin
     * @param ilObjCourse $course
     */
    public function __construct($plugin, $course)
    {
        global $DIC;

        parent::__construct($plugin);
        $this->course = $course;
        $this->participants = $course->getMembersObject();

        foreach($DIC->rbac()->review()->getRolesOfObject($this->course->getRefId(), true) as $role_id) {
            $title = ilObject::_lookupTitle($role_id);
            if ($title == $this->config->get(ilExamAdminConfig::LOCAL_TESTACCOUNT_ROLE)) {
                $this->testaccount_role = $role_id;
            }
        }
    }



    /**
     * Get the overviewData
     * @return array
     */
    public function getOverviewData()
    {
        $categories = [
            self::CAT_LOCAL_ADMIN_LECTURER,
            self::CAT_LOCAL_TUTOR_CORRECTOR,
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
                    $this->db->in('usr_id', $this->participants->getAdmins(), false, 'integer');

            case self::CAT_LOCAL_TUTOR_CORRECTOR:
                return
                    $this->db->in('usr_id', $this->participants->getTutors(), false, 'integer');

            case self::CAT_LOCAL_MEMBER_STANDARD:
                return
                    $this->db->in('usr_id', $this->participants->getMembers(), false, 'integer')
                    . " AND NOT (" . $this->getCategoryCond(self::CAT_LOCAL_MEMBER_TESTACCOUNT) . ")"
                    . " AND NOT (" . parent::getCategoryCond(self::CAT_GLOBAL_REGISTERED) . ")";

            case self::CAT_LOCAL_MEMBER_REGISTERED:
                return
                    $this->db->in('usr_id', $this->participants->getMembers(), false, 'integer')
                    . " AND (" . parent::getCategoryCond(self::CAT_GLOBAL_REGISTERED) .")";

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                return
                    $this->db->in('usr_id', $this->getTestaccounts(), false, 'integer');

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
        if ($this->plugin->hasAdminAccess()) {
            switch ($category) {
                case self::CAT_LOCAL_ADMIN_LECTURER:
                case self::CAT_LOCAL_TUTOR_CORRECTOR:
                case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                    return ['synchronizeUsers'];
                case self::CAT_LOCAL_MEMBER_STANDARD:
                    return ['activateUsers', 'deactivateUsers', 'synchronizeUsers'];
                case self::CAT_LOCAL_MEMBER_REGISTERED:
                    return ['activateUsers', 'deactivateUsers'];
                default:
                    return parent::getCategoryCommands($category);
            }
        }
        else {
            switch ($category) {
                case self::CAT_LOCAL_ADMIN_LECTURER:
                case self::CAT_LOCAL_TUTOR_CORRECTOR:
                case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                    return ['synchronizeUsers'];
                case self::CAT_LOCAL_MEMBER_STANDARD:
                    return ['activateUsers', 'synchronizeUsers'];
                case self::CAT_LOCAL_MEMBER_REGISTERED:
                    return [];
                default:
                    return parent::getCategoryCommands($category);
            }
        }
    }

    /**
     * Get the commands available for a single user of a category
     * @param $category
     * @return string[]
     */
    public function getUserCommands($category)
    {
        if ($this->plugin->hasAdminAccess()) {
            switch ($category) {
                case self::CAT_LOCAL_ADMIN_LECTURER:
                case self::CAT_LOCAL_TUTOR_CORRECTOR:
                case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                    return ['synchronizeUser', 'removeUser'];
                case self::CAT_LOCAL_MEMBER_STANDARD:
                    return ['activateUser', 'deactivateUser', 'synchronizeUser', 'removeUser'];
                case self::CAT_LOCAL_MEMBER_REGISTERED:
                    return ['activateUser', 'deactivateUser', 'rewriteUser', 'removeUser'];
                default:
                    return parent::getUserCommands($category);
            }
        }
        else {
            switch ($category) {
                case self::CAT_LOCAL_ADMIN_LECTURER:
                case self::CAT_LOCAL_TUTOR_CORRECTOR:
                case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                    return ['synchronizeUser', 'removeUser'];
                case self::CAT_LOCAL_MEMBER_STANDARD:
                    return ['activateUser', 'synchronizeUser', 'removeUser'];
                case self::CAT_LOCAL_MEMBER_REGISTERED:
                    return ['removeUser'];
                default:
                    return parent::getUserCommands($category);
            }
        }

    }

    /**
     * Add Course Participants
     * @param int[] $usr_ids
     * @param bool $local       usr_ids are local (otherwise from connection)
     * @param string $category
     * @return string[] list of logins
     * @throws Exception
     */
    public function addParticipants($usr_ids, $local, $category)
    {
        $as_testaccount = false;
        $with_testaccounts = false;

        switch ($category) {
            case self::CAT_LOCAL_ADMIN_LECTURER:
                $global_role = $this->config->get(ilExamAdminConfig::GLOBAL_LECTURER_ROLE);
                $local_role = IL_CRS_ADMIN;
                $with_testaccounts = true;
                break;

            case self::CAT_LOCAL_TUTOR_CORRECTOR:
                $global_role = $this->config->get(ilExamAdminConfig::GLOBAL_LECTURER_ROLE);
                $local_role = IL_CRS_TUTOR;
                $with_testaccounts = true;
                break;

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                $global_role = $this->config->get(ilExamAdminConfig::GLOBAL_LECTURER_ROLE);
                $local_role = IL_CRS_MEMBER;
                $as_testaccount = true;
                break;

            case self::CAT_LOCAL_MEMBER_STANDARD:
                $global_role = $this->config->get(ilExamAdminConfig::GLOBAL_PARTICIPANT_ROLE);
                $local_role = IL_CRS_MEMBER;
                break;

            case self::CAT_LOCAL_MEMBER_REGISTERED:
            default:
                return [];
        }

        $added = [];
        if ($local)  {
            foreach ($this->getUserDataByIds($usr_ids) as $user) {
                if ($as_testaccount && $this->addTestaccount($user['usr_id'])) {
                    $added[] = $user['login'];
                }
                elseif ($this->participants->add($user['usr_id'], $local_role)) {
                    $added[] = $user['login'];
                }
                if ($with_testaccounts) {
                    foreach ($this->getTestaccountData($user['login']) as $test) {
                        if ($this->addTestaccount($user['usr_id'])) {
                            $added[] = $test['login'];
                        }
                    }
                }
            }
        }
        else {
            $connObj = $this->plugin->getConnector();
            foreach ($connObj->getUserDataByIds($usr_ids) as $user) {
                $user = $this->getMatchingUser($user, true, $global_role);
                if ($as_testaccount && $this->addTestaccount($user['usr_id'])) {
                    $added[] = $user['login'];
                }
                elseif ($this->participants->add($user['usr_id'], $local_role)) {
                    $added[] = $user['login'];
                }
                if ($with_testaccounts) {
                    foreach ($connObj->getTestaccountData($user['login']) as $test) {
                        $test = $this->getMatchingUser($test, true, $this->config->get(ilExamAdminConfig::GLOBAL_LECTURER_ROLE));
                        if ($this->addTestaccount($user['usr_id'])) {
                            $added[] = $test['login'];
                        }
                    }
                }
            }
        }
        return $added;
    }

    /**
     * Add Course Participants
     * @param int[] $usr_ids
     * @return string[] list of logins
     * @throws Exception
     */
    public function removeParticipants($usr_ids)
    {
        $removed = [];
        foreach ($this->getUserDataByIds($usr_ids) as $user) {
            if ($this->participants->delete($user['usr_id'])) {
                $removed[] = $user['login'];
            }
        }
        return $removed;
    }


    /**
     * Add a test account to the course
     * @return bool
     */
    public function addTestaccount($usr_id)
    {
        global $DIC;

        $rbacadmin = $DIC->rbac()->admin();
        $rbacreview = $DIC->rbac()->review();
        $event = $DIC->event();

        if ($this->testaccount_role && !$rbacreview->isAssigned($usr_id, $this->testaccount_role)) {
            $rbacadmin->assignUser($this->testaccount_role, $usr_id);
            $event->raise(
                'Modules/Course',
                "addParticipant",
                array(
                    'obj_id' => $this->course->getId(),
                    'usr_id' => $usr_id,
                    'role_id' => $this->testaccount_role)
            );

            return true;
        }
        return false;
    }

    /**
     * Get the test accounts of a course
     * @return int[]
     */
    public function getTestaccounts()
    {
        global $DIC;
        $rbacreview = $DIC->rbac()->review();

        if ($this->testaccount_role) {
            return $rbacreview->assignedUsers($this->testaccount_role);
        }
        return [];
    }


	/**
	 * Rewrite a self-registered user
	 * A new user is created, if it does not exist in the local platform
	 * The test passes of tests in this course are moved from the self registered user to the new one
	 * The new user is added to the course
	 * The original user is kept but removed from the course
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

		// check for conflicting tests in the current course
		if ($local) {
			$orig_tests = $this->getTestsOfUser($orig_id, $this->course->getRefId());
			$new_tests = $this->getTestsOfUser($new_id, $this->course->getRefId());
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
			$this->changeTestsOfUser($orig, $new, $this->course->getRefId());

			// switch the group membership
			$this->course->getMembersObject()->delete($orig_id);
			$this->course->getMembersObject()->add($new_id, IL_CRS_MEMBER);

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