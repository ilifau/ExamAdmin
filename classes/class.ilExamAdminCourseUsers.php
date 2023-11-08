<?php

// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExamAdminUsers.php');

/**
 * User management for courses
 * Note: ilCourseParticipants is not used because
 * - it doesn't distinct members from test accounts well
 * - removing of single users would re-read the membership data
 */
class ilExamAdminCourseUsers extends ilExamAdminUsers
{
    // supported course roles
    CONST ROLE_ADMIN = 'admin';
    CONST ROLE_TUTOR = 'tutor';
    CONST ROLE_MEMBER = 'member';
    CONST ROLE_TESTACCOUNT = 'testaccount';

    /**
     * IDs of the supported course roles
     * @var array
     */
    protected $role_ids = [
        self::ROLE_ADMIN => null,
        self::ROLE_TUTOR => null,
        self::ROLE_MEMBER => null,
        self::ROLE_TESTACCOUNT => null
    ];

    /**
     * Assignments of users to the supported course roles
     * @var array[]
     */
    protected $assignments = [
        self::ROLE_ADMIN => [],
        self::ROLE_TUTOR => [],
        self::ROLE_MEMBER => [],
        self::ROLE_TESTACCOUNT => [],
    ];

    /** @var ilObjCourse */
    protected $course;


    /** @var ilRecommendedContentManager */
    protected $recommendedContentManager;

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
        $this->recommendedContentManager = new ilRecommendedContentManager();


        foreach($DIC->rbac()->review()->getRolesOfObject($this->course->getRefId(), true) as $role_id) {
            $title = ilObject::_lookupTitle($role_id);
            $assigned = $DIC->rbac()->review()->assignedUsers($role_id);

            switch (substr($title, 0, 8)) {
                case 'il_crs_a':
                    $this->role_ids[self::ROLE_ADMIN] = $role_id;
                    $this->assignments[self::ROLE_ADMIN] = $assigned;
                    break;

                case 'il_crs_t':
                    $this->role_ids[self::ROLE_TUTOR] = $role_id;
                    $this->assignments[self::ROLE_TUTOR] = $assigned;
                    break;

                case 'il_crs_m':
                    $this->role_ids[self::ROLE_MEMBER] = $role_id;
                    $this->assignments[self::ROLE_MEMBER] = $assigned;
                    break;

                case substr($this->config->get(ilExamAdminConfig::LOCAL_TESTACCOUNT_ROLE), 0, 8):
                    $this->role_ids[self::ROLE_TESTACCOUNT] = $role_id;
                    $this->assignments[self::ROLE_TESTACCOUNT] = $assigned;
                    break;
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
                    $this->db->in('usr_id', $this->assignments[self::ROLE_ADMIN], false, 'integer');

            case self::CAT_LOCAL_TUTOR_CORRECTOR:
                return
                    $this->db->in('usr_id', $this->assignments[self::ROLE_TUTOR], false, 'integer');

            case self::CAT_LOCAL_MEMBER_STANDARD:
                return
                    $this->db->in('usr_id', $this->assignments[self::ROLE_MEMBER], false, 'integer')
                    . " AND NOT (" . parent::getCategoryCond(self::CAT_GLOBAL_REGISTERED) . ")";

            case self::CAT_LOCAL_MEMBER_REGISTERED:
                return
                    $this->db->in('usr_id', $this->assignments[self::ROLE_MEMBER], false, 'integer')
                    . " AND (" . parent::getCategoryCond(self::CAT_GLOBAL_REGISTERED) .")";

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                return
                    $this->db->in('usr_id', $this->assignments[self::ROLE_TESTACCOUNT], false, 'integer');

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
                    return ['activateUser', 'deactivateUser', 'synchronizeUser', 'removeUser', 'logoutUser'];
                case self::CAT_LOCAL_MEMBER_REGISTERED:
                    return ['activateUser', 'deactivateUser', 'rewriteUser', 'removeUser', 'logoutUser'];
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
                    return ['activateUser', 'synchronizeUser', 'removeUser', 'logoutUser'];
                case self::CAT_LOCAL_MEMBER_REGISTERED:
                    return ['removeUser', 'logoutUser'];
                default:
                    return parent::getUserCommands($category);
            }
        }

    }

    /**
     * Check if the user is already a participant
     * @param int $usr_id
     * @return bool
     */
    public function isParticipant($usr_id)
    {
        foreach ([self::ROLE_ADMIN, self::ROLE_TUTOR, self::ROLE_MEMBER, self::ROLE_TESTACCOUNT] as $role) {
            if (in_array($usr_id, $this->assignments[$role])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add Course Participants
     * @param int[] $usr_ids
     * @param bool $local       usr_ids are local (otherwise from connection)
     * @param string $category
     * @param bool $change_existing
     * @return string[] list of logins
     * @throws Exception
     */
    public function addParticipants($usr_ids, $local, $category, $change_existing = true)
    {
        switch ($category) {
            case self::CAT_LOCAL_ADMIN_LECTURER:
                $global_role_id = $this->config->get(ilExamAdminConfig::GLOBAL_LECTURER_ROLE);
                $local_role = self::ROLE_ADMIN;
                $with_testaccounts = true;
                break;

            case self::CAT_LOCAL_TUTOR_CORRECTOR:
                $global_role_id = $this->config->get(ilExamAdminConfig::GLOBAL_LECTURER_ROLE);
                $local_role = self::ROLE_TUTOR;
                $with_testaccounts = true;
                break;

            case self::CAT_LOCAL_MEMBER_TESTACCOUNT:
                $global_role_id = $this->config->get(ilExamAdminConfig::GLOBAL_LECTURER_ROLE);
                $local_role = self::ROLE_TESTACCOUNT;
                $with_testaccounts = false;
                break;

            case self::CAT_LOCAL_MEMBER_STANDARD:
            case self::CAT_LOCAL_MEMBER_REGISTERED:
                $global_role_id = $this->config->get(ilExamAdminConfig::GLOBAL_PARTICIPANT_ROLE);
                $local_role = self::ROLE_MEMBER;
                $with_testaccounts = false;
                break;

            default:
                // not supported
                return [];
        }

        $added = [];
        if ($local)  {
            foreach ($this->getUserDataByIds($usr_ids) as $user) {
                if ($this->addParticipant($user['usr_id'], $local_role, $change_existing)) {
                        $this->addGlobalRole($user['usr_id'], $global_role_id);
                        $added[] = $user['login'];
                }

                if ($with_testaccounts) {
                    foreach ($this->getTestaccountData($user['login']) as $test) {
                        if ($this->addParticipant($test['usr_id'], self::ROLE_TESTACCOUNT, false)) {
                            // use same global role as the main user
                            $this->addGlobalRole($user['usr_id'], $global_role_id);
                            $added[] = $test['login'];
                        }
                    }
                }
            }
        }
        else {
            $connObj = $this->plugin->getConnector();
            foreach ($connObj->getUserDataByIds($usr_ids) as $user) {
                $user = $this->getMatchingUser($user, true, $global_role_id);
                if ($this->addParticipant($user['usr_id'], $local_role, $change_existing)) {
                    $this->addGlobalRole($user['usr_id'], $global_role_id);
                    $added[] = $user['login'];
                }

                if ($with_testaccounts) {
                    foreach ($connObj->getTestaccountData($user['login']) as $test) {
                        $test = $this->getMatchingUser($test, true, $global_role_id);
                        if ($this->addParticipant($test['usr_id'], self::ROLE_TESTACCOUNT, false)) {
                            // use same global role as the main user
                            $this->addGlobalRole($user['usr_id'], $global_role_id);
                            $added[] = $test['login'];
                        }
                    }
                }
            }
        }
        return $added;
    }


    /**
     * Add a participant to a known course role (move it from other known course roles)
     * - allow adding to test account
     *
     * @param int $usr_id
     * @param string $new_role
     * @param $change_existing
     * @return bool
     * @see ilCourseParticipants::add()
     */
    public function addParticipant($usr_id, $new_role, $change_existing = true)
    {
        global $DIC;

        // local role does not exist
        if (!isset($this->role_ids[$new_role])) {
            return false;
        }

        // user is already assigned to the local role
        if (in_array($usr_id, $this->assignments[$new_role])) {
            return false;
        }

        // other role assignment should be kept
        if (!$change_existing && $this->isParticipant($usr_id)) {
            return false;
        }

        // don't change role assignment of own account, if not global admin
        if ($usr_id == $DIC->user()->getId() && !$this->plugin->hasAdminAccess()) {
            return false;
        }

        // remove from other course role but keep desktop item etc.
        $this->removeParticipant($usr_id, false);

        // assign the user to the course role
        $DIC->rbac()->admin()->assignUser($this->role_ids[$new_role], $usr_id);
        $this->assignments[$new_role][] = $usr_id;

        $this->recommendedContentManager->addObjectRecommendation($usr_id, $this->course->getRefId());

        // raise event like in ilParticipants::add()
        $DIC->event()->raise(
            'Modules/Course',
            "addParticipant",
            array(
                'obj_id' => $this->course->getId(),
                'usr_id' => $usr_id,
                'role_id' => $this->role_ids[$new_role])
        );

        return true;
    }


    /**
     * Remove Course Participants
     * @param int[] $usr_ids
     * @return string[] list of logins
     */
    public function removeParticipants($usr_ids)
    {
        $removed = [];
        foreach ($this->getUserDataByIds($usr_ids) as $user) {
            if ($this->removeParticipant($user['usr_id'], true)) {
                $removed[] = $user['login'];
            }
        }
        return $removed;
    }

    /**
     * Remove a participant from the known course roles
     * @param int $usr_id
     * @param bool $finally user is not moved to another course role
     * @return bool user was in course
     * @see ilCourseParticipants::add()
     */
    public function removeParticipant($usr_id, $finally = true)
    {
        global $DIC;

        // remove the user from all known course roles
        $removed = false;
        foreach ([self::ROLE_ADMIN, self::ROLE_TUTOR, self::ROLE_MEMBER, self::ROLE_TESTACCOUNT] as $role) {
            $key = array_search($usr_id, $this->assignments[$role]);
            if ($key !== false) {

                // don't delete the own account, if not global admin
                if ($usr_id == $DIC->user()->getId() && !$this->plugin->hasAdminAccess()) {
                    return false;
                }

                $DIC->rbac()->admin()->deassignUser($this->role_ids[$role], $usr_id);
                unset($this->assignments[$key]);
                $removed = true;
            }
        }

        // cleanup if user is finally removed from the course (not role moved)
        if ($finally && $removed) {
            $this->recommendedContentManager->removeObjectRecommendation($usr_id, $this->course->getRefId());

            $DIC->event()->raise(
               'Modules/Course',
                "deleteParticipant",
                array(
                    'obj_id' => $this->course->getId(),
                    'usr_id' => $usr_id)
            );
        }

        return $removed;
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