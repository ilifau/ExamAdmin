<?php

require_once (__DIR__ . '/orga/class.ilExamAdminOrgaRecord.php');

class ilExamAdminCronHandler
{
    private $parent_obj;
    /** @var  ilAccessHandler $access */
    protected $access;

    /** @var  ilLanguage $lng */
    protected $lng;

    /** @var ilDBInterface */
    protected $db;

    /** @var ilExamAdminPlugin $plugin */
    protected $plugin;

    /** @var ilExamAdminConfig */
    protected $config;

    /** @var ilExamAdminConnector */
    protected $connector;

    /** @var string */
    protected $logfile;

    /**
     * constructor
     * @param ilExamAdminPlugin $plugin
     */
    public function __construct($plugin)
    {
        global $DIC;

        $this->access = $DIC->access();
        $this->lng = $DIC->language();
        $this->db = $DIC->database();

        $this->plugin = $plugin;
        $this->config = $plugin->getConfig();
        $this->connector = $plugin->getConnector();

        $this->logfile = ILIAS_DATA_DIR . '/ExamAdmin.log';


        // prepare remote db access
        $this->plugin->init();
    }

    /**
     * Create or configure courses from the scheduled exams
     * @return array   $ref_id => title
     */
    public function installCourses()
    {
        file_put_contents($this->logfile, date('Y-m-d H:i.s') . "\n", FILE_APPEND);

        $objects = $this->connector->getOrgaObjects();

        $formats = explode(',', $this->config->get('exam_format'));
        $is_presence = in_array('presence', $formats);

        $today =  (new ilDate(time(), IL_CAL_UNIX))->get(IL_CAL_DATE);

        $collection = ilExamAdminOrgaRecord::getCollection()
            ->where(['obj_id' => array_keys($objects)])
            ->where(['exam_date' => $today], '>')
            ->orderBy('exam_date');

        $courses = [];

        /** @var ilExamAdminOrgaRecord $record */
        foreach ($collection->get() as $record) {

            // ignore format matching if presence is required and platform is not for presence
            if ($record->force_presence && !$is_presence) {
                continue;
            }

            // don't create presence exams if status is not changed by exam team
            if ($record->exam_format == 'presence' && $record->booking_status == 'requested') {
                continue;
            }

            // format matches or platform is forced for presence
            if (in_array($record->exam_format, $formats) || ($record->force_presence && $is_presence)) {

                $courses[$record->id] = $record->exam_title;

                if ($ref_id = $this->findCourse($record)) {
                    $mess = "UPDATE " . $record->exam_date . " " . $record->exam_title . "...\n";
                    file_put_contents($this->logfile, $mess, FILE_APPEND);
                    if (!ilContext::usesHTTP()) {
                        echo $mess;
                    }
                    $this->updateCourse($record, $ref_id);
                }
                else {
                    $mess = "CREATE " . $record->exam_date . " " . $record->exam_title . "...\n";
                    file_put_contents($this->logfile, $mess, FILE_APPEND);
                    if (!ilContext::usesHTTP()) {
                        echo $mess;
                    }
                    $ref_id = $this->createCourse($record);
                    $this->updateCourse($record, $ref_id);
                    $this->removeRootParticipant($ref_id);
                }
            }
         }

        return $courses;
    }


    /**
     * Find an existing course by the data of an orga record
     * @param ilExamAdminOrgaRecord $record
     * @return int ref_id
     */
    protected function findCourse($record)
    {
       require_once (__DIR__ . '/param/class.ilExamAdminData.php');

       foreach (ilExamAdminData::findObjectIds(ilExamAdminData::PARAM_ORGA_ID, $record->id) as $obj_id) {
          foreach (ilObject::_getAllReferences($obj_id) as $ref_id) {
              if (!ilObject::_isInTrash($ref_id)) {
                  return $ref_id;
              }
          }
       }
       return false;
    }


    /**
     * Create a course from the data of an orga record
     * @param ilExamAdminOrgaRecord $record
     * @return int ref_id
     */
    protected function createCourse($record)
    {
        $cat_ref_id = $this->findCategory($record->fau_unit);

        if (!$cat_ref_id) {
            $cat_ref_id = $this->createCategory($record->fau_unit);
        }

        $master_course = $this->config->get('master_course_' . $record->exam_format);
        if (empty($master_course)) {
            $master_course = $this->config->get('master_course');
        }

        return $this->copyCourse($master_course, $cat_ref_id);
    }

    /**
     * Find a category by its title
     * @param string $title
     * @return int ref_id
     */
    protected function findCategory($title)
    {
        $query = "
            SELECT r.ref_id
            FROM object_data o 
            INNER JOIN object_reference r ON r.obj_id = o.obj_id
            WHERE o.title = " . $this->db->quote($title, 'text') . "
            AND o.type = 'cat'
            AND r.deleted IS NULL
        ";

        $result = $this->db->query($query);
        if ($row = $this->db->fetchAssoc($result)) {
            return $row['ref_id'];
        }
        return false;
    }

    /**
     * Create a category by its title
     * @param string $title
     * @return int ref_id
     */
    protected function createCategory($title)
    {
        $parent_ref_id = $this->config->get('parent_category');

        $category = new ilObjCategory();
        $category->setTitle($title);
        $category->create();
        $category->createReference();
        $category->putInTree($parent_ref_id);
        $category->setPermissions($parent_ref_id);
        return $category->getRefId();
    }



    /**
     * Copy a course
     * @param int $sourceRefId
     * @param int targetRefId
     * @return int newRefId
     */
    protected function copyCourse($sourceRefId, $targetRefId, $typesToLink = [])
    {
        global $DIC;

        $tree = $DIC->repositoryTree();

        // prepare the copy options for all sub objects
        $options = array();
        $nodedata = $tree->getNodeData($sourceRefId);
        $nodearray = $tree->getSubTree($nodedata);
        foreach ($nodearray as $node) {
            if (in_array($node['type'], $typesToLink)) {
                $options[$node['ref_id']] = array("type" => ilCopyWizardOptions::COPY_WIZARD_LINK);
            }
            else {
                $options[$node['ref_id']] = array("type" => ilCopyWizardOptions::COPY_WIZARD_COPY);
            }
        }

        // cron context creates no data in the session
        // but existing session data is needed for ilSession::_duplicate() in ilContainer::cloneAllObject()
        // otherwise subsequent ilSoapUtil::ilClone calls will not work correctly due to missing sessison id

        $session_id = $DIC['ilAuthSession']->getId();
        ilSession::_writeData($session_id,'examAdmin');

        $source_object = ilObjectFactory::getInstanceByRefId($sourceRefId);
        $ret = $source_object->cloneAllObject(
            $session_id,
            CLIENT_ID,
            $source_object->getType(),
            $targetRefId,
            $sourceRefId,
            $options,
            false
        );
        return $ret['ref_id'];
    }

    /**
     * Update a course with the data from the orga record
     * @param ilExamAdminOrgaRecord $record
     * @param int $ref_id
     */
    public function updateCourse($record, $ref_id)
    {
        $course = new ilObjCourse($ref_id);
        $title = $record->exam_date . ' ' . $record->exam_title;
        $description = $record->fau_lecturer . " | " . $record->fau_chair . " | " . $record->exam_runs;

        $course->setTitle($title);
        $course->setDescription(nl2br($description));
        $course->setOfflineStatus(false);
        $course->update();

        $this->updateCourseManagers($record, $course);
        $this->updateCourseMembers($record, $course);
        $this->updateCourseMetadata($record, $course);

        // save the data relationship
        $data = $this->plugin->getData($course->getId());
        $data->set(ilExamAdminData::PARAM_ORGA_ID, $record->id);
        $data->write();

        // registration code
        if (empty($record->reg_code)) {
            $record->reg_code = $this->createRegistrationCode($record, $course);
        }

        // write back the course link
        $url = $this->plugin->getConfig()->get(ilExamAdminConfig::BASE_URL);
        $record->course_link = $url . '/goto.php?target=crs_' . $ref_id;

        // save the updated record
        $record->save();
    }

    /**
     * Update the course managers (admins and tutors, with test accounts) with the data from the orga record
     * @param ilExamAdminOrgaRecord $record
     * @param ilObjCourse $course
     */
    protected function updateCourseManagers($record, $course)
    {
        require_once (__DIR__ . '/class.ilExamAdminCourseUsers.php');
        $users = new ilExamAdminCourseUsers($this->plugin, $course);

        // add owner as admin (with test accounts)
        $users->addParticipants([$record->owner_id], false, ilExamAdminCourseUsers::CAT_LOCAL_ADMIN_LECTURER, false);

        // add admins (with test accounts)
        $admins = $this->connector->getUserDataByLoginList($record->getAdminsLogins());
        $users->addParticipants($users->extractUserIds($admins), false, ilExamAdminCourseUsers::CAT_LOCAL_ADMIN_LECTURER, false);

        // add correctors as tutors (with test accounts)
        $correctors = $this->connector->getUserDataByLoginList($record->getCorrectorLogins());
        $users->addParticipants($users->extractUserIds($correctors), false, ilExamAdminCourseUsers::CAT_LOCAL_TUTOR_CORRECTOR, false);
    }

    /**
     * Update the course members from my campus
     * @param ilExamAdminOrgaRecord $record
     * @param ilObjCourse $course
     */
    protected function updateCourseMembers($record, $course)
    {
        require_once (__DIR__ . '/class.ilExamAdminCampusParticipants.php');
        require_once (__DIR__ . '/class.ilExamAdminCourseUsers.php');

        $connObj = $this->plugin->getConnector();
        $usersObj = new ilExamAdminCourseUsers($this->plugin, $course);

        // get the matriculation numbers from campus
        $active_matriculations = [];
        $resigned_matriculations = [];
        foreach ($record->getExamIds() as $id) {
            if (!empty($id)) {
                $campus = new ilExamAdminCampusParticipants();
                $campus->fetchParticipants($this->plugin, $id);
                $active_matriculations = array_merge($active_matriculations, $campus->getActiveMatriculations());
                $resigned_matriculations = array_merge($resigned_matriculations, $campus->getResignedMatriculations());
            }
        }

        // resign matching local users (only those that are not active for another exam_id)
        $resigned_matriculations = array_diff($resigned_matriculations, $active_matriculations);
        foreach( $usersObj->getUserDataByMatriculationList($resigned_matriculations) as $resigned_data) {
           $usersObj->removeParticipant($resigned_data['usr_id'], true);
        }

        // add matching remote users (create local users, if necessary)
        foreach ($connObj->getUserDataByMatriculationList($active_matriculations) as $active_data) {
            $local_data = $usersObj->getMatchingUser($active_data, true, $this->config->get(ilExamAdminConfig::GLOBAL_PARTICIPANT_ROLE));
            $usersObj->addParticipant($local_data['usr_id'], ilExamAdminCourseUsers::ROLE_MEMBER);
        }
    }

    /**
     * Remove the root account from groups in the course
     * @param $ref_id
     */
    protected function removeRootParticipant($ref_id)
    {
        global $DIC;
        $tree = $DIC->repositoryTree();

        $root_id = ilObjUser::_lookupId('root');

        // remove from course
        $part = new ilCourseParticipants(ilObject::_lookupObjectId($ref_id));
        $part->delete($root_id);

        // remove from group
        foreach ($tree->getChildsByType($ref_id, 'grp') as $node) {
            $part = new ilGroupParticipants($node['obj_id']);
            $part->delete($root_id);
        }
    }

    /**
     * Update the LOM metadata of the course (used for standard search)
     * @param ilExamAdminOrgaRecord $record
     * @param ilObjCourse $course
     */
    protected function updateCourseMetadata($record, $course)
    {
        // collect patterns for keywords
        $patterns = [];
        $patterns[] = trim($record->fau_lecturer);
        $patterns[] = trim($record->fau_chair);
        $patterns[] = trim($record->exam_format);
        $patterns[] = trim($record->exam_type);
        $patterns[] = trim($record->exam_method);

        // collect the user names for authors
        $authors = [];
        foreach($this->connector->getUserDataByIds($record->owner_id) as $user) {
            $authors[] = trim($user['firstname'] . ' ' . $user['lastname']);
        }
        foreach($this->connector->getUserDataByLoginList($record->getAdminsLogins()) as $user) {
            $authors[] = trim($user['firstname'] . ' ' . $user['lastname']);
        }

        $meta = new ilMD($course->getId(), $course->getId(), $course->getType());

        // general section for keywords
        if(!is_object($general = $meta->getGeneral())) {
            $general = $meta->addGeneral();
            $general->save();
        }
        // delete existing keywords
        foreach($general->getKeywordIds() as $key_id) {
            $keyword = $general->getKeyword($key_id);
            $keyword->delete();
        }
        // save new keywords
        foreach ($patterns as $pattern) {
            $keyword = $general->addKeyword();
            $keyword->setKeywordLanguage(new ilMDLanguageItem('de'));
            $keyword->setKeyword($pattern);
            $keyword->save();
        }

        // lifecycle section for authors
        if(!is_object($life = $meta->getLifecycle())) {
            $life = $meta->addLifecycle();
            $life->save();
        }
        // delete existing authors
        foreach($life->getContributeIds() as $con_id) {
            $contrib = $life->getContribute($con_id);
            if ($contrib->getRole() == "Author") {
                $contrib->delete();
            }
        }
        // add the current list of authors
        if (!empty($authors)) {
            $contrib = $life->addContribute();
            $contrib->setRole("Author");
            $contrib->save();
            foreach (array_unique($authors) as $author) {
                $entity = $contrib->addEntity();
                $entity->setEntity($author);
                $entity->save();
            }
        }
    }

    /**
     * create a registration code for the course
     * @param ilExamAdminOrgaRecord $record
     * @param ilObjCourse $course
     * @return string
     */
    public function createRegistrationCode($record, $course)
    {
        $codeObj = new ilRegistrationCode();
        $codeObj->title = $course->getTitle();
        $codeObj->reg_enabled = true;
        $codeObj->ext_enabled = false;
        $codeObj->use_limit = 1000;
        $codeObj->login_generation_type = 'guestselfreg';
        $codeObj->password_generation = 0;  //manual entry (will be shown afterwards)
        $codeObj->captcha_required = false;
        $codeObj->email_verification = false;
        $codeObj->global_role = $this->plugin->getConfig()->get(ilExamAdminConfig::GLOBAL_PARTICIPANT_ROLE);
        $codeObj->local_roles = [$course->getMembersObject()->getRoleId(IL_CRS_MEMBER)];
        $codeObj->limit_type = "unlimited";
        $codeObj->limit_date = new ilDateTime();
        $codeObj->limit_duration = array();
        $codeObj->write();

        return $codeObj->code;
    }

    /**
     * Synchronize the data of users (incl. passwords)
     * @retrun int
     */
    public function syncUserData()
    {
        require_once (__DIR__ . '/class.ilExamAdminUsers.php');
        $users = new ilExamAdminUsers($this->plugin);

        $count = 0;
        $count += $users->synchronizeByCategory(ilExamAdminUsers::CAT_GLOBAL_LECTURER);
        $count += $users->synchronizeByCategory(ilExamAdminUsers::CAT_GLOBAL_PARTICIPANT);

        return $count;
    }

}